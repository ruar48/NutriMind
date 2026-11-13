<?php

use App\Enums\QuestionType;
use App\Models\QuestionBankItem;
use App\Models\QuizQuestion;

dataset('question factories', [
    'quiz question' => QuizQuestion::class,
    'question bank item' => QuestionBankItem::class,
]);

it('creates multiple choice questions by default with two to six choices and a valid answer index', function (string $model) {
    $questions = $model::factory()->count(10)->create();

    $questions->each(function ($question): void {
        expect($question->type)->toBe(QuestionType::MultipleChoice);
        expect(count($question->choices))->toBeGreaterThanOrEqual(2)->toBeLessThanOrEqual(6);
        expect($question->choices)->toHaveKey((int) $question->correct_answer);
        expect($question->correct_answer)->toMatch('/^[0-5]$/');
    });
})->with('question factories');

it('creates multiple choice questions whose stored answer is the correct choice', function (string $model) {
    $question = $model::factory()->multipleChoice()->create();

    expect($question->isCorrectAnswer($question->correct_answer))->toBeTrue();
    expect($question->correctAnswerText())->toBe($question->choices[(int) $question->correct_answer]);
})->with('question factories');

it('creates true or false questions with the True and False choices', function (string $model) {
    $question = $model::factory()->trueFalse()->create();

    expect($question->type)->toBe(QuestionType::TrueFalse);
    expect($question->choices)->toBe(['True', 'False']);
    expect($question->correct_answer)->toBeIn(['0', '1']);
})->with('question factories');

it('creates identification questions without choices and with accepted answers', function (string $model) {
    $question = $model::factory()->identification()->create();

    expect($question->type)->toBe(QuestionType::Identification);
    expect($question->choices)->toBeNull();
    expect($question->acceptedAnswers())->not->toBeEmpty();
    expect($question->isCorrectAnswer($question->acceptedAnswers()[0]))->toBeTrue();
})->with('question factories');
