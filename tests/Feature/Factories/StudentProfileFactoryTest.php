<?php

use App\Enums\GradeLevel;
use App\Enums\UserRole;
use App\Models\StudentProfile;
use App\Services\Settings;

use function Pest\Laravel\freezeTime;

it('gives every learner a distinct twelve-digit LRN', function () {
    $lrns = StudentProfile::factory()->count(10)->create()->pluck('lrn');

    expect($lrns->unique())->toHaveCount(10);
    expect($lrns->all())->each->toMatch('/^\d{12}$/');
});

it('belongs to a learner account', function () {
    expect(StudentProfile::factory()->create()->user->role)->toBe(UserRole::Student);
});

it('gives the learner a birthdate that matches the grade level', function (GradeLevel $grade, int $age) {
    freezeTime();

    $profiles = StudentProfile::factory()->count(5)->create(['grade_level' => $grade]);

    expect($profiles->map(fn (StudentProfile $profile): ?int => $profile->age)->unique()->all())->toBe([$age]);
})->with([
    'grade 5 learners are 10' => [GradeLevel::Five, 10],
    'grade 6 learners are 11' => [GradeLevel::Six, 11],
]);

it('derives the level from the points with the default level rule', function (int $points, int $level) {
    expect(StudentProfile::factory()->create(['total_points' => $points])->level)->toBe($level);
})->with([
    'no points' => [0, 1],
    'just below the next level' => [499, 1],
    'exactly the next level' => [500, 2],
    'several levels' => [1250, 3],
    'capped at the maximum level' => [100000, 50],
]);

it('derives the level from the points with the configured level rule', function () {
    app(Settings::class)->setMany(['points_per_level' => 100, 'max_level' => 10]);

    expect(StudentProfile::factory()->create(['total_points' => 450])->level)->toBe(5);
    expect(StudentProfile::factory()->create(['total_points' => 5000])->level)->toBe(10);
});

it('syncs the learner after the last activity and never in the future', function () {
    freezeTime();

    $profiles = StudentProfile::factory()->count(5)->create();

    $profiles->each(function (StudentProfile $profile): void {
        expect($profile->last_synced_at->greaterThanOrEqualTo($profile->last_active_at))->toBeTrue();
        expect($profile->last_synced_at->lessThanOrEqualTo(now()))->toBeTrue();
    });
});
