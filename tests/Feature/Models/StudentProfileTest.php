<?php

use App\Enums\GradeLevel;
use App\Enums\Sex;
use App\Models\StudentProfile;
use App\Models\User;

use function Pest\Laravel\travelTo;

it('calculates the learner age in whole years from the birthdate', function (string $birthdate, int $age) {
    travelTo('2026-11-13 09:00:00');
    $profile = new StudentProfile(['birthdate' => $birthdate]);

    expect($profile->age)->toBe($age);
})->with([
    'birthday today' => ['2015-11-13', 11],
    'birthday tomorrow' => ['2015-11-14', 10],
]);

it('has no age when the birthdate is unknown', function () {
    expect((new StudentProfile)->age)->toBeNull();
});

it('casts the grade level, sex, birthdate and sync times', function () {
    $profile = StudentProfile::factory()->create([
        'grade_level' => GradeLevel::Six,
        'sex' => Sex::Female,
        'birthdate' => '2015-02-20',
        'last_synced_at' => '2026-11-12 18:30:00',
    ])->fresh();

    expect($profile->grade_level)->toBe(GradeLevel::Six);
    expect($profile->sex)->toBe(Sex::Female);
    expect($profile->birthdate->toDateString())->toBe('2015-02-20');
    expect($profile->last_synced_at->toDateTimeString())->toBe('2026-11-12 18:30:00');
});

it('keeps the game-owned progress fields out of mass assignment', function () {
    $profile = new StudentProfile(['lrn' => '123456789012', 'total_points' => 9999, 'level' => 50, 'last_synced_at' => now()]);

    expect($profile->lrn)->toBe('123456789012');
    expect($profile->total_points)->toBe(0);
    expect($profile->level)->toBe(1);
    expect($profile->last_synced_at)->toBeNull();
});

it('belongs to the learner account', function () {
    $student = User::factory()->student()->create();

    expect($student->studentProfile->user->is($student))->toBeTrue();
});
