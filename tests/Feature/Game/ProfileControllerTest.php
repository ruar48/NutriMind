<?php

use App\Enums\GradeLevel;
use App\Models\Reward;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

it('returns 401 without a token', function () {
    $this->getJson(route('game.profile'))
        ->assertUnauthorized()
        ->assertJson(['message' => 'Unauthenticated.']);
});

it('returns 403 for a token of a staff account', function () {
    Sanctum::actingAs(User::factory()->teacher()->create());

    $this->getJson(route('game.profile'))->assertForbidden();
});

it('shows the learner\'s grade, points, level and rewards', function () {
    $student = User::factory()->student(GradeLevel::Six)->create(['name' => 'Juan Dela Cruz', 'username' => 'jdelacruz']);
    $student->studentProfile->forceFill(['total_points' => 1200, 'last_synced_at' => '2026-11-20 08:00:00'])->save();
    $reward = Reward::factory()->create(['name' => 'Golden Lantern Badge', 'description' => 'Finished the first mission.']);
    $student->rewards()->attach($reward, ['awarded_at' => '2026-11-19 10:00:00']);
    Sanctum::actingAs($student);

    $this->getJson(route('game.profile'))
        ->assertOk()
        ->assertExactJson(['data' => [
            'id' => $student->id,
            'name' => 'Juan Dela Cruz',
            'username' => 'jdelacruz',
            'grade' => 6,
            'total_points' => 1200,
            'level' => 3,
            'max_level' => 50,
            'level_progress' => ['points_into_level' => 200, 'points_per_level' => 500, 'percent' => 40],
            'rewards' => [[
                'name' => 'Golden Lantern Badge',
                'description' => 'Finished the first mission.',
                'awarded_at' => '2026-11-19T10:00:00+08:00',
            ]],
            'last_synced_at' => '2026-11-20T08:00:00+08:00',
        ]]);
});
