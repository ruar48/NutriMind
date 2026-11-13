<?php

use App\Enums\GradeLevel;
use App\Enums\QuizStatus;
use App\Models\LearningModule;
use App\Models\Quiz;

use function Pest\Laravel\freezeSecond;

it('creates an unreviewed draft with the default passing score and two attempts', function () {
    $quiz = Quiz::factory()->create()->fresh();

    expect($quiz->status)->toBe(QuizStatus::Draft);
    expect($quiz->reviewed_at)->toBeNull();
    expect($quiz->published_at)->toBeNull();
    expect($quiz->passing_score)->toBe(75);
    expect($quiz->max_attempts)->toBe(2);
});

it('creates reviewed and published quizzes', function () {
    freezeSecond();

    $quiz = Quiz::factory()->published()->create();

    expect($quiz->status)->toBe(QuizStatus::Published);
    expect($quiz->reviewed_at->equalTo(now()))->toBeTrue();
    expect($quiz->published_at->equalTo(now()))->toBeTrue();
});

it('creates closed quizzes that were published before', function () {
    $quiz = Quiz::factory()->closed()->create();

    expect($quiz->status)->toBe(QuizStatus::Closed);
    expect($quiz->isReviewed())->toBeTrue();
    expect($quiz->published_at)->not->toBeNull();
});

it('creates reviewed drafts', function () {
    $quiz = Quiz::factory()->reviewed()->create();

    expect($quiz->status)->toBe(QuizStatus::Draft);
    expect($quiz->isReviewed())->toBeTrue();
});

it('takes the subject and grade level of its learning module', function () {
    $module = LearningModule::factory()->create(['grade_level' => GradeLevel::Six]);

    $quiz = Quiz::factory()->for($module, 'module')->create();

    expect($quiz->subject_id)->toBe($module->subject_id);
    expect($quiz->grade_level)->toBe(GradeLevel::Six);
});
