<?php

use App\Enums\RewardType;
use App\Models\Reward;
use App\Models\User;

use function Pest\Laravel\freezeTime;

it('filters active rewards', function () {
    $activeReward = Reward::factory()->create();
    Reward::factory()->inactive()->create();

    expect(Reward::query()->active()->pluck('id')->all())->toBe([$activeReward->id]);
});

it('records when each learner earned the reward', function () {
    freezeTime();
    $reward = Reward::factory()->create(['type' => RewardType::Trophy]);
    $student = User::factory()->student()->create();

    $reward->students()->attach($student);
    $earnedBy = $reward->students()->sole();

    expect($reward->fresh()->type)->toBe(RewardType::Trophy);
    expect($earnedBy->is($student))->toBeTrue();
    expect($earnedBy->pivot->awarded_at->toDateTimeString())->toBe(now()->toDateTimeString());
});

it('keeps an award date that is given explicitly', function () {
    $reward = Reward::factory()->create();
    $student = User::factory()->student()->create();

    $student->rewards()->attach($reward, ['awarded_at' => '2026-10-01 10:15:00']);

    expect($student->rewards()->sole()->pivot->awarded_at->toDateTimeString())->toBe('2026-10-01 10:15:00');
});
