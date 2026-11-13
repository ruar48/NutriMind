<?php

use App\Enums\GradeLevel;
use App\Models\LearningModule;
use App\Models\Mission;

it('creates an active mission without a learning module by default', function () {
    $mission = Mission::factory()->create();

    expect($mission->is_active)->toBeTrue();
    expect($mission->learning_module_id)->toBeNull();
    expect($mission->grade_level)->toBeInstanceOf(GradeLevel::class);
});

it('takes the subject and grade level of its learning module', function () {
    $module = LearningModule::factory()->create(['grade_level' => GradeLevel::Six]);

    $mission = Mission::factory()->for($module, 'module')->create();

    expect($mission->subject_id)->toBe($module->subject_id);
    expect($mission->grade_level)->toBe(GradeLevel::Six);
});
