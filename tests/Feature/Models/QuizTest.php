<?php

use App\Enums\QuizStatus;
use App\Models\Classroom;
use App\Models\LessonPlan;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\QuizQuestion;

it('reports its status through the status helpers', function (QuizStatus $status, array $answers) {
    $quiz = new Quiz(['status' => $status]);

    expect([
        'draft' => $quiz->isDraft(),
        'published' => $quiz->isPublished(),
        'closed' => $quiz->isClosed(),
    ])->toBe($answers);
})->with([
    'draft' => [QuizStatus::Draft, ['draft' => true, 'published' => false, 'closed' => false]],
    'published' => [QuizStatus::Published, ['draft' => false, 'published' => true, 'closed' => false]],
    'closed' => [QuizStatus::Closed, ['draft' => false, 'published' => false, 'closed' => true]],
]);

it('starts as an unreviewed draft', function () {
    $quiz = new Quiz;

    expect($quiz->isDraft())->toBeTrue();
    expect($quiz->isReviewed())->toBeFalse();
});

it('counts as reviewed once a review time is recorded', function () {
    expect(Quiz::factory()->reviewed()->create()->isReviewed())->toBeTrue();
});

it('filters published quizzes', function () {
    $publishedQuiz = Quiz::factory()->published()->create();
    Quiz::factory()->create();
    Quiz::factory()->closed()->create();

    expect(Quiz::query()->published()->pluck('id')->all())->toBe([$publishedQuiz->id]);
});

it('filters quizzes by status', function (QuizStatus $status) {
    $quizzes = [
        QuizStatus::Draft->value => Quiz::factory()->create(),
        QuizStatus::Published->value => Quiz::factory()->published()->create(),
        QuizStatus::Closed->value => Quiz::factory()->closed()->create(),
    ];

    expect(Quiz::query()->ofStatus($status)->pluck('id')->all())->toBe([$quizzes[$status->value]->id]);
})->with(QuizStatus::cases());

it('lists its questions by sort order, then by creation order', function () {
    $quiz = Quiz::factory()->create();
    $third = QuizQuestion::factory()->for($quiz)->create(['sort_order' => 5]);
    $first = QuizQuestion::factory()->for($quiz)->create(['sort_order' => 1]);
    $second = QuizQuestion::factory()->for($quiz)->create(['sort_order' => 1]);

    expect($quiz->questions->modelKeys())->toBe([$first->id, $second->id, $third->id]);
});

it('reaches its classrooms, attempts and aligned lesson plans', function () {
    $quiz = Quiz::factory()->published()->create();
    $classroom = Classroom::factory()->create();
    $quiz->classrooms()->attach($classroom);
    $attempt = QuizAttempt::factory()->for($quiz)->create();
    $lessonPlan = LessonPlan::factory()->create();
    $lessonPlan->quizzes()->attach($quiz);

    expect($quiz->classrooms()->sole()->is($classroom))->toBeTrue();
    expect($quiz->attempts()->sole()->is($attempt))->toBeTrue();
    expect($quiz->lessonPlans()->sole()->is($lessonPlan))->toBeTrue();
});
