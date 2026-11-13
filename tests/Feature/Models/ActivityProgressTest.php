<?php

use App\Enums\ProgressStatus;
use App\Models\ActivityProgress;

it('filters completed activities', function () {
    $completedProgress = ActivityProgress::factory()->completed()->create();
    ActivityProgress::factory()->create();

    expect(ActivityProgress::query()->completed()->pluck('id')->all())->toBe([$completedProgress->id]);
});

it('reaches its activity and learner and casts its status', function () {
    $progress = ActivityProgress::factory()->completed()->create()->fresh();

    expect($progress->status)->toBe(ProgressStatus::Completed);
    expect($progress->activity->id)->toBe($progress->learning_activity_id);
    expect($progress->student->id)->toBe($progress->student_id);
});
