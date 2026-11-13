<?php

use App\Enums\Difficulty;
use App\Enums\GradeLevel;
use App\Enums\MissionType;
use App\Models\LessonPlan;
use App\Models\Mission;
use App\Models\MissionProgress;

it('filters active missions', function () {
    $activeMission = Mission::factory()->create();
    Mission::factory()->inactive()->create();

    expect(Mission::query()->active()->pluck('id')->all())->toBe([$activeMission->id]);
});

it('includes missions open to both grades when filtering by grade', function () {
    $gradeFiveMission = Mission::factory()->create(['grade_level' => GradeLevel::Five]);
    $bothGradesMission = Mission::factory()->create(['grade_level' => null]);
    Mission::factory()->create(['grade_level' => GradeLevel::Six]);

    $missionIds = Mission::query()->forGrade(GradeLevel::Five)->pluck('id')->all();

    expect($missionIds)->toEqualCanonicalizing([$gradeFiveMission->id, $bothGradesMission->id]);
});

it('keeps the other constraints of the query when filtering by grade', function () {
    $activeMission = Mission::factory()->create(['grade_level' => null]);
    Mission::factory()->inactive()->create(['grade_level' => null]);

    expect(Mission::query()->active()->forGrade(GradeLevel::Six)->pluck('id')->all())->toBe([$activeMission->id]);
});

it('casts its type, difficulty, grade and dates', function () {
    $mission = Mission::factory()->create([
        'type' => MissionType::Weekly,
        'difficulty' => Difficulty::Hard,
        'grade_level' => null,
        'starts_on' => '2026-11-16',
        'ends_on' => '2026-11-22',
    ])->fresh();

    expect($mission->type)->toBe(MissionType::Weekly);
    expect($mission->difficulty)->toBe(Difficulty::Hard);
    expect($mission->grade_level)->toBeNull();
    expect($mission->starts_on->toDateString())->toBe('2026-11-16');
    expect($mission->ends_on->toDateString())->toBe('2026-11-22');
});

it('reaches its subject, learner progress and aligned lesson plans', function () {
    $mission = Mission::factory()->create();
    $progress = MissionProgress::factory()->for($mission)->create();
    $lessonPlan = LessonPlan::factory()->create();
    $lessonPlan->missions()->attach($mission);

    expect($mission->subject->id)->toBe($mission->subject_id);
    expect($mission->module)->toBeNull();
    expect($mission->progress()->sole()->is($progress))->toBeTrue();
    expect($mission->lessonPlans()->sole()->is($lessonPlan))->toBeTrue();
});
