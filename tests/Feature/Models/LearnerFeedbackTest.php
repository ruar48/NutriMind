<?php

use App\Enums\FeedbackType;
use App\Models\Classroom;
use App\Models\LearnerFeedback;
use App\Models\QuizAttempt;

it('filters feedback that parents and guardians may read', function () {
    $visibleFeedback = LearnerFeedback::factory()->create();
    LearnerFeedback::factory()->hiddenFromGuardians()->create();

    expect(LearnerFeedback::query()->visibleToGuardians()->pluck('id')->all())->toBe([$visibleFeedback->id]);
});

it('filters feedback nobody has read yet', function () {
    $unreadFeedback = LearnerFeedback::factory()->create();
    LearnerFeedback::factory()->read()->create();

    expect(LearnerFeedback::query()->unread()->pluck('id')->all())->toBe([$unreadFeedback->id]);
});

it('reaches its teacher, learner, classroom and quiz attempt', function () {
    $classroom = Classroom::factory()->create();
    $attempt = QuizAttempt::factory()->create();
    $feedback = LearnerFeedback::factory()->for($classroom)->for($attempt)->create(['type' => FeedbackType::Guidance])->fresh();

    expect($feedback->type)->toBe(FeedbackType::Guidance);
    expect($feedback->teacher->id)->toBe($feedback->teacher_id);
    expect($feedback->student->id)->toBe($feedback->student_id);
    expect($feedback->classroom->is($classroom))->toBeTrue();
    expect($feedback->quizAttempt->is($attempt))->toBeTrue();
});

it('keeps feedback when its classroom and quiz attempt are deleted', function () {
    $classroom = Classroom::factory()->create();
    $attempt = QuizAttempt::factory()->create();
    $feedback = LearnerFeedback::factory()->for($classroom)->for($attempt)->create();

    $classroom->delete();
    $attempt->delete();

    $storedFeedback = $feedback->fresh();
    expect($storedFeedback->classroom_id)->toBeNull();
    expect($storedFeedback->quiz_attempt_id)->toBeNull();
});
