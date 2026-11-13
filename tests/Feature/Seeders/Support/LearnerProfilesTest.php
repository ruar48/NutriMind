<?php

use App\Models\User;
use Database\Seeders\Support\LearnerArchetype;
use Database\Seeders\Support\LearnerProfile;
use Database\Seeders\Support\LearnerProfiles;
use Illuminate\Database\Eloquent\Factories\Sequence;

use function Pest\Laravel\travelTo;

beforeEach(function () {
    travelTo(now()->setDate(2026, 11, 13)->setTime(10, 0));
});

it('gives the learners archetypes in the planned shares', function () {
    User::factory()->student()->count(100)->create(['created_at' => now()->subMonths(3)]);

    $counts = collect(LearnerProfiles::load(now()->getTimestamp()))->countBy(fn (LearnerProfile $profile): string => $profile->archetype->value);

    expect($counts->sortKeys()->all())->toBe([
        'developing' => 22,
        'needs_support' => 14,
        'star' => 12,
        'steady' => 30,
        'strong' => 22,
    ]);
});

it('keeps every learner\'s ability, engagement and growth within the archetype\'s ranges', function () {
    User::factory()->student()->count(60)->create(['created_at' => now()->subMonths(3)]);

    foreach (LearnerProfiles::load(now()->getTimestamp()) as $profile) {
        [$minAbility, $maxAbility] = $profile->archetype->abilityRange();
        [$minEngagement, $maxEngagement] = $profile->archetype->engagementRange();
        [$minGrowth, $maxGrowth] = $profile->archetype->growthRange();

        expect($profile->hasPlayed)->toBeTrue();
        expect($profile->ability)->toBeGreaterThanOrEqual($minAbility)->toBeLessThanOrEqual($maxAbility);
        expect($profile->engagement)->toBeGreaterThanOrEqual($minEngagement)->toBeLessThanOrEqual($maxEngagement);
        expect($profile->growthPerWeek)->toBeGreaterThanOrEqual($minGrowth)->toBeLessThanOrEqual($maxGrowth);
    }
});

it('marks seven learners as having stopped playing one to three weeks ago', function () {
    User::factory()->student()->count(60)->create(['created_at' => now()->subMonths(3)]);

    $inactive = collect(LearnerProfiles::load(now()->getTimestamp()))->filter(fn (LearnerProfile $profile): bool => $profile->isInactive());

    expect($inactive)->toHaveCount(LearnerProfiles::INACTIVE_LEARNERS);

    foreach ($inactive as $profile) {
        expect($profile->inactiveSince)
            ->toBeGreaterThanOrEqual(now()->subDays(20)->getTimestamp())
            ->toBeLessThanOrEqual(now()->subDays(8)->getTimestamp());
        expect($profile->activeUntil(now()->getTimestamp()))->toBe($profile->inactiveSince);
    }
});

it('treats learners whose accounts were created this fortnight as not having played yet', function () {
    $newcomer = User::factory()->student()->create(['created_at' => now()->subDays(4)]);
    User::factory()->student()->count(20)->create(['created_at' => now()->subMonths(3)]);

    $profile = LearnerProfiles::load(now()->getTimestamp())[$newcomer->id];

    expect($profile->hasPlayed)->toBeFalse();
    expect($profile->isInactive())->toBeFalse();
});

it('fixes the profiles of the demo family\'s children', function () {
    [$sofia, $miguel] = User::factory()->student()->count(2)
        ->state(new Sequence(['username' => 'sofia_navarro'], ['username' => 'miguel_navarro']))
        ->create(['created_at' => now()->subYear()]);
    User::factory()->student()->count(30)->create(['created_at' => now()->subMonths(3)]);

    $profiles = LearnerProfiles::load(now()->getTimestamp());

    expect([$profiles[$sofia->id]->archetype, $profiles[$sofia->id]->growthPerWeek, $profiles[$sofia->id]->isInactive()])
        ->toBe([LearnerArchetype::Strong, 0.004, false]);
    expect([$profiles[$miguel->id]->archetype, $profiles[$miguel->id]->growthPerWeek, $profiles[$miguel->id]->isInactive()])
        ->toBe([LearnerArchetype::Developing, 0.018, false]);
});

it('derives the same profiles every time it loads them', function () {
    User::factory()->student()->count(40)->create(['created_at' => now()->subMonths(3)]);

    expect(LearnerProfiles::load(now()->getTimestamp()))->toEqual(LearnerProfiles::load(now()->getTimestamp()));
});
