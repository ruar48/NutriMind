<?php

use App\Models\AcademicTerm;
use App\Models\SchoolYear;

it('numbers and names the quarters of a school year in order', function () {
    $schoolYear = SchoolYear::factory()->create();

    $terms = AcademicTerm::factory()->count(4)->for($schoolYear)->create();

    expect($terms->pluck('sequence')->all())->toBe([1, 2, 3, 4]);
    expect($terms->pluck('name')->all())->toBe(['1st Quarter', '2nd Quarter', '3rd Quarter', '4th Quarter']);
});

it('dates consecutive quarters inside the school year without overlapping', function () {
    $schoolYear = SchoolYear::factory()->create(['starts_on' => '2026-06-15', 'ends_on' => '2027-03-31']);

    $terms = AcademicTerm::factory()->count(4)->for($schoolYear)->create();

    expect($terms->first()->starts_on->toDateString())->toBe('2026-06-15');
    expect($terms->last()->ends_on->toDateString())->toBe('2027-03-31');
    $terms->sliding(2)->each(function ($pair): void {
        [$earlier, $later] = $pair->values()->all();

        expect($later->starts_on->toDateString())->toBe($earlier->ends_on->copy()->addDay()->toDateString());
    });
});

it('continues the numbering after the terms a school year already has', function () {
    $schoolYear = SchoolYear::factory()->create();
    AcademicTerm::factory()->for($schoolYear)->create();

    $nextTerm = AcademicTerm::factory()->for($schoolYear)->create();

    expect($nextTerm->sequence)->toBe(2);
    expect($nextTerm->name)->toBe('2nd Quarter');
});
