<?php

namespace Database\Seeders\Support;

/**
 * How one demo learner performs and plays: the basis of their attempts, game progress and feedback.
 */
final readonly class LearnerProfile
{
    /**
     * The number of seconds in a week.
     */
    private const WEEK = 7 * DemoClock::DAY;

    /**
     * Create a learner profile.
     *
     * @param  float  $ability  The share of medium-difficulty questions answered correctly four weeks ago.
     * @param  float  $engagement  The probability of taking an assigned quiz or keeping up with game work.
     * @param  float  $growthPerWeek  How much the ability improves each week.
     * @param  int|null  $inactiveSince  When a learner who stopped playing last synced, or null.
     * @param  bool  $hasPlayed  False for newly enrolled learners who have not opened the game yet.
     */
    public function __construct(
        public LearnerArchetype $archetype,
        public float $ability,
        public float $engagement,
        public float $growthPerWeek,
        public ?int $inactiveSince,
        public bool $hasPlayed,
    ) {}

    /**
     * Get the learner's ability at a moment: lower in earlier weeks, higher recently (a gentle improvement trend).
     */
    public function abilityAt(int $timestamp, int $now): float
    {
        $weeksSinceMidpoint = ($timestamp - ($now - 4 * self::WEEK)) / self::WEEK;

        return $this->ability + $this->growthPerWeek * max(-8.0, min(4.0, $weeksSinceMidpoint));
    }

    /**
     * Get the last moment the learner was active: when they stopped playing, or now.
     */
    public function activeUntil(int $now): int
    {
        return $this->inactiveSince ?? $now;
    }

    /**
     * Determine whether the learner stopped playing (and syncing) a while ago.
     */
    public function isInactive(): bool
    {
        return $this->inactiveSince !== null;
    }
}
