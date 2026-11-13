<?php

use App\Models\Quiz;
use App\Models\QuizQuestion;

it('numbers the questions of a quiz in the order they are created', function () {
    $quiz = Quiz::factory()->create();

    $questions = QuizQuestion::factory()->count(3)->for($quiz)->create();

    expect($questions->pluck('sort_order')->all())->toBe([1, 2, 3]);
});

it('places new questions after the existing questions of the quiz', function () {
    $quiz = Quiz::factory()->create();
    QuizQuestion::factory()->for($quiz)->create(['sort_order' => 5]);

    expect(QuizQuestion::factory()->for($quiz)->create()->sort_order)->toBe(6);
});

it('creates one-point questions without media', function () {
    $question = QuizQuestion::factory()->create();

    expect($question->points)->toBe(1);
    expect($question->hasMedia())->toBeFalse();
});
