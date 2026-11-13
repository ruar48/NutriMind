<?php

use Database\Seeders\Support\DemoClock;
use Database\Seeders\Support\LearnerArchetype;
use Database\Seeders\Support\LearnerProfile;

it('improves the learner\'s ability gently over the weeks', function (float $weeksAgo, float $ability) {
    $now = DemoClock::at('2026-11-13 10:00:00');
    $profile = new LearnerProfile(LearnerArchetype::Steady, 0.8, 0.9, 0.01, null, true);

    expect($profile->abilityAt($now - (int) round($weeksAgo * 7 * DemoClock::DAY), $now))->toEqualWithDelta($ability, 0.0001);
})->with([
    'eight weeks ago' => [8.0, 0.76],
    'four weeks ago' => [4.0, 0.8],
    'this week' => [0.0, 0.84],
    'last school year, capped' => [36.0, 0.72],
]);

it('was active until it stopped playing, or until now', function () {
    $now = DemoClock::at('2026-11-13 10:00:00');
    $inactiveSince = $now - 10 * DemoClock::DAY;

    expect((new LearnerProfile(LearnerArchetype::Steady, 0.8, 0.9, 0.0, $inactiveSince, true))->activeUntil($now))->toBe($inactiveSince);
    expect((new LearnerProfile(LearnerArchetype::Steady, 0.8, 0.9, 0.0, null, true))->activeUntil($now))->toBe($now);
});

it('gives the archetypes shares that add up to the whole school', function () {
    expect(array_sum(array_map(fn (LearnerArchetype $archetype): float => $archetype->share(), LearnerArchetype::cases())))->toEqualWithDelta(1.0, 0.0001);
});

it('ranks the archetypes from the strongest to the learners who need support', function () {
    $abilities = array_map(fn (LearnerArchetype $archetype): float => $archetype->abilityRange()[0], LearnerArchetype::cases());
    $sorted = $abilities;
    rsort($sorted);

    expect($abilities)->toBe($sorted);

    foreach (LearnerArchetype::cases() as $archetype) {
        foreach ([$archetype->abilityRange(), $archetype->engagementRange(), $archetype->growthRange()] as [$min, $max]) {
            expect($min)->toBeLessThanOrEqual($max);
        }
    }
});
