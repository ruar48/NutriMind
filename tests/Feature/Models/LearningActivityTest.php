<?php

use App\Enums\ActivityType;
use App\Models\ActivityProgress;
use App\Models\LearningActivity;
use App\Models\LessonPlan;

it('filters activities that are published in the game', function () {
    $publishedActivity = LearningActivity::factory()->create();
    LearningActivity::factory()->unpublished()->create();

    expect(LearningActivity::query()->published()->pluck('id')->all())->toBe([$publishedActivity->id]);
});

it('reaches its module, learner progress and aligned lesson plans', function () {
    $activity = LearningActivity::factory()->create(['type' => ActivityType::MiniGame]);
    $progress = ActivityProgress::factory()->for($activity, 'activity')->create();
    $lessonPlan = LessonPlan::factory()->create();
    $lessonPlan->activities()->attach($activity);

    expect($activity->fresh()->type)->toBe(ActivityType::MiniGame);
    expect($activity->module->id)->toBe($activity->learning_module_id);
    expect($activity->progress()->sole()->is($progress))->toBeTrue();
    expect($activity->lessonPlans()->sole()->is($lessonPlan))->toBeTrue();
});
