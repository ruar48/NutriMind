<?php

use App\Enums\ProgressStatus;
use App\Models\MissionProgress;

it('filters completed missions', function () {
    $completedProgress = MissionProgress::factory()->completed()->create();
    MissionProgress::factory()->inProgress()->create();

    expect(MissionProgress::query()->completed()->pluck('id')->all())->toBe([$completedProgress->id]);
});

it('reaches its mission and learner and casts its status', function () {
    $progress = MissionProgress::factory()->completed()->create()->fresh();

    expect($progress->status)->toBe(ProgressStatus::Completed);
    expect($progress->mission->id)->toBe($progress->mission_id);
    expect($progress->student->id)->toBe($progress->student_id);
});
