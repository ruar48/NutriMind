<?php

use App\Enums\ProgressStatus;
use App\Models\Mission;
use App\Models\MissionProgress;

use function Pest\Laravel\freezeTime;

it('creates completed missions that earned the full reward', function () {
    freezeTime();
    $mission = Mission::factory()->create(['points_reward' => 150]);

    $progress = MissionProgress::factory()->completed()->for($mission)->create();

    expect($progress->status)->toBe(ProgressStatus::Completed);
    expect($progress->progress_percent)->toBe(100);
    expect($progress->points_earned)->toBe(150);
    expect($progress->completed_at->greaterThanOrEqualTo($progress->started_at))->toBeTrue();
    expect($progress->synced_at->greaterThanOrEqualTo($progress->completed_at))->toBeTrue();
    expect($progress->synced_at->lessThanOrEqualTo(now()))->toBeTrue();
});

it('creates missions still in progress without points or a completion time', function () {
    $progress = MissionProgress::factory()->inProgress()->create();

    expect($progress->status)->toBe(ProgressStatus::InProgress);
    expect($progress->progress_percent)->toBeLessThan(100);
    expect($progress->points_earned)->toBe(0);
    expect($progress->completed_at)->toBeNull();
});

it('belongs to a learner', function () {
    expect(MissionProgress::factory()->create()->student->isStudent())->toBeTrue();
});
