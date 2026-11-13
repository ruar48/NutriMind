<?php

use App\Enums\ProgressStatus;
use App\Models\ActivityProgress;
use App\Models\LearningActivity;

use function Pest\Laravel\freezeTime;

it('creates completed activities that earned the activity points', function () {
    freezeTime();
    $activity = LearningActivity::factory()->create(['points' => 25]);

    $progress = ActivityProgress::factory()->completed()->for($activity, 'activity')->create();

    expect($progress->status)->toBe(ProgressStatus::Completed);
    expect($progress->points_earned)->toBe(25);
    expect($progress->score)->toBeGreaterThanOrEqual(60)->toBeLessThanOrEqual(100);
    expect($progress->synced_at->greaterThanOrEqualTo($progress->completed_at))->toBeTrue();
    expect($progress->synced_at->lessThanOrEqualTo(now()))->toBeTrue();
});

it('creates activities in progress without a score or points', function () {
    $progress = ActivityProgress::factory()->create();

    expect($progress->status)->toBe(ProgressStatus::InProgress);
    expect($progress->score)->toBeNull();
    expect($progress->points_earned)->toBe(0);
    expect($progress->completed_at)->toBeNull();
});
