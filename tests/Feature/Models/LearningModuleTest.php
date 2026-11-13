<?php

use App\Enums\GradeLevel;
use App\Models\LearningActivity;
use App\Models\LearningModule;
use App\Models\Mission;
use App\Models\Quiz;

it('filters modules that are published in the game', function () {
    $publishedModule = LearningModule::factory()->create();
    LearningModule::factory()->unpublished()->create();

    expect(LearningModule::query()->published()->pluck('id')->all())->toBe([$publishedModule->id]);
});

it('filters modules for a grade level', function () {
    $gradeFiveModule = LearningModule::factory()->create(['grade_level' => GradeLevel::Five]);
    LearningModule::factory()->create(['grade_level' => GradeLevel::Six]);

    expect(LearningModule::query()->forGrade(GradeLevel::Five)->pluck('id')->all())->toBe([$gradeFiveModule->id]);
});

it('lists its activities in play order, then by creation order', function () {
    $module = LearningModule::factory()->create();
    $third = LearningActivity::factory()->for($module, 'module')->create(['sort_order' => 3]);
    $first = LearningActivity::factory()->for($module, 'module')->create(['sort_order' => 0]);
    $second = LearningActivity::factory()->for($module, 'module')->create(['sort_order' => 0]);

    expect($module->activities->modelKeys())->toBe([$first->id, $second->id, $third->id]);
});

it('reaches its subject, missions and quizzes', function () {
    $module = LearningModule::factory()->create();
    $mission = Mission::factory()->for($module, 'module')->create();
    $quiz = Quiz::factory()->for($module, 'module')->create();

    expect($module->subject->id)->toBe($module->subject_id);
    expect($module->missions()->sole()->is($mission))->toBeTrue();
    expect($module->quizzes()->sole()->is($quiz))->toBeTrue();
});

it('keeps missions and quizzes when their module is deleted', function () {
    $module = LearningModule::factory()->create();
    $mission = Mission::factory()->for($module, 'module')->create();
    $quiz = Quiz::factory()->for($module, 'module')->create();
    $activity = LearningActivity::factory()->for($module, 'module')->create();

    $module->delete();

    expect($mission->fresh()->learning_module_id)->toBeNull();
    expect($quiz->fresh()->learning_module_id)->toBeNull();
    $this->assertModelMissing($activity);
});
