<?php

use App\Enums\QuestionType;
use App\Models\Classroom;
use App\Models\MissionProgress;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\QuizQuestion;
use App\Models\User;
use Illuminate\Support\Facades\Date;
use Laravel\Sanctum\Sanctum;

/**
 * Enroll a new learner in a classroom with a published quiz of three questions (4 points in all):
 * a 2-point multiple choice (answer "1"), a true/false (answer "0") and an identification ("Vitamin C|ascorbic acid").
 *
 * @param  array<string, mixed>  $quizAttributes
 * @return array{0: User, 1: Quiz, 2: list<QuizQuestion>, 3: Classroom}
 */
function gameGradedQuiz(array $quizAttributes = []): array
{
    $classroom = Classroom::factory()->create();
    $student = User::factory()->student()->enrolledIn($classroom)->create();
    $quiz = Quiz::factory()->published()->create(['subject_id' => $classroom->subject_id, 'passing_score' => 75, 'max_attempts' => 2, ...$quizAttributes]);
    $quiz->classrooms()->attach($classroom);

    $questions = [
        QuizQuestion::factory()->for($quiz)->create(['type' => QuestionType::MultipleChoice, 'choices' => ['Rice', 'Fish', 'Candy'], 'correct_answer' => '1', 'points' => 2, 'explanation' => 'Fish is rich in protein.']),
        QuizQuestion::factory()->for($quiz)->create(['type' => QuestionType::TrueFalse, 'choices' => ['True', 'False'], 'correct_answer' => '0', 'points' => 1]),
        QuizQuestion::factory()->for($quiz)->create(['type' => QuestionType::Identification, 'choices' => null, 'correct_answer' => 'Vitamin C|ascorbic acid', 'points' => 1]),
    ];

    return [$student, $quiz, $questions, $classroom];
}

it('grades the answers, stores the attempt and recalculates the learner\'s points', function () {
    Date::setTestNow('2026-11-24 10:00:00');
    [$student, $quiz, $questions, $classroom] = gameGradedQuiz();
    $student->studentProfile->forceFill(['total_points' => 9999])->save();
    MissionProgress::factory()->for($student, 'student')->create(['points_earned' => 120]);
    Sanctum::actingAs($student);

    $this->postJson(route('game.quizzes.attempts.store', $quiz), [
        'answers' => [
            ['question_id' => $questions[0]->id, 'answer' => '1'],
            ['question_id' => $questions[1]->id, 'answer' => '1'],
            ['question_id' => $questions[2]->id, 'answer' => '  vitamin c '],
        ],
        'started_at' => '2026-11-24 09:52:30',
    ])
        ->assertCreated()
        ->assertJsonPath('data.score', 3)
        ->assertJsonPath('data.max_score', 4)
        ->assertJsonPath('data.percentage', 75)
        ->assertJsonPath('data.passed', true)
        ->assertJsonPath('data.attempt_number', 1)
        ->assertJsonPath('data.attempts_left', 1)
        ->assertJsonPath('data.duration_seconds', 450)
        ->assertJsonPath('data.results.0', [
            'question_id' => $questions[0]->id,
            'answer' => '1',
            'is_correct' => true,
            'points_awarded' => 2,
            'correct_choice' => 1,
            'correct_answer_text' => 'Fish',
            'explanation' => 'Fish is rich in protein.',
        ])
        ->assertJsonPath('data.results.1.is_correct', false)
        ->assertJsonPath('data.results.2.is_correct', true)
        ->assertJsonPath('data.results.2.correct_choice', null)
        ->assertJsonPath('student.total_points', 123);

    $attempt = QuizAttempt::query()->sole();
    expect($attempt)
        ->student_id->toBe($student->id)
        ->classroom_id->toBe($classroom->id)
        ->score->toBe(3)
        ->passed->toBeTrue()
        ->is_offline->toBeFalse()
        ->submitted_at->toDateTimeString()->toBe('2026-11-24 10:00:00')
        ->and($attempt->answers()->orderBy('id')->pluck('is_correct')->all())->toBe([true, false, true])
        ->and($student->studentProfile->refresh()->total_points)->toBe(123)
        ->and($student->studentProfile->last_active_at->toDateTimeString())->toBe('2026-11-24 10:00:00');
});

it('counts unanswered questions as wrong', function () {
    [$student, $quiz] = gameGradedQuiz();
    Sanctum::actingAs($student);

    $this->postJson(route('game.quizzes.attempts.store', $quiz), ['answers' => []])
        ->assertCreated()
        ->assertJsonPath('data.score', 0)
        ->assertJsonPath('data.passed', false)
        ->assertJsonCount(3, 'data.results');

    expect(QuizAttempt::query()->sole()->answers()->whereNull('answer')->count())->toBe(3);
});

it('leaves out the correct answers when the teacher hides them', function () {
    [$student, $quiz, $questions] = gameGradedQuiz(['show_correct_answers' => false]);
    Sanctum::actingAs($student);

    $this->postJson(route('game.quizzes.attempts.store', $quiz), ['answers' => [['question_id' => $questions[0]->id, 'answer' => '0']]])
        ->assertCreated()
        ->assertJsonPath('data.results.0.is_correct', false)
        ->assertJsonMissingPath('data.results.0.correct_choice')
        ->assertJsonMissingPath('data.results.0.correct_answer_text')
        ->assertJsonMissingPath('data.results.0.explanation');
});

it('stores an attempt finished offline with the time it was submitted', function () {
    Date::setTestNow('2026-11-24 10:00:00');
    [$student, $quiz] = gameGradedQuiz();
    Sanctum::actingAs($student);

    $this->postJson(route('game.quizzes.attempts.store', $quiz), [
        'answers' => [],
        'submitted_at' => '2026-11-23 15:30:00',
        'duration_seconds' => 300,
        'is_offline' => true,
    ])->assertCreated();

    expect(QuizAttempt::query()->sole())
        ->is_offline->toBeTrue()
        ->submitted_at->toDateTimeString()->toBe('2026-11-23 15:30:00')
        ->started_at->toDateTimeString()->toBe('2026-11-23 15:25:00')
        ->synced_at->toDateTimeString()->toBe('2026-11-24 10:00:00');
});

it('counts only the best score of each quiz toward the learner\'s points', function () {
    [$student, $quiz, $questions] = gameGradedQuiz(['max_attempts' => null]);
    QuizAttempt::factory()->for($quiz)->create(['student_id' => $student->id, 'score' => 4, 'max_score' => 4]);
    Sanctum::actingAs($student);

    $this->postJson(route('game.quizzes.attempts.store', $quiz), ['answers' => [['question_id' => $questions[1]->id, 'answer' => '0']]])
        ->assertCreated()
        ->assertJsonPath('data.score', 1)
        ->assertJsonPath('data.attempt_number', 2)
        ->assertJsonPath('data.attempts_left', null)
        ->assertJsonPath('student.total_points', 4);
});

it('refuses another attempt once the learner used every attempt', function () {
    [$student, $quiz] = gameGradedQuiz(['max_attempts' => 1]);
    QuizAttempt::factory()->for($quiz)->create(['student_id' => $student->id]);
    Sanctum::actingAs($student);

    $this->postJson(route('game.quizzes.attempts.store', $quiz), ['answers' => []])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['quiz' => 'You have used all 1 attempts for this quiz.']);

    expect(QuizAttempt::query()->count())->toBe(1);
});

it('rejects invalid answers', function (Closure $payload, string $field, string $message) {
    [$student, $quiz, $questions] = gameGradedQuiz();
    Sanctum::actingAs($student);

    $this->postJson(route('game.quizzes.attempts.store', $quiz), $payload($questions))
        ->assertUnprocessable()
        ->assertJsonValidationErrors([$field => $message]);

    expect(QuizAttempt::query()->count())->toBe(0);
})->with([
    'no answers list' => [fn (array $questions): array => [], 'answers', 'The answers field must be present.'],
    'a question of another quiz' => [fn (array $questions): array => ['answers' => [['question_id' => QuizQuestion::factory()->create()->id, 'answer' => '0']]], 'answers.0.question_id', 'This question is not part of the quiz.'],
    'the same question twice' => [fn (array $questions): array => ['answers' => [['question_id' => $questions[0]->id, 'answer' => '0'], ['question_id' => $questions[0]->id, 'answer' => '1']]], 'answers.0.question_id', 'Each question can be answered only once.'],
    'a submit time in the future' => [fn (array $questions): array => ['answers' => [], 'submitted_at' => now()->addHour()->toDateTimeString()], 'submitted_at', 'The submit time field must be a date before or equal to now.'],
]);

it('answers 404 for a quiz the learner may not take', function () {
    [, $quiz] = gameGradedQuiz();
    Sanctum::actingAs(User::factory()->student()->create());

    $this->postJson(route('game.quizzes.attempts.store', $quiz), ['answers' => []])->assertNotFound();

    expect(QuizAttempt::query()->count())->toBe(0);
});
