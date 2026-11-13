<?php

use App\Enums\GradeLevel;
use App\Enums\Permission;
use App\Enums\QuestionType;
use App\Enums\UserRole;
use App\Models\Classroom;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\QuizAttemptAnswer;
use App\Models\QuizQuestion;
use App\Models\Subject;
use App\Models\User;
use App\Policies\QuizPolicy;
use App\Services\RolePermissions;

/**
 * A learner's second attempt (50%) at a two-question quiz: the multiple choice question answered wrongly, the
 * identification question answered correctly; the first attempt scored 0.
 *
 * @return array{teacher: User, subject: Subject, classroom: Classroom, quiz: Quiz, student: User, attempt: QuizAttempt, first: QuizAttempt}
 */
function quizAttemptPageSetup(): array
{
    $subject = Subject::factory()->create();
    $teacher = User::factory()->teaching($subject)->create();
    $classroom = Classroom::factory()->for($teacher, 'teacher')->for($subject)->create(['grade_level' => GradeLevel::Five, 'name' => 'Health 5 – Sampaguita']);
    $quiz = Quiz::factory()->published()->for($teacher, 'teacher')->for($subject)->create(['title' => 'Go Grow Glow Quiz', 'passing_score' => 75]);
    $choice = QuizQuestion::factory()->for($quiz)->create([
        'type' => QuestionType::MultipleChoice,
        'prompt' => 'Which food group helps you grow?',
        'choices' => ['Go', 'Grow', 'Glow'],
        'correct_answer' => '1',
        'sort_order' => 1,
        'explanation' => 'Grow foods build muscles.',
    ]);
    $typed = QuizQuestion::factory()->for($quiz)->create([
        'type' => QuestionType::Identification,
        'prompt' => 'What drink keeps you hydrated?',
        'choices' => null,
        'correct_answer' => 'water|tubig',
        'sort_order' => 2,
    ]);
    $student = User::factory()->student(GradeLevel::Five)->enrolledIn($classroom)->create(['name' => 'Ana Cruz']);
    $first = QuizAttempt::factory()->for($quiz)->for($student, 'student')->for($classroom)->create([
        'attempt_number' => 1, 'score' => 0, 'max_score' => 2, 'percentage' => 0, 'passed' => false, 'submitted_at' => now()->subDay(),
    ]);
    $attempt = QuizAttempt::factory()->for($quiz)->for($student, 'student')->for($classroom)->create([
        'attempt_number' => 2, 'score' => 1, 'max_score' => 2, 'percentage' => 50, 'passed' => false, 'duration_seconds' => 125,
    ]);
    QuizAttemptAnswer::factory()->for($attempt, 'attempt')->for($choice, 'question')->create(['answer' => '2', 'is_correct' => false, 'points_awarded' => 0]);
    QuizAttemptAnswer::factory()->for($attempt, 'attempt')->for($typed, 'question')->create(['answer' => 'Water', 'is_correct' => true, 'points_awarded' => 1]);

    return compact('teacher', 'subject', 'classroom', 'quiz', 'student', 'attempt', 'first');
}

it('sends guests to the sign-in page', function () {
    $this->get(route('teacher.attempts.show', QuizAttempt::factory()->create()))->assertRedirectToRoute('login');
});

it('forbids other roles', function (UserRole $role) {
    $this->actingAs(User::factory()->{$role->value}()->create())
        ->get(route('teacher.attempts.show', QuizAttempt::factory()->create()))
        ->assertForbidden();
})->with([
    'administrator' => UserRole::Admin,
    'parent' => UserRole::Parent,
]);

it('reports an attempt at another teacher’s quiz as missing', function () {
    $data = quizAttemptPageSetup();

    $this->actingAs(User::factory()->teaching($data['subject'])->create())
        ->get(route('teacher.attempts.show', $data['attempt']))
        ->assertNotFound();
});

it('reports an unknown attempt as missing', function () {
    $data = quizAttemptPageSetup();

    $this->actingAs($data['teacher'])
        ->get(route('teacher.attempts.show', 999999))
        ->assertNotFound();
});

it('refuses an attempt at a quiz whose subject was revoked', function () {
    $data = quizAttemptPageSetup();
    $data['teacher']->assignedSubjects()->detach($data['subject']);

    $this->actingAs($data['teacher']->refresh())
        ->get(route('teacher.attempts.show', $data['attempt']))
        ->assertForbidden()
        ->assertSeeText(QuizPolicy::SUBJECT_REVOKED_MESSAGE);
});

it('reviews every answer in question order', function () {
    $data = quizAttemptPageSetup();

    $response = $this->actingAs($data['teacher'])->get(route('teacher.attempts.show', $data['attempt']));

    $response->assertOk()
        ->assertViewIs('teacher.attempts.show')
        ->assertSeeTextInOrder(['Which food group helps you grow?', 'What drink keeps you hydrated?'])
        ->assertSee('data-answer-review="incorrect"', false)
        ->assertSee('data-answer-review="correct"', false)
        ->assertSee('data-correct-choice', false)
        ->assertSeeText('Grow foods build muscles.')
        ->assertSeeText('1 / 2')
        ->assertSeeText('1 of 2 questions correct')
        ->assertSeeText('2:05')
        ->assertSeeText('Did Not Meet Expectations')
        ->assertSeeText('Not passed');
});

it('lists the learner’s other attempts at the quiz', function () {
    $data = quizAttemptPageSetup();

    $this->actingAs($data['teacher'])
        ->get(route('teacher.attempts.show', $data['attempt']))
        ->assertSeeText('Attempt 1')
        ->assertSee(route('teacher.attempts.show', $data['first']), false);
});

it('offers feedback on the attempt', function () {
    $data = quizAttemptPageSetup();

    $this->actingAs($data['teacher'])
        ->get(route('teacher.attempts.show', $data['attempt']))
        ->assertSee(e(route('teacher.feedback.create', ['student' => $data['student']->id, 'attempt' => $data['attempt']->id])), false)
        ->assertSee(route('teacher.students.show', $data['student']), false);
});

it('hides the feedback action without the give feedback permission', function () {
    $data = quizAttemptPageSetup();
    app(RolePermissions::class)->revoke(UserRole::Teacher, Permission::GiveFeedback);

    $this->actingAs($data['teacher'])
        ->get(route('teacher.attempts.show', $data['attempt']))
        ->assertOk()
        ->assertDontSeeText('Give feedback on this attempt');
});

it('still shows the attempt after the learner left the teacher’s classrooms', function () {
    $data = quizAttemptPageSetup();
    $data['classroom']->students()->detach($data['student']);

    $this->actingAs($data['teacher'])
        ->get(route('teacher.attempts.show', $data['attempt']))
        ->assertOk()
        ->assertDontSee(route('teacher.students.show', $data['student']), false)
        ->assertDontSeeText('Give feedback on this attempt');
});

it('shows an attempt that is not submitted yet', function () {
    $data = quizAttemptPageSetup();
    $inProgress = QuizAttempt::factory()->inProgress()->for($data['quiz'])->for($data['student'], 'student')->for($data['classroom'])->create(['attempt_number' => 3]);

    $this->actingAs($data['teacher'])
        ->get(route('teacher.attempts.show', $inProgress))
        ->assertOk()
        ->assertSeeText('Not submitted yet')
        ->assertDontSeeText('Give feedback on this attempt');
});
