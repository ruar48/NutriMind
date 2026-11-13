<?php

use App\Enums\FeedbackType;
use App\Enums\GradeLevel;
use App\Enums\Permission;
use App\Enums\UserRole;
use App\Models\Classroom;
use App\Models\LearnerFeedback;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\Subject;
use App\Models\User;
use App\Services\RolePermissions;
use Carbon\CarbonImmutable;

/**
 * A Health teacher with Health 5 – Sampaguita, a learner (Ana) enrolled there and her 60% attempt at the teacher's quiz.
 *
 * @return array{teacher: User, subject: Subject, classroom: Classroom, student: User, quiz: Quiz, attempt: QuizAttempt}
 */
function feedbackPagesSetup(): array
{
    $subject = Subject::factory()->create(['name' => 'Health']);
    $teacher = User::factory()->teaching($subject)->create();
    $classroom = Classroom::factory()->for($teacher, 'teacher')->for($subject)->create(['grade_level' => GradeLevel::Five, 'name' => 'Health 5 – Sampaguita']);
    $student = User::factory()->student(GradeLevel::Five)->enrolledIn($classroom)->create(['name' => 'Ana Cruz', 'username' => 'ana_cruz']);
    $quiz = Quiz::factory()->published()->for($teacher, 'teacher')->for($subject)->create(['title' => 'Healthy Plate Quiz', 'passing_score' => 75]);
    $attempt = QuizAttempt::factory()->for($quiz)->for($student, 'student')->for($classroom)->create([
        'score' => 6, 'max_score' => 10, 'percentage' => 60, 'passed' => false,
    ]);

    return compact('teacher', 'subject', 'classroom', 'student', 'quiz', 'attempt');
}

/**
 * A valid feedback payload for the setup's learner.
 *
 * @param  array{student: User}  $data
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function feedbackPayload(array $data, array $overrides = []): array
{
    return array_merge([
        'student_id' => $data['student']->id,
        'classroom_id' => $data['classroom']->id,
        'quiz_attempt_id' => $data['attempt']->id,
        'type' => 'guidance',
        'message' => 'Review the Go, Grow and Glow foods before your next try.',
        'is_visible_to_guardians' => '1',
    ], $overrides);
}

describe('index', function () {
    it('sends guests to the sign-in page', function () {
        $this->get(route('teacher.feedback.index'))->assertRedirectToRoute('login');
    });

    it('forbids other roles', function (UserRole $role) {
        $this->actingAs(User::factory()->{$role->value}()->create())
            ->get(route('teacher.feedback.index'))
            ->assertForbidden();
    })->with([
        'administrator' => UserRole::Admin,
        'parent' => UserRole::Parent,
    ]);

    it('refuses teachers without the give feedback permission', function () {
        $data = feedbackPagesSetup();
        app(RolePermissions::class)->revoke(UserRole::Teacher, Permission::GiveFeedback);

        $this->actingAs($data['teacher'])
            ->get(route('teacher.feedback.index'))
            ->assertForbidden()
            ->assertSeeText(RolePermissions::deniedMessage(Permission::GiveFeedback));
    });

    it('lists the teacher’s own feedback, newest first, with visibility and seen status', function () {
        $data = feedbackPagesSetup();
        LearnerFeedback::factory()->for($data['teacher'], 'teacher')->for($data['student'], 'student')
            ->create(['message' => 'Older private note.', 'is_visible_to_guardians' => false, 'created_at' => now()->subDays(2)]);
        LearnerFeedback::factory()->for($data['teacher'], 'teacher')->for($data['student'], 'student')
            ->create(['message' => 'Seen praise.', 'type' => FeedbackType::Praise, 'read_at' => now()->subHour(), 'created_at' => now()->subDay()]);
        LearnerFeedback::factory()->for($data['teacher'], 'teacher')->for($data['student'], 'student')
            ->create(['message' => 'Newest about the attempt.', 'quiz_attempt_id' => $data['attempt']->id]);
        LearnerFeedback::factory()->for($data['student'], 'student')->create(['message' => 'A colleague wrote this.']);

        $response = $this->actingAs($data['teacher'])->get(route('teacher.feedback.index'));

        $response->assertOk()
            ->assertViewIs('teacher.feedback.index')
            ->assertSeeTextInOrder(['Newest about the attempt.', 'Seen praise.', 'Older private note.'])
            ->assertDontSeeText('A colleague wrote this.')
            ->assertSeeText('Private note')
            ->assertSeeText('Visible to parents')
            ->assertSeeText('Not seen yet')
            ->assertSeeText('Seen 1 hour ago')
            ->assertSee(route('teacher.attempts.show', $data['attempt']), false)
            ->assertSee(route('teacher.students.show', $data['student']), false);
    });

    it('filters by classroom, type and learner search', function (Closure $query, array $expected) {
        $data = feedbackPagesSetup();
        $other = Classroom::factory()->for($data['teacher'], 'teacher')->for($data['subject'])->create();
        $ben = User::factory()->student()->enrolledIn($other)->create(['name' => 'Ben Reyes']);
        LearnerFeedback::factory()->for($data['teacher'], 'teacher')->for($data['student'], 'student')->for($data['classroom'])
            ->create(['message' => 'Ana guidance.', 'type' => FeedbackType::Guidance]);
        LearnerFeedback::factory()->for($data['teacher'], 'teacher')->for($ben, 'student')->for($other)
            ->create(['message' => 'Ben praise.', 'type' => FeedbackType::Praise]);

        $response = $this->actingAs($data['teacher'])->get(route('teacher.feedback.index', $query($data)));

        expect($response->viewData('feedback')->pluck('message')->all())->toBe($expected);
    })->with([
        'classroom' => [fn (array $data): array => ['classroom' => $data['classroom']->id], ['Ana guidance.']],
        'type' => [fn (array $data): array => ['type' => 'praise'], ['Ben praise.']],
        'learner name' => [fn (array $data): array => ['search' => 'Reyes'], ['Ben praise.']],
        'learner username' => [fn (array $data): array => ['search' => 'ana_cruz'], ['Ana guidance.']],
        'one learner' => [fn (array $data): array => ['student' => $data['student']->id], ['Ana guidance.']],
        'unknown type is ignored' => [fn (array $data): array => ['type' => 'shout'], ['Ben praise.', 'Ana guidance.']],
    ]);

    it('reports filters outside the teacher’s scope as missing', function (Closure $query) {
        $data = feedbackPagesSetup();

        $this->actingAs($data['teacher'])
            ->get(route('teacher.feedback.index', $query($data)))
            ->assertNotFound();
    })->with([
        'another teacher’s classroom' => [fn (array $data): array => ['classroom' => Classroom::factory()->create()->id]],
        'a learner the teacher does not know' => [fn (array $data): array => ['student' => User::factory()->student()->create()->id]],
        'an account that is not a learner' => [fn (array $data): array => ['student' => $data['teacher']->id]],
        'a malformed learner id' => [fn (array $data): array => ['student' => 'ana']],
    ]);

    it('keeps the learner filter for a learner who left the teacher’s classrooms', function () {
        $data = feedbackPagesSetup();
        LearnerFeedback::factory()->for($data['teacher'], 'teacher')->for($data['student'], 'student')->create(['message' => 'Before Ana moved.']);
        $data['classroom']->students()->detach($data['student']);

        $this->actingAs($data['teacher'])
            ->get(route('teacher.feedback.index', ['student' => $data['student']->id]))
            ->assertOk()
            ->assertSeeText('Feedback for Ana Cruz')
            ->assertSeeText('Before Ana moved.')
            ->assertDontSee(route('teacher.students.show', $data['student']), false);
    });

    it('paginates 12 messages per page', function () {
        $data = feedbackPagesSetup();
        foreach (range(1, 13) as $number) {
            LearnerFeedback::factory()->for($data['teacher'], 'teacher')->for($data['student'], 'student')
                ->create(['message' => 'Message '.$number, 'created_at' => now()->subMinutes(100 - $number)]);
        }

        $response = $this->actingAs($data['teacher'])->get(route('teacher.feedback.index', ['page' => 2]));

        expect($response->viewData('feedback')->pluck('message')->all())->toBe(['Message 1']);
    });

    it('explains an empty list', function () {
        $data = feedbackPagesSetup();

        $this->actingAs($data['teacher'])
            ->get(route('teacher.feedback.index'))
            ->assertOk()
            ->assertSeeText('No feedback yet')
            ->assertSeeText('Write your first feedback');
    });

    it('explains an empty filtered list', function () {
        $data = feedbackPagesSetup();
        LearnerFeedback::factory()->for($data['teacher'], 'teacher')->for($data['student'], 'student')->create();

        $this->actingAs($data['teacher'])
            ->get(route('teacher.feedback.index', ['search' => 'Nobody']))
            ->assertOk()
            ->assertSeeText('No feedback matches your filters');
    });
});

describe('create', function () {
    it('sends guests to the sign-in page', function () {
        $this->get(route('teacher.feedback.create'))->assertRedirectToRoute('login');
    });

    it('refuses teachers without the give feedback permission', function () {
        $data = feedbackPagesSetup();
        app(RolePermissions::class)->revoke(UserRole::Teacher, Permission::GiveFeedback);

        $this->actingAs($data['teacher'])
            ->get(route('teacher.feedback.create', ['student' => $data['student']->id]))
            ->assertForbidden();
    });

    it('asks for one of the teacher’s learners first', function () {
        $data = feedbackPagesSetup();
        User::factory()->student()->enrolledIn(Classroom::factory()->create())->create(['name' => 'Other Teacher Learner']);
        User::factory()->student()->enrolledIn(Classroom::factory()->for($data['teacher'], 'teacher')->for($data['subject'])->archived()->create())
            ->create(['name' => 'Archived Class Learner']);

        $response = $this->actingAs($data['teacher'])->get(route('teacher.feedback.create'));

        $response->assertOk()
            ->assertSeeText('Choose a learner')
            ->assertSee('Ana Cruz · Health 5 – Sampaguita', false)
            ->assertDontSeeText('Other Teacher Learner')
            ->assertDontSeeText('Archived Class Learner')
            ->assertDontSee('id="feedback-form"', false);
    });

    it('explains when the teacher has no learners', function () {
        $this->actingAs(User::factory()->teaching()->create())
            ->get(route('teacher.feedback.create'))
            ->assertOk()
            ->assertSeeText('No learners to write to yet');
    });

    it('shows the form with the learner’s classroom, attempts and suggestions', function () {
        $data = feedbackPagesSetup();
        $colleagueAttempt = QuizAttempt::factory()->for(Quiz::factory()->published()->for($data['subject']))->for($data['student'], 'student')
            ->create(['percentage' => 40, 'score' => 4, 'max_score' => 10]);

        $response = $this->actingAs($data['teacher'])->get(route('teacher.feedback.create', ['student' => $data['student']->id]));

        $response->assertOk()
            ->assertViewIs('teacher.feedback.create')
            ->assertSee('id="feedback-form"', false)
            ->assertSee('name="student_id" value="'.$data['student']->id.'"', false)
            ->assertSee('Healthy Plate Quiz · Attempt 1 · 60%', false)
            ->assertSee('data-suggestion-type="concern"', false)
            ->assertSee('data-monitoring-suggestion-type="concern"', false);
        expect($response->viewData('attemptOptions')->keys()->all())->toBe([$data['attempt']->id])
            ->and($response->viewData('attemptOptions')->keys()->all())->not->toContain($colleagueAttempt->id)
            ->and($response->viewData('classroomOptions')->all())->toBe([$data['classroom']->id => 'Health 5 – Sampaguita'])
            ->and($response->viewData('feedback')->classroom_id)->toBe($data['classroom']->id)
            ->and($response->viewData('feedback')->quiz_attempt_id)->toBeNull()
            ->and($response->viewData('feedback')->is_visible_to_guardians)->toBeTrue();
    });

    it('prefills the attempt the feedback is about', function () {
        $data = feedbackPagesSetup();

        $response = $this->actingAs($data['teacher'])
            ->get(route('teacher.feedback.create', ['student' => $data['student']->id, 'attempt' => $data['attempt']->id]));

        $response->assertOk();
        expect($response->viewData('feedback')->quiz_attempt_id)->toBe($data['attempt']->id)
            ->and($response->viewData('feedback')->classroom_id)->toBe($data['classroom']->id);
        $response->assertSee('value="'.$data['attempt']->id.'" selected', false);
    });

    it('reports prefills outside the teacher’s scope as missing', function (Closure $query) {
        $data = feedbackPagesSetup();

        $this->actingAs($data['teacher'])
            ->get(route('teacher.feedback.create', $query($data)))
            ->assertNotFound();
    })->with([
        'another teacher’s learner' => [fn (array $data): array => ['student' => User::factory()->student()->enrolledIn(Classroom::factory()->create())->create()->id]],
        'an account that is not a learner' => [fn (array $data): array => ['student' => $data['teacher']->id]],
        'a malformed learner id' => [fn (array $data): array => ['student' => '1 OR 1=1']],
        'another learner’s attempt' => [fn (array $data): array => [
            'student' => $data['student']->id,
            'attempt' => QuizAttempt::factory()->for($data['quiz'])->for($data['classroom'])->create()->id,
        ]],
        'an attempt at another teacher’s quiz' => [fn (array $data): array => [
            'student' => $data['student']->id,
            'attempt' => QuizAttempt::factory()->for(Quiz::factory()->published()->for($data['subject']))->for($data['student'], 'student')->create()->id,
        ]],
    ]);
});

describe('store', function () {
    it('sends guests to the sign-in page', function () {
        $this->post(route('teacher.feedback.store'), [])->assertRedirectToRoute('login');
    });

    it('forbids other roles', function (UserRole $role) {
        $this->actingAs(User::factory()->{$role->value}()->create())
            ->post(route('teacher.feedback.store'), [])
            ->assertForbidden();
    })->with([
        'administrator' => UserRole::Admin,
        'parent' => UserRole::Parent,
    ]);

    it('refuses teachers without the give feedback permission before validating', function () {
        $data = feedbackPagesSetup();
        app(RolePermissions::class)->revoke(UserRole::Teacher, Permission::GiveFeedback);

        $this->actingAs($data['teacher'])
            ->post(route('teacher.feedback.store'), [])
            ->assertForbidden()
            ->assertSessionHasNoErrors();
    });

    it('saves feedback for one of the teacher’s learners', function () {
        $data = feedbackPagesSetup();

        $response = $this->actingAs($data['teacher'])->post(route('teacher.feedback.store'), feedbackPayload($data));

        $response->assertRedirectToRoute('teacher.feedback.index')
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status', 'Your feedback for Ana Cruz was saved.');
        $this->assertDatabaseHas('learner_feedback', [
            'teacher_id' => $data['teacher']->id,
            'student_id' => $data['student']->id,
            'classroom_id' => $data['classroom']->id,
            'quiz_attempt_id' => $data['attempt']->id,
            'type' => 'guidance',
            'message' => 'Review the Go, Grow and Glow foods before your next try.',
            'is_visible_to_guardians' => true,
            'read_at' => null,
        ]);
    });

    it('keeps a private note from parents', function () {
        $data = feedbackPagesSetup();

        $this->actingAs($data['teacher'])
            ->post(route('teacher.feedback.store'), feedbackPayload($data, ['is_visible_to_guardians' => '0', 'classroom_id' => '', 'quiz_attempt_id' => '']))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('learner_feedback', [
            'student_id' => $data['student']->id,
            'classroom_id' => null,
            'quiz_attempt_id' => null,
            'is_visible_to_guardians' => false,
        ]);
    });

    it('shows feedback to parents when the visibility is not sent', function () {
        $data = feedbackPagesSetup();
        $payload = feedbackPayload($data);
        unset($payload['is_visible_to_guardians']);

        $this->actingAs($data['teacher'])->post(route('teacher.feedback.store'), $payload)->assertSessionHasNoErrors();

        $this->assertDatabaseHas('learner_feedback', ['student_id' => $data['student']->id, 'is_visible_to_guardians' => true]);
    });

    it('reports a learner outside the teacher’s classrooms as missing', function () {
        $data = feedbackPagesSetup();
        $stranger = User::factory()->student()->enrolledIn(Classroom::factory()->create())->create();

        $this->actingAs($data['teacher'])
            ->post(route('teacher.feedback.store'), feedbackPayload($data, ['student_id' => $stranger->id]))
            ->assertNotFound();

        $this->assertDatabaseCount('learner_feedback', 0);
    });

    it('validates the feedback', function (array $overrides, string $field, string $message) {
        $data = feedbackPagesSetup();

        $this->actingAs($data['teacher'])
            ->post(route('teacher.feedback.store'), feedbackPayload($data, $overrides))
            ->assertSessionHasErrors([$field => $message]);

        $this->assertDatabaseCount('learner_feedback', 0);
    })->with([
        'missing learner' => [['student_id' => ''], 'student_id', 'Choose the learner this feedback is for.'],
        'malformed learner' => [['student_id' => 'ana'], 'student_id', 'Choose the learner this feedback is for.'],
        'not a learner account' => [['student_id' => 0], 'student_id', 'Choose one of your learners.'],
        'missing type' => [['type' => ''], 'type', 'Choose the kind of feedback.'],
        'unknown type' => [['type' => 'shout'], 'type', 'Choose the kind of feedback.'],
        'missing message' => [['message' => ''], 'message', 'Write a message for the learner.'],
        'message too long' => [['message' => str_repeat('a', 1001)], 'message', 'Keep the message to 1000 characters or fewer.'],
        'malformed classroom' => [['classroom_id' => 'x'], 'classroom_id', 'Choose one of your classrooms this learner is enrolled in.'],
        'malformed attempt' => [['quiz_attempt_id' => 'x'], 'quiz_attempt_id', 'Choose one of this learner’s attempts at your quizzes.'],
        'malformed visibility' => [['is_visible_to_guardians' => 'maybe'], 'is_visible_to_guardians', 'Choose whether parents and guardians can read this feedback.'],
    ]);

    it('accepts a message of exactly 1000 characters', function () {
        $data = feedbackPagesSetup();

        $this->actingAs($data['teacher'])
            ->post(route('teacher.feedback.store'), feedbackPayload($data, ['message' => str_repeat('a', 1000)]))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseCount('learner_feedback', 1);
    });

    it('rejects a classroom that is not one of the teacher’s classrooms of the learner', function (Closure $classroom) {
        $data = feedbackPagesSetup();

        $this->actingAs($data['teacher'])
            ->post(route('teacher.feedback.store'), feedbackPayload($data, ['classroom_id' => $classroom($data)]))
            ->assertSessionHasErrors(['classroom_id' => 'Choose one of your classrooms this learner is enrolled in.']);
    })->with([
        'another teacher’s classroom of the learner' => [function (array $data): int {
            $classroom = Classroom::factory()->create();
            $classroom->students()->attach($data['student']);

            return $classroom->id;
        }],
        'the teacher’s classroom without the learner' => [fn (array $data): int => Classroom::factory()->for($data['teacher'], 'teacher')->for($data['subject'])->create()->id],
        'an unknown classroom' => [fn (array $data): int => 999999],
    ]);

    it('rejects an attempt that is not the learner’s attempt at the teacher’s quiz', function (Closure $attempt) {
        $data = feedbackPagesSetup();

        $this->actingAs($data['teacher'])
            ->post(route('teacher.feedback.store'), feedbackPayload($data, ['quiz_attempt_id' => $attempt($data)]))
            ->assertSessionHasErrors(['quiz_attempt_id' => 'Choose one of this learner’s attempts at your quizzes.']);
    })->with([
        'another learner’s attempt' => [fn (array $data): int => QuizAttempt::factory()->for($data['quiz'])->for($data['classroom'])->create()->id],
        'an attempt at another teacher’s quiz' => [fn (array $data): int => QuizAttempt::factory()->for(Quiz::factory()->published()->for($data['subject']))->for($data['student'], 'student')->create()->id],
        'an attempt that is not submitted' => [fn (array $data): int => QuizAttempt::factory()->inProgress()->for($data['quiz'])->for($data['student'], 'student')->create()->id],
    ]);
});

describe('edit', function () {
    it('shows the teacher’s feedback in the form', function () {
        $data = feedbackPagesSetup();
        $feedback = LearnerFeedback::factory()->for($data['teacher'], 'teacher')->for($data['student'], 'student')->for($data['classroom'])
            ->create(['message' => 'Keep it up!', 'type' => FeedbackType::Encouragement, 'quiz_attempt_id' => $data['attempt']->id]);

        $this->actingAs($data['teacher'])
            ->get(route('teacher.feedback.edit', $feedback))
            ->assertOk()
            ->assertViewIs('teacher.feedback.edit')
            ->assertSee('Keep it up!</textarea>', false)
            ->assertSee('value="encouragement"', false)
            ->assertSee('value="'.$data['attempt']->id.'" selected', false)
            ->assertSee('value="'.$data['classroom']->id.'" selected', false)
            ->assertSee(route('teacher.feedback.destroy', $feedback), false);
    });

    it('reports another teacher’s feedback as missing', function () {
        $data = feedbackPagesSetup();

        $this->actingAs($data['teacher'])
            ->get(route('teacher.feedback.edit', LearnerFeedback::factory()->for($data['student'], 'student')->create()))
            ->assertNotFound();
    });

    it('refuses teachers without the give feedback permission', function () {
        $data = feedbackPagesSetup();
        $feedback = LearnerFeedback::factory()->for($data['teacher'], 'teacher')->for($data['student'], 'student')->create();
        app(RolePermissions::class)->revoke(UserRole::Teacher, Permission::GiveFeedback);

        $this->actingAs($data['teacher'])->get(route('teacher.feedback.edit', $feedback))->assertForbidden();
    });

    it('still edits feedback after the learner left the teacher’s classrooms', function () {
        $data = feedbackPagesSetup();
        $feedback = LearnerFeedback::factory()->for($data['teacher'], 'teacher')->for($data['student'], 'student')->for($data['classroom'])->create();
        $data['classroom']->students()->detach($data['student']);

        $this->actingAs($data['teacher'])
            ->get(route('teacher.feedback.edit', $feedback))
            ->assertOk()
            ->assertSeeText('This learner is no longer in your classrooms.')
            ->assertSeeText('Saving removes the link.')
            ->assertDontSee('data-monitoring-suggestions', false);
    });
});

describe('update', function () {
    it('saves the changes and marks an edited message unread for parents', function () {
        $data = feedbackPagesSetup();
        $feedback = LearnerFeedback::factory()->for($data['teacher'], 'teacher')->for($data['student'], 'student')
            ->create(['message' => 'Old message.', 'read_at' => now()->subDay()]);
        $payload = feedbackPayload($data, ['message' => 'New message.', 'type' => 'praise', 'is_visible_to_guardians' => '0']);
        unset($payload['student_id']);

        $response = $this->actingAs($data['teacher'])->put(route('teacher.feedback.update', $feedback), $payload);

        $response->assertRedirectToRoute('teacher.feedback.index')
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status', 'Your feedback for Ana Cruz was updated.');
        expect($feedback->refresh())
            ->message->toBe('New message.')
            ->type->toBe(FeedbackType::Praise)
            ->is_visible_to_guardians->toBeFalse()
            ->classroom_id->toBe($data['classroom']->id)
            ->quiz_attempt_id->toBe($data['attempt']->id)
            ->read_at->toBeNull();
    });

    it('keeps the seen status when the message does not change', function () {
        $this->travelTo(CarbonImmutable::parse('2026-11-13 10:00:00'));
        $data = feedbackPagesSetup();
        $feedback = LearnerFeedback::factory()->for($data['teacher'], 'teacher')->for($data['student'], 'student')
            ->create(['message' => 'Same message.', 'read_at' => now()->subDay(), 'is_visible_to_guardians' => true]);

        $this->actingAs($data['teacher'])
            ->put(route('teacher.feedback.update', $feedback), ['type' => 'praise', 'message' => 'Same message.'])
            ->assertSessionHasNoErrors();

        expect($feedback->refresh())
            ->read_at->toDateTimeString()->toBe('2026-11-12 10:00:00')
            ->is_visible_to_guardians->toBeTrue()
            ->classroom_id->toBeNull();
    });

    it('does not let the learner change', function () {
        $data = feedbackPagesSetup();
        $feedback = LearnerFeedback::factory()->for($data['teacher'], 'teacher')->for($data['student'], 'student')->create();
        $other = User::factory()->student()->enrolledIn($data['classroom'])->create();

        $this->actingAs($data['teacher'])
            ->put(route('teacher.feedback.update', $feedback), ['student_id' => $other->id, 'type' => 'praise', 'message' => 'Hello'])
            ->assertSessionHasNoErrors();

        expect($feedback->refresh()->student_id)->toBe($data['student']->id);
    });

    it('validates the changes', function () {
        $data = feedbackPagesSetup();
        $feedback = LearnerFeedback::factory()->for($data['teacher'], 'teacher')->for($data['student'], 'student')->create(['message' => 'Unchanged.']);

        $this->actingAs($data['teacher'])
            ->put(route('teacher.feedback.update', $feedback), [
                'type' => '',
                'message' => '',
                'classroom_id' => Classroom::factory()->create()->id,
            ])
            ->assertSessionHasErrors([
                'type' => 'Choose the kind of feedback.',
                'message' => 'Write a message for the learner.',
            ]);

        expect($feedback->refresh()->message)->toBe('Unchanged.');
    });

    it('rejects a classroom of another teacher even after the learner left', function () {
        $data = feedbackPagesSetup();
        $feedback = LearnerFeedback::factory()->for($data['teacher'], 'teacher')->for($data['student'], 'student')->create();
        $data['classroom']->students()->detach($data['student']);
        $foreign = Classroom::factory()->create();
        $foreign->students()->attach($data['student']);

        $this->actingAs($data['teacher'])
            ->put(route('teacher.feedback.update', $feedback), ['type' => 'praise', 'message' => 'Hello', 'classroom_id' => $foreign->id])
            ->assertSessionHasErrors(['classroom_id' => 'Choose one of your classrooms this learner is enrolled in.']);
    });

    it('reports another teacher’s feedback as missing', function () {
        $data = feedbackPagesSetup();
        $feedback = LearnerFeedback::factory()->for($data['student'], 'student')->create(['message' => 'Not yours.']);

        $this->actingAs($data['teacher'])
            ->put(route('teacher.feedback.update', $feedback), ['type' => 'praise', 'message' => 'Hijacked'])
            ->assertNotFound();

        expect($feedback->refresh()->message)->toBe('Not yours.');
    });

    it('refuses teachers without the give feedback permission', function () {
        $data = feedbackPagesSetup();
        $feedback = LearnerFeedback::factory()->for($data['teacher'], 'teacher')->for($data['student'], 'student')->create();
        app(RolePermissions::class)->revoke(UserRole::Teacher, Permission::GiveFeedback);

        $this->actingAs($data['teacher'])
            ->put(route('teacher.feedback.update', $feedback), ['type' => 'praise', 'message' => 'Hello'])
            ->assertForbidden();
    });
});

describe('destroy', function () {
    it('deletes the teacher’s feedback', function () {
        $data = feedbackPagesSetup();
        $feedback = LearnerFeedback::factory()->for($data['teacher'], 'teacher')->for($data['student'], 'student')->create();

        $this->actingAs($data['teacher'])
            ->delete(route('teacher.feedback.destroy', $feedback))
            ->assertRedirectToRoute('teacher.feedback.index')
            ->assertSessionHas('status', 'Your feedback for Ana Cruz was deleted.');

        $this->assertModelMissing($feedback);
    });

    it('reports another teacher’s feedback as missing', function () {
        $data = feedbackPagesSetup();
        $feedback = LearnerFeedback::factory()->for($data['student'], 'student')->create();

        $this->actingAs($data['teacher'])->delete(route('teacher.feedback.destroy', $feedback))->assertNotFound();

        $this->assertModelExists($feedback);
    });

    it('refuses teachers without the give feedback permission', function () {
        $data = feedbackPagesSetup();
        $feedback = LearnerFeedback::factory()->for($data['teacher'], 'teacher')->for($data['student'], 'student')->create();
        app(RolePermissions::class)->revoke(UserRole::Teacher, Permission::GiveFeedback);

        $this->actingAs($data['teacher'])->delete(route('teacher.feedback.destroy', $feedback))->assertForbidden();

        $this->assertModelExists($feedback);
    });

    it('sends guests to the sign-in page', function () {
        $this->delete(route('teacher.feedback.destroy', LearnerFeedback::factory()->create()))->assertRedirectToRoute('login');
    });
});
