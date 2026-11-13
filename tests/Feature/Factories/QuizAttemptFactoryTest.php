<?php

use App\Enums\PerformanceBand;
use App\Models\Classroom;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\QuizQuestion;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Sequence;

use function Pest\Laravel\freezeSecond;

it('submits an attempt at a published quiz by a learner', function () {
    $attempt = QuizAttempt::factory()->create();

    expect($attempt->quiz->isPublished())->toBeTrue();
    expect($attempt->student->isStudent())->toBeTrue();
    expect($attempt->submitted_at)->not->toBeNull();
    expect($attempt->is_offline)->toBeFalse();
});

it('derives the percentage and the outcome from the score', function () {
    $attempt = QuizAttempt::factory()->create(['score' => 2, 'max_score' => 3])->fresh();

    expect($attempt->percentage)->toBe('66.67');
    expect($attempt->passed)->toBeFalse();
});

it('keeps every generated score within the maximum and consistent with the outcome', function () {
    $quiz = Quiz::factory()->published()->create(['passing_score' => 75]);

    $attempts = QuizAttempt::factory()->count(20)->for($quiz)->create();

    $attempts->each(function (QuizAttempt $attempt): void {
        $expectedPercentage = round($attempt->score * 100 / $attempt->max_score, 2);

        expect($attempt->score)->toBeLessThanOrEqual($attempt->max_score);
        expect((float) $attempt->percentage)->toBe($expectedPercentage);
        expect($attempt->passed)->toBe($expectedPercentage >= 75);
    });
});

it('uses the total question points of the quiz as the maximum score', function () {
    $quiz = Quiz::factory()->published()->create();
    QuizQuestion::factory()->count(3)->for($quiz)->state(new Sequence(['points' => 1], ['points' => 2], ['points' => 3]))->create();

    expect(QuizAttempt::factory()->for($quiz)->create()->max_score)->toBe(6);
});

it('creates passing attempts', function (int $passingScore) {
    $quiz = Quiz::factory()->published()->create(['passing_score' => $passingScore]);

    $attempts = QuizAttempt::factory()->count(5)->passed()->for($quiz)->create();

    $attempts->each(function (QuizAttempt $attempt) use ($passingScore): void {
        expect($attempt->passed)->toBeTrue();
        expect((float) $attempt->percentage)->toBeGreaterThanOrEqual($passingScore);
    });
})->with([
    'default passing score' => 75,
    'perfect score required' => 100,
]);

it('creates failing attempts', function (int $passingScore) {
    $quiz = Quiz::factory()->published()->create(['passing_score' => $passingScore]);

    $attempts = QuizAttempt::factory()->count(5)->failed()->for($quiz)->create();

    $attempts->each(function (QuizAttempt $attempt) use ($passingScore): void {
        expect($attempt->passed)->toBeFalse();
        expect((float) $attempt->percentage)->toBeLessThan($passingScore);
        expect($attempt->band())->toBe(PerformanceBand::fromScore((float) $attempt->percentage));
    });
})->with([
    'default passing score' => 75,
    'low passing score' => 10,
]);

it('refuses to fail a quiz that every score passes', function () {
    $quiz = Quiz::factory()->published()->create(['passing_score' => 0]);

    expect(fn () => QuizAttempt::factory()->failed()->for($quiz)->create())->toThrow(LogicException::class);
});

it('fits the duration within the quiz time limit and starts the attempt that long before submitting it', function () {
    $quiz = Quiz::factory()->published()->create(['time_limit_minutes' => 1]);

    $attempt = QuizAttempt::factory()->for($quiz)->create();

    expect($attempt->duration_seconds)->toBeLessThanOrEqual(60);
    expect($attempt->started_at->toDateTimeString())->toBe($attempt->submitted_at->copy()->subSeconds($attempt->duration_seconds)->toDateTimeString());
});

it('creates offline attempts that synced after they were submitted', function () {
    freezeSecond();

    $attempt = QuizAttempt::factory()->offline()->create();

    expect($attempt->is_offline)->toBeTrue();
    expect($attempt->submitted_at->lessThan(now()))->toBeTrue();
    expect($attempt->synced_at->equalTo(now()))->toBeTrue();
});

it('creates attempts that are not submitted yet', function () {
    $attempt = QuizAttempt::factory()->inProgress()->create();

    expect($attempt->submitted_at)->toBeNull();
    expect($attempt->synced_at)->toBeNull();
    expect($attempt->duration_seconds)->toBeNull();
    expect($attempt->started_at)->not->toBeNull();
    expect($attempt->band())->toBe(PerformanceBand::NoData);
});

it('enrolls the learner and assigns the quiz when the attempt is made in a classroom', function () {
    $classroom = Classroom::factory()->create();

    $attempt = QuizAttempt::factory()->for($classroom)->create();

    $this->assertDatabaseHas('classroom_student', ['classroom_id' => $classroom->id, 'student_id' => $attempt->student_id]);
    $this->assertDatabaseHas('classroom_quiz', ['classroom_id' => $classroom->id, 'quiz_id' => $attempt->quiz_id]);
});

it('keeps an existing enrollment when the attempt is made in the classroom', function () {
    $classroom = Classroom::factory()->create();
    $student = User::factory()->student()->create();
    $classroom->students()->attach($student, ['enrolled_at' => '2026-06-16 07:30:00']);

    QuizAttempt::factory()->for($classroom)->for($student, 'student')->create();

    $this->assertDatabaseCount('classroom_student', 1);
    expect($classroom->students()->sole()->pivot->enrolled_at->toDateTimeString())->toBe('2026-06-16 07:30:00');
});

it('keeps an existing quiz assignment when the attempt is made in the classroom', function () {
    $classroom = Classroom::factory()->create();
    $quiz = Quiz::factory()->published()->create();
    $classroom->quizzes()->attach($quiz, ['due_at' => '2026-11-20 17:00:00']);

    QuizAttempt::factory()->for($classroom)->for($quiz)->create();

    $this->assertDatabaseCount('classroom_quiz', 1);
    expect($classroom->quizzes()->sole()->pivot->due_at->toDateTimeString())->toBe('2026-11-20 17:00:00');
});
