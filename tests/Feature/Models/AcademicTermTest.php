<?php

use App\Models\AcademicTerm;
use App\Models\LessonPlan;
use App\Models\Quiz;
use App\Models\SchoolYear;

it('finds the active term of the active school year', function () {
    $schoolYear = SchoolYear::factory()->active()->create();
    AcademicTerm::factory()->for($schoolYear)->create();
    $activeTerm = AcademicTerm::factory()->active()->for($schoolYear)->create();

    expect(AcademicTerm::current()?->is($activeTerm))->toBeTrue();
});

it('ignores an active term that belongs to an inactive school year', function () {
    AcademicTerm::factory()->active()->for(SchoolYear::factory())->create();

    expect(AcademicTerm::current())->toBeNull();
});

it('finds no current term when no term is active', function () {
    AcademicTerm::factory()->for(SchoolYear::factory()->active())->create();

    expect(AcademicTerm::current())->toBeNull();
});

it('tells whether it is the active term of the active school year', function (bool $termIsActive, bool $yearIsActive, bool $isCurrent) {
    $term = AcademicTerm::factory()
        ->for(SchoolYear::factory()->state(['is_active' => $yearIsActive]))
        ->create(['is_active' => $termIsActive]);

    expect($term->isCurrent())->toBe($isCurrent);
})->with([
    'active term of the active year' => [true, true, true],
    'active term of an inactive year' => [true, false, false],
    'inactive term of the active year' => [false, true, false],
]);

it('filters active terms', function () {
    $activeTerm = AcademicTerm::factory()->active()->create();
    AcademicTerm::factory()->create();

    expect(AcademicTerm::query()->active()->pluck('id')->all())->toBe([$activeTerm->id]);
});

it('reaches its school year, quizzes and lesson plans', function () {
    $term = AcademicTerm::factory()->create();
    $quiz = Quiz::factory()->for($term)->create();
    $lessonPlan = LessonPlan::factory()->for($term)->create();

    expect($term->schoolYear->id)->toBe($term->school_year_id);
    expect($term->quizzes()->sole()->is($quiz))->toBeTrue();
    expect($term->lessonPlans()->sole()->is($lessonPlan))->toBeTrue();
});
