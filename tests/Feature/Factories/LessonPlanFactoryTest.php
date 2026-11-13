<?php

use App\Enums\GradeLevel;
use App\Enums\LessonPlanStatus;
use App\Models\LearningModule;
use App\Models\LessonPlan;

it('creates a planned lesson that runs from Monday to Friday', function () {
    $lessonPlan = LessonPlan::factory()->create();

    expect($lessonPlan->status)->toBe(LessonPlanStatus::Planned);
    expect($lessonPlan->starts_on->isMonday())->toBeTrue();
    expect($lessonPlan->ends_on->toDateString())->toBe($lessonPlan->starts_on->copy()->addDays(4)->toDateString());
});

it('takes the subject and grade level of its learning module', function () {
    $module = LearningModule::factory()->create(['grade_level' => GradeLevel::Five]);

    $lessonPlan = LessonPlan::factory()->for($module, 'module')->create();

    expect($lessonPlan->subject_id)->toBe($module->subject_id);
    expect($lessonPlan->grade_level)->toBe(GradeLevel::Five);
});
