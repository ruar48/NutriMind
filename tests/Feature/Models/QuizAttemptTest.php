<?php

use App\Enums\PerformanceBand;
use App\Models\QuizAttempt;
use App\Models\QuizAttemptAnswer;

it('bands a submitted attempt by its percentage', function (float $percentage, PerformanceBand $band) {
    $attempt = new QuizAttempt(['percentage' => $percentage, 'submitted_at' => now()]);

    expect($attempt->band())->toBe($band);
})->with([
    'outstanding' => [92.5, PerformanceBand::Outstanding],
    'fairly satisfactory' => [75.0, PerformanceBand::FairlySatisfactory],
    'did not meet expectations' => [74.99, PerformanceBand::DidNotMeetExpectations],
]);

it('has no band until the attempt is submitted', function () {
    $attempt = QuizAttempt::factory()->inProgress()->create();

    expect($attempt->band())->toBe(PerformanceBand::NoData);
});

it('filters submitted attempts', function () {
    $submittedAttempt = QuizAttempt::factory()->create();
    QuizAttempt::factory()->inProgress()->create();

    expect(QuizAttempt::query()->submitted()->pluck('id')->all())->toBe([$submittedAttempt->id]);
});

it('stores the percentage with two decimals', function () {
    $attempt = QuizAttempt::factory()->create(['score' => 2, 'max_score' => 3])->fresh();

    expect($attempt->percentage)->toBe('66.67');
});

it('reaches its quiz, learner, classroom and answers', function () {
    $answer = QuizAttemptAnswer::factory()->create();
    $attempt = $answer->attempt;

    expect($attempt->quiz->id)->toBe($attempt->quiz_id);
    expect($attempt->student->id)->toBe($attempt->student_id);
    expect($attempt->classroom)->toBeNull();
    expect($attempt->answers()->sole()->is($answer))->toBeTrue();
    expect($answer->question->quiz_id)->toBe($attempt->quiz_id);
});
