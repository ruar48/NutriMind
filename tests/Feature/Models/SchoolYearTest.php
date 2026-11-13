<?php

use App\Models\AcademicTerm;
use App\Models\Classroom;
use App\Models\SchoolYear;
use Illuminate\Support\Facades\DB;

it('finds the active school year', function () {
    SchoolYear::factory()->create();
    $activeYear = SchoolYear::factory()->active()->create();

    expect(SchoolYear::current()?->is($activeYear))->toBeTrue();
});

it('finds no current school year when none is active', function () {
    SchoolYear::factory()->create();

    expect(SchoolYear::current())->toBeNull();
});

it('prefers the most recent active school year if several are flagged active', function () {
    SchoolYear::factory()->active()->create(['name' => '2025–2026', 'starts_on' => '2025-06-16', 'ends_on' => '2026-03-31']);
    $latestYear = SchoolYear::factory()->active()->create(['name' => '2026–2027', 'starts_on' => '2026-06-15', 'ends_on' => '2027-03-31']);

    expect(SchoolYear::current()?->is($latestYear))->toBeTrue();
});

it('filters active school years', function () {
    $activeYear = SchoolYear::factory()->active()->create();
    SchoolYear::factory()->create();

    expect(SchoolYear::query()->active()->pluck('id')->all())->toBe([$activeYear->id]);
});

it('lists its terms in sequence order', function () {
    $schoolYear = SchoolYear::factory()->create();
    $second = AcademicTerm::factory()->for($schoolYear)->create(['sequence' => 2, 'name' => '2nd Quarter']);
    $first = AcademicTerm::factory()->for($schoolYear)->create(['sequence' => 1, 'name' => '1st Quarter']);

    expect($schoolYear->terms->modelKeys())->toBe([$first->id, $second->id]);
});

it('links its loaded terms back to it so they can tell whether they are current without querying', function () {
    $schoolYear = SchoolYear::factory()->active()->create();
    AcademicTerm::factory()->active()->for($schoolYear)->create();
    $loadedYear = SchoolYear::query()->with('terms')->findOrFail($schoolYear->id);

    DB::enableQueryLog();
    $isCurrent = $loadedYear->terms->sole()->isCurrent();

    expect($isCurrent)->toBeTrue();
    expect(DB::getQueryLog())->toBeEmpty();
});

it('reaches its classrooms', function () {
    $schoolYear = SchoolYear::factory()->create();
    $classroom = Classroom::factory()->for($schoolYear)->create();

    expect($schoolYear->classrooms()->sole()->is($classroom))->toBeTrue();
});

it('casts its dates and activation flag', function () {
    $schoolYear = SchoolYear::factory()->active()->create(['starts_on' => '2026-06-15', 'ends_on' => '2027-03-31'])->fresh();

    expect($schoolYear->starts_on->toDateString())->toBe('2026-06-15');
    expect($schoolYear->ends_on->toDateString())->toBe('2027-03-31');
    expect($schoolYear->is_active)->toBeTrue();
});
