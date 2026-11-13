<?php

namespace App\Services\Monitoring;

/**
 * Learner counts of one mission (or of a classroom's missions) in the classrooms in scope.
 */
final readonly class MissionCompletion
{
    public function __construct(
        /** The learners (or learner–mission pairs) who can play the mission(s). */
        public int $eligible,
        /** Those who completed it. */
        public int $completed,
        /** Those who started it but have not finished. */
        public int $inProgress,
    ) {}

    /**
     * Get the number of eligible learners who have not started.
     */
    public function notStarted(): int
    {
        return max(0, $this->eligible - $this->completed - $this->inProgress);
    }

    /**
     * Get completed / eligible × 100 with 1 decimal, or null without eligible learners.
     */
    public function rate(): ?float
    {
        return $this->eligible === 0 ? null : round(min($this->completed, $this->eligible) / $this->eligible * 100, 1);
    }
}
