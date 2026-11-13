<?php

use App\Enums\GradeLevel;
use App\Enums\Permission;
use App\Enums\QuizStatus;
use App\Enums\UserRole;
use App\Http\Requests\Teacher\UpdateQuizStatusRequest;
use App\Models\Classroom;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\QuizQuestion;
use App\Models\User;
use App\Policies\QuizPolicy;
use App\Services\RolePermissions;

/**
 * Create a quiz in the given status that is ready to publish: one question, one active classroom, reviewed.
 *
 * @return array{0: User, 1: Quiz}
 */
function quizStatusScenario(QuizStatus $status = QuizStatus::Draft): array
{
    $teacher = User::factory()->teaching()->create();
    $subject = $teacher->assignedSubjects->first();
    $quiz = Quiz::factory()->for($teacher, 'teacher')->for($subject)->create([
        'grade_level' => GradeLevel::Five,
        'status' => $status,
        'reviewed_at' => now()->subHour(),
        'published_at' => $status === QuizStatus::Draft ? null : now()->subDays(3),
    ]);
    QuizQuestion::factory()->for($quiz)->create();
    $quiz->classrooms()->attach(Classroom::factory()->for($teacher, 'teacher')->for($subject)->create(['grade_level' => GradeLevel::Five]));

    return [$teacher, $quiz];
}

it('sends guests to the sign-in page', function () {
    $this->put(route('teacher.quizzes.status.update', Quiz::factory()->create()), ['status' => 'published'])
        ->assertRedirectToRoute('login');
});

it('forbids other roles', function (string $role) {
    $this->actingAs(User::factory()->{$role}()->create())
        ->put(route('teacher.quizzes.status.update', Quiz::factory()->create()), ['status' => 'published'])
        ->assertForbidden();
})->with(['admin', 'parent', 'student']);

it('publishes a ready draft and records when it was first published', function () {
    $this->freezeSecond();
    [$teacher, $quiz] = quizStatusScenario();

    $this->actingAs($teacher)
        ->from(route('teacher.quizzes.show', $quiz))
        ->put(route('teacher.quizzes.status.update', $quiz), ['status' => 'published'])
        ->assertRedirect(route('teacher.quizzes.show', $quiz))
        ->assertSessionHasNoErrors()
        ->assertSessionHas('status', 'Quiz published. Learners in the assigned classrooms can take it now.');

    expect($quiz->refresh())
        ->status->toBe(QuizStatus::Published)
        ->and($quiz->published_at->toDateTimeString())->toBe(now()->toDateTimeString());
});

it('lists every missing publishing step', function () {
    $teacher = User::factory()->teaching()->create();
    $subject = $teacher->assignedSubjects->first();
    $quiz = Quiz::factory()->for($teacher, 'teacher')->for($subject)->create(['grade_level' => GradeLevel::Five]);
    $quiz->classrooms()->attach(Classroom::factory()->archived()->for($teacher, 'teacher')->for($subject)->create(['grade_level' => GradeLevel::Five]));

    $response = $this->actingAs($teacher)
        ->from(route('teacher.quizzes.show', $quiz))
        ->put(route('teacher.quizzes.status.update', $quiz), ['status' => 'published']);

    $response->assertRedirect(route('teacher.quizzes.show', $quiz))
        ->assertSessionHasErrors(['status' => UpdateQuizStatusRequest::NEEDS_QUESTION_MESSAGE])
        ->assertSessionHasErrors(['status' => UpdateQuizStatusRequest::NEEDS_CLASSROOM_MESSAGE])
        ->assertSessionHasErrors(['status' => UpdateQuizStatusRequest::NEEDS_REVIEW_MESSAGE]);
    expect($quiz->refresh()->status)->toBe(QuizStatus::Draft);
});

it('shows the refusal on the page the teacher came from', function () {
    $teacher = User::factory()->teaching()->create();
    $quiz = Quiz::factory()->for($teacher, 'teacher')->for($teacher->assignedSubjects->first())->create();

    $this->actingAs($teacher)
        ->from(route('teacher.quizzes.show', $quiz))
        ->followingRedirects()
        ->put(route('teacher.quizzes.status.update', $quiz), ['status' => 'published'])
        ->assertOk()
        ->assertSeeText('The quiz status did not change')
        ->assertSeeText(UpdateQuizStatusRequest::NEEDS_QUESTION_MESSAGE);
});

it('refuses to publish a draft changed since its review', function () {
    [$teacher, $quiz] = quizStatusScenario();
    $quiz->forceFill(['reviewed_at' => null])->save();

    $this->actingAs($teacher)
        ->put(route('teacher.quizzes.status.update', $quiz), ['status' => 'published'])
        ->assertSessionHasErrors(['status' => UpdateQuizStatusRequest::NEEDS_REVIEW_MESSAGE]);
});

it('closes a published quiz', function () {
    [$teacher, $quiz] = quizStatusScenario(QuizStatus::Published);

    $this->actingAs($teacher)
        ->put(route('teacher.quizzes.status.update', $quiz), ['status' => 'closed'])
        ->assertSessionHas('status', 'Quiz closed. Learners can no longer start it.');

    expect($quiz->refresh()->status)->toBe(QuizStatus::Closed);
});

it('reopens a closed quiz and keeps its first publishing date', function () {
    [$teacher, $quiz] = quizStatusScenario(QuizStatus::Closed);
    $publishedAt = $quiz->published_at->toDateTimeString();

    $this->actingAs($teacher)
        ->put(route('teacher.quizzes.status.update', $quiz), ['status' => 'published'])
        ->assertSessionHas('status', 'Quiz reopened. Learners can take it again.');

    expect($quiz->refresh())
        ->status->toBe(QuizStatus::Published)
        ->and($quiz->published_at->toDateTimeString())->toBe($publishedAt);
});

it('refuses to reopen a quiz without an active classroom', function () {
    [$teacher, $quiz] = quizStatusScenario(QuizStatus::Closed);
    $quiz->classrooms()->detach();

    $this->actingAs($teacher)
        ->put(route('teacher.quizzes.status.update', $quiz), ['status' => 'published'])
        ->assertSessionHasErrors(['status' => UpdateQuizStatusRequest::NEEDS_CLASSROOM_MESSAGE]);

    expect($quiz->refresh()->status)->toBe(QuizStatus::Closed);
});

it('moves a quiz nobody attempted back to draft', function (QuizStatus $status) {
    [$teacher, $quiz] = quizStatusScenario($status);

    $this->actingAs($teacher)
        ->put(route('teacher.quizzes.status.update', $quiz), ['status' => 'draft'])
        ->assertSessionHas('status', 'Quiz moved back to draft. Learners no longer see it, and you can edit its questions.');

    expect($quiz->refresh()->status)->toBe(QuizStatus::Draft);
})->with([QuizStatus::Published, QuizStatus::Closed]);

it('keeps an attempted quiz out of draft', function (QuizStatus $status) {
    [$teacher, $quiz] = quizStatusScenario($status);
    QuizAttempt::factory()->for($quiz)->create();

    $this->actingAs($teacher)
        ->put(route('teacher.quizzes.status.update', $quiz), ['status' => 'draft'])
        ->assertSessionHasErrors(['status' => UpdateQuizStatusRequest::DRAFT_WITH_ATTEMPTS_MESSAGE]);

    expect($quiz->refresh()->status)->toBe($status);
})->with([QuizStatus::Published, QuizStatus::Closed]);

it('refuses transitions that make no sense', function (QuizStatus $from, string $to, string $message) {
    [$teacher, $quiz] = quizStatusScenario($from);

    $this->actingAs($teacher)
        ->put(route('teacher.quizzes.status.update', $quiz), ['status' => $to])
        ->assertSessionHasErrors(['status' => $message]);

    expect($quiz->refresh()->status)->toBe($from);
})->with([
    'close a draft' => [QuizStatus::Draft, 'closed', UpdateQuizStatusRequest::CLOSE_DRAFT_MESSAGE],
    'draft to draft' => [QuizStatus::Draft, 'draft', 'This quiz is already a draft.'],
    'publish a published quiz' => [QuizStatus::Published, 'published', 'This quiz is already published.'],
    'close a closed quiz' => [QuizStatus::Closed, 'closed', 'This quiz is already closed.'],
]);

it('requires a valid status', function (?string $status, string $message) {
    [$teacher, $quiz] = quizStatusScenario();

    $this->actingAs($teacher)
        ->put(route('teacher.quizzes.status.update', $quiz), ['status' => $status])
        ->assertSessionHasErrors(['status' => $message]);
})->with([
    'missing' => [null, 'Choose the new status of the quiz.'],
    'unknown' => ['archived', 'Choose a valid quiz status.'],
]);

it('forbids teachers without the quiz permission', function () {
    [$teacher, $quiz] = quizStatusScenario();
    app(RolePermissions::class)->revoke(UserRole::Teacher, Permission::CreateQuizzes);

    $this->actingAs($teacher)
        ->put(route('teacher.quizzes.status.update', $quiz), ['status' => 'published'])
        ->assertForbidden();

    expect($quiz->refresh()->status)->toBe(QuizStatus::Draft);
});

it('hides other teachers’ quizzes', function () {
    [, $quiz] = quizStatusScenario();

    $this->actingAs(User::factory()->teaching()->create())
        ->put(route('teacher.quizzes.status.update', $quiz), ['status' => 'published'])
        ->assertNotFound();
});

it('forbids the owner once the subject was revoked', function () {
    $quiz = Quiz::factory()->revokedSubject()->create();

    $this->actingAs($quiz->teacher)
        ->put(route('teacher.quizzes.status.update', $quiz), ['status' => 'published'])
        ->assertForbidden()
        ->assertSeeText(QuizPolicy::SUBJECT_REVOKED_MESSAGE);
});
