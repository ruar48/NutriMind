<?php

namespace Database\Seeders\Support;

use App\Enums\UserRole;
use App\Models\User;
use Database\Seeders\Support\Catalog\PeopleCatalog;

/**
 * Derives the profile of every demo learner from the seeded accounts.
 *
 * The derivation uses its own seeded stream and only reads data that exists before any learner activity is seeded,
 * so every seeder that needs the profiles gets exactly the same ones.
 */
final class LearnerProfiles
{
    /**
     * How many learners stopped playing (and syncing) one to three weeks ago.
     */
    public const INACTIVE_LEARNERS = 7;

    /**
     * Accounts created this recently belong to newly enrolled learners who have not played yet.
     */
    private const NEWCOMER_DAYS = 14;

    /**
     * Load the profiles of all learners, keyed by user ID.
     *
     * @return array<int, LearnerProfile>
     */
    public static function load(int $now): array
    {
        $learners = User::query()
            ->ofRole(UserRole::Student)
            ->orderBy('id')
            ->toBase()
            ->get(['id', 'username', 'created_at']);

        $random = DemoRandom::stream('learner-profiles');
        $newcomerSince = $now - self::NEWCOMER_DAYS * DemoClock::DAY;
        $overrides = PeopleCatalog::profileOverrides();

        $regularIds = [];

        foreach ($learners as $learner) {
            if (DemoClock::at($learner->created_at) < $newcomerSince && ! isset($overrides[$learner->username])) {
                $regularIds[] = (int) $learner->id;
            }
        }

        $archetypes = self::assignArchetypes($random, $regularIds);
        $inactiveIds = self::pickInactiveLearners($random, $archetypes);

        $profiles = [];

        foreach ($learners as $learner) {
            $id = (int) $learner->id;

            if (DemoClock::at($learner->created_at) >= $newcomerSince) {
                $profiles[$id] = new LearnerProfile(LearnerArchetype::Steady, 0.82, 0.0, 0.0, null, false);

                continue;
            }

            $override = $overrides[$learner->username] ?? null;
            $archetype = $override['archetype'] ?? $archetypes[$id];

            $profiles[$id] = new LearnerProfile(
                archetype: $archetype,
                ability: $random->between(...$archetype->abilityRange()),
                engagement: $random->between(...$archetype->engagementRange()),
                growthPerWeek: $override['growth'] ?? $random->between(...$archetype->growthRange()),
                inactiveSince: in_array($id, $inactiveIds, true)
                    ? $now - (int) round($random->between(8.0, 20.0) * DemoClock::DAY)
                    : null,
                hasPlayed: true,
            );
        }

        return $profiles;
    }

    /**
     * Give each learner an archetype so the school matches the archetype shares.
     *
     * @param  list<int>  $learnerIds
     * @return array<int, LearnerArchetype>
     */
    private static function assignArchetypes(DemoRandom $random, array $learnerIds): array
    {
        $shuffled = $random->shuffle($learnerIds);
        $count = count($shuffled);
        $archetypes = [];
        $position = 0;
        $cumulativeShare = 0.0;

        foreach (LearnerArchetype::cases() as $archetype) {
            $cumulativeShare += $archetype->share();
            $end = min($count, (int) round($cumulativeShare * $count));

            for (; $position < $end; $position++) {
                $archetypes[$shuffled[$position]] = $archetype;
            }
        }

        for (; $position < $count; $position++) {
            $archetypes[$shuffled[$position]] = LearnerArchetype::Steady;
        }

        return $archetypes;
    }

    /**
     * Pick the learners who stopped playing, favoring those who are already struggling.
     *
     * @param  array<int, LearnerArchetype>  $archetypes
     * @return list<int>
     */
    private static function pickInactiveLearners(DemoRandom $random, array $archetypes): array
    {
        ksort($archetypes);

        $weights = array_map(fn (LearnerArchetype $archetype): float => $archetype->inactivityWeight(), $archetypes);
        $picked = [];

        while (count($picked) < self::INACTIVE_LEARNERS && $weights !== []) {
            $learnerId = (int) $random->weighted($weights);
            $picked[] = $learnerId;

            unset($weights[$learnerId]);
        }

        return $picked;
    }
}
