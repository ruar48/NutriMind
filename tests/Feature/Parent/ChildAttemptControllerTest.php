<?php

use App\Enums\GradeLevel;
use App\Enums\Permission;
use App\Enums\UserRole;
use App\Models\Classroom;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\QuizAttemptAnswer;
use App\Models\QuizQuestion;
use App\Models\User;
use App\Services\RolePermissions;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Create a published quiz with one question of each type and a learner's submitted attempt at it.
 *
 * The learner chose the right food group (correct), answered the true-or-false question wrongly and left the
 * identification question blank.
 *
 * @param  array<string, mixed>  $quizAttributes
 * @return array{student: User, quiz: Quiz, attempt: QuizAttempt, questions: array{choice: QuizQuestion, trueFalse: QuizQuestion, identification: QuizQuestion}}
 */
function attemptPageScenario(array $quizAttributes = []): array
{
    $student = User::factory()->student(GradeLevel::Five)->create(['name' => 'Sofia Navarro']);
    $quiz = Quiz::factory()->published()->create(['title' => 'Healthy Plate Quiz', 'passing_score' => 75, ...$quizAttributes]);

    $choice = QuizQuestion::factory()->multipleChoice()->for($quiz)->create([
        'prompt' => 'Which food group gives us energy?',
        'choices' => ['Go foods', 'Grow foods', 'Glow foods'],
        'correct_answer' => '0',
        'explanation' => 'Go foods such as rice give us energy.',
        'points' => 2,
    ]);
    $trueFalse = QuizQuestion::factory()->trueFalse()->for($quiz)->create([
        'prompt' => 'Soft drinks are a healthy daily snack.',
        'correct_answer' => '1',
        'explanation' => 'Soft drinks are high in sugar.',
        'points' => 1,
    ]);
    $identification = QuizQuestion::factory()->identification()->for($quiz)->create([
        'prompt' => 'Name a vegetable rich in vitamin A.',
        'correct_answer' => 'Carrot|Squash',
        'explanation' => null,
        'points' => 1,
    ]);

    $attempt = QuizAttempt::factory()->for($quiz)->for($student, 'student')->create([
        'score' => 2,
        'max_score' => 4,
        'percentage' => 50,
        'passed' => false,
        'duration_seconds' => 425,
        'submitted_at' => now()->subDay(),
    ]);
    QuizAttemptAnswer::factory()->for($attempt, 'attempt')->for($choice, 'question')->create(['answer' => '0', 'is_correct' => true, 'points_awarded' => 2]);
    QuizAttemptAnswer::factory()->for($attempt, 'attempt')->for($trueFalse, 'question')->create(['answer' => '0', 'is_correct' => false, 'points_awarded' => 0]);

    return [
        'student' => $student,
        'quiz' => $quiz,
        'attempt' => $attempt,
        'questions' => ['choice' => $choice, 'trueFalse' => $trueFalse, 'identification' => $identification],
    ];
}

it('sends guests to the sign-in page', function () {
    ['student' => $student, 'attempt' => $attempt] = attemptPageScenario();

    $this->get(route('parent.children.attempts.show', [$student, $attempt]))->assertRedirectToRoute('login');
});

it('forbids accounts of the other portals', function (UserRole $role) {
    ['student' => $student, 'attempt' => $attempt] = attemptPageScenario();

    $this->actingAs(User::factory()->{$role->value}()->create())
        ->get(route('parent.children.attempts.show', [$student, $attempt]))
        ->assertForbidden();
})->with([
    'administrator' => UserRole::Admin,
    'teacher' => UserRole::Teacher,
    'learner' => UserRole::Student,
]);

it('reports an attempt of a learner who is not linked to the parent as missing', function () {
    ['student' => $student, 'attempt' => $attempt] = attemptPageScenario();
    $parent = User::factory()->parent()->create();

    $this->actingAs($parent)
        ->get(route('parent.children.attempts.show', [$student, $attempt]))
        ->assertNotFound();
});

it('reports an unlinked learner as missing even without the quiz details permission', function () {
    ['student' => $student, 'attempt' => $attempt] = attemptPageScenario();
    $parent = User::factory()->parent()->create();
    app(RolePermissions::class)->revoke(UserRole::Parent, Permission::ViewQuizDetails);

    $this->actingAs($parent)
        ->get(route('parent.children.attempts.show', [$student, $attempt]))
        ->assertNotFound();
});

it('reports another learner’s attempt as missing under the parent’s own child', function () {
    ['attempt' => $attempt] = attemptPageScenario();
    $child = User::factory()->student()->create();
    $parent = User::factory()->parent()->guardianOf($child)->create();

    $this->actingAs($parent)
        ->get(route('parent.children.attempts.show', [$child, $attempt]))
        ->assertNotFound();
});

it('reports an attempt that was never submitted as missing', function () {
    ['student' => $student, 'quiz' => $quiz] = attemptPageScenario();
    $parent = User::factory()->parent()->guardianOf($student)->create();
    $unfinished = QuizAttempt::factory()->inProgress()->for($quiz)->for($student, 'student')->create(['attempt_number' => 2]);

    $this->actingAs($parent)
        ->get(route('parent.children.attempts.show', [$student, $unfinished]))
        ->assertNotFound();
});

it('reports an unknown attempt as missing', function () {
    ['student' => $student] = attemptPageScenario();
    $parent = User::factory()->parent()->guardianOf($student)->create();

    $this->actingAs($parent)
        ->get(route('parent.children.attempts.show', [$student, 999999]))
        ->assertNotFound();
});

it('forbids parents without the view quiz details permission', function () {
    ['student' => $student, 'attempt' => $attempt] = attemptPageScenario();
    $parent = User::factory()->parent()->guardianOf($student)->create();
    app(RolePermissions::class)->revoke(UserRole::Parent, Permission::ViewQuizDetails);

    $this->actingAs($parent)
        ->get(route('parent.children.attempts.show', [$student, $attempt]))
        ->assertForbidden()
        ->assertSeeText('You do not have permission to view quiz details.');
});

it('forbids parents without the view child progress permission', function () {
    ['student' => $student, 'attempt' => $attempt] = attemptPageScenario();
    $parent = User::factory()->parent()->guardianOf($student)->create();
    app(RolePermissions::class)->revoke(UserRole::Parent, Permission::ViewChildProgress);

    $this->actingAs($parent)
        ->get(route('parent.children.attempts.show', [$student, $attempt]))
        ->assertForbidden()
        ->assertSeeText('You do not have permission to view child progress.');
});

it('shows each answer with the correct answers when the quiz reveals them', function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-13 10:00:00'));
    ['student' => $student, 'attempt' => $attempt, 'questions' => $questions] = attemptPageScenario(['show_correct_answers' => true]);
    $parent = User::factory()->parent()->guardianOf($student)->create();

    $response = $this->actingAs($parent)->get(route('parent.children.attempts.show', [$student, $attempt]));

    $response->assertOk()
        ->assertViewIs('parent.children.attempts.show')
        ->assertSeeInOrder([
            'Which food group gives us energy?',
            'Soft drinks are a healthy daily snack.',
            'Name a vegetable rich in vitamin A.',
        ])
        ->assertSee('data-answer-review="correct"', false)
        ->assertSee('data-answer-review="incorrect"', false)
        ->assertSee('data-answer-review="unanswered"', false)
        ->assertSee('data-correct-choice', false)
        ->assertSeeText('Your child\'s answer')
        ->assertSeeText('Go foods such as rice give us energy.')
        ->assertSeeText('Carrot')
        ->assertSeeText('1 of 3 questions answered correctly')
        ->assertSeeText('2 / 4')
        ->assertSeeText('7:05')
        ->assertSeeText('Nov 12, 2026 10:00 AM')
        ->assertDontSee('data-answers-hidden', false);
    $response->assertViewHas('answersByQuestion', fn ($answers): bool => $answers->keys()->sort()->values()->all() === [$questions['choice']->id, $questions['trueFalse']->id]);
});

it('shows only correctness marks when the quiz hides the correct answers', function () {
    ['student' => $student, 'attempt' => $attempt] = attemptPageScenario(['show_correct_answers' => false]);
    $parent = User::factory()->parent()->guardianOf($student)->create();

    $this->actingAs($parent)
        ->get(route('parent.children.attempts.show', [$student, $attempt]))
        ->assertOk()
        ->assertSee('data-answers-hidden', false)
        ->assertSeeText('Correct answers are hidden for this quiz')
        ->assertSee('data-answer-review="correct"', false)
        ->assertSee('data-answer-review="incorrect"', false)
        ->assertDontSee('data-correct-choice', false)
        ->assertDontSee('data-answer-review-explanation', false)
        ->assertDontSee('data-answer-review-accepted', false)
        ->assertDontSeeText('Go foods such as rice give us energy.');
});

it('shows the offline sync time of an attempt played offline', function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-13 10:00:00'));
    ['student' => $student, 'attempt' => $attempt] = attemptPageScenario();
    $attempt->update(['is_offline' => true, 'submitted_at' => '2026-11-11 19:30:00', 'synced_at' => '2026-11-12 07:15:00']);
    $parent = User::factory()->parent()->guardianOf($student)->create();

    $this->actingAs($parent)
        ->get(route('parent.children.attempts.show', [$student, $attempt]))
        ->assertOk()
        ->assertSeeText('Played offline')
        ->assertSeeInOrder(['Synced', 'Nov 12, 2026 7:15 AM']);
});

it('lists every submitted attempt at the quiz and links the other ones', function () {
    ['student' => $student, 'quiz' => $quiz, 'attempt' => $attempt] = attemptPageScenario();
    $parent = User::factory()->parent()->guardianOf($student)->create();
    $retake = QuizAttempt::factory()->for($quiz)->for($student, 'student')->create(['attempt_number' => 2, 'percentage' => 100, 'passed' => true]);
    QuizAttempt::factory()->inProgress()->for($quiz)->for($student, 'student')->create(['attempt_number' => 3]);
    QuizAttempt::factory()->for($quiz)->create(['attempt_number' => 1]);

    $this->actingAs($parent)
        ->get(route('parent.children.attempts.show', [$student, $attempt]))
        ->assertOk()
        ->assertSeeInOrder(['Attempt 1', '(this one)', 'Attempt 2'])
        ->assertSee('href="'.route('parent.children.attempts.show', [$student, $retake]).'"', false)
        ->assertViewHas('otherAttempts', fn ($attempts): bool => $attempts->modelKeys() === [$attempt->id, $retake->id]);
});

it('names the classroom the attempt was made in', function () {
    ['student' => $student, 'attempt' => $attempt] = attemptPageScenario();
    $classroom = Classroom::factory()->create(['name' => 'Health 5 – Sampaguita']);
    $attempt->update(['classroom_id' => $classroom->id]);
    $parent = User::factory()->parent()->guardianOf($student)->create();

    $this->actingAs($parent)
        ->get(route('parent.children.attempts.show', [$student, $attempt]))
        ->assertOk()
        ->assertSeeInOrder(['Class', 'Health 5 – Sampaguita']);
});

it('escapes question and answer text', function () {
    ['student' => $student, 'attempt' => $attempt, 'questions' => $questions] = attemptPageScenario();
    $questions['identification']->update(['prompt' => '<script>alert("q")</script>']);
    $parent = User::factory()->parent()->guardianOf($student)->create();

    $this->actingAs($parent)
        ->get(route('parent.children.attempts.show', [$student, $attempt]))
        ->assertOk()
        ->assertSee('&lt;script&gt;alert(&quot;q&quot;)&lt;/script&gt;', false)
        ->assertDontSee('<script>alert("q")</script>', false);
});

it('runs the same number of queries however many questions the quiz has', function () {
    ['student' => $student, 'quiz' => $quiz, 'attempt' => $attempt] = attemptPageScenario();
    $parent = User::factory()->parent()->guardianOf($student)->create();
    $countQueries = function () use ($parent, $student, $attempt): int {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->actingAs($parent)->get(route('parent.children.attempts.show', [$student, $attempt]))->assertOk();
        DB::disableQueryLog();

        return count(DB::getQueryLog());
    };

    $before = $countQueries();
    $questions = QuizQuestion::factory()->count(5)->for($quiz)->create();
    foreach ($questions as $question) {
        QuizAttemptAnswer::factory()->correct()->for($attempt, 'attempt')->for($question, 'question')->create();
    }
    QuizAttempt::factory()->count(2)->for($quiz)->for($student, 'student')->create();

    expect($countQueries())->toBe($before);
});
