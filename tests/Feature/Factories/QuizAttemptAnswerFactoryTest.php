<?php

use App\Models\QuizAttempt;
use App\Models\QuizAttemptAnswer;
use App\Models\QuizQuestion;

dataset('question types', [
    'multiple choice' => 'multipleChoice',
    'true or false' => 'trueFalse',
    'identification' => 'identification',
]);

it('answers a question of the attempt quiz', function () {
    $attempt = QuizAttempt::factory()->create();

    $answer = QuizAttemptAnswer::factory()->for($attempt, 'attempt')->create();

    expect($answer->question->quiz_id)->toBe($attempt->quiz_id);
});

it('creates correct answers that earn the question points', function (string $type) {
    $question = QuizQuestion::factory()->{$type}()->create(['points' => 3]);

    $answer = QuizAttemptAnswer::factory()->correct()->for($question, 'question')->create();

    expect($question->isCorrectAnswer($answer->answer))->toBeTrue();
    expect($answer->is_correct)->toBeTrue();
    expect($answer->points_awarded)->toBe(3);
})->with('question types');

it('creates incorrect answers that earn no points', function (string $type) {
    $question = QuizQuestion::factory()->{$type}()->create(['points' => 3]);

    $answer = QuizAttemptAnswer::factory()->incorrect()->for($question, 'question')->create();

    expect($answer->answer)->not->toBeNull();
    expect($question->isCorrectAnswer($answer->answer))->toBeFalse();
    expect($answer->is_correct)->toBeFalse();
    expect($answer->points_awarded)->toBe(0);
})->with('question types');

it('marks generated answers correct exactly when the question accepts them', function () {
    $question = QuizQuestion::factory()->create(['points' => 2]);

    $answers = QuizAttemptAnswer::factory()->count(10)->for($question, 'question')->create();

    $answers->each(function (QuizAttemptAnswer $answer) use ($question): void {
        expect($answer->is_correct)->toBe($question->isCorrectAnswer($answer->answer));
        expect($answer->points_awarded)->toBe($answer->is_correct ? 2 : 0);
    });
});
