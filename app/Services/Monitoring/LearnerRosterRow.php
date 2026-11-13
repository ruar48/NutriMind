<?php

namespace App\Services\Monitoring;

use App\Enums\PerformanceBand;
use App\Models\Classroom;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;

/**
 * One learner of the teacher's learners list, with the figures of the classrooms in scope.
 */
final readonly class LearnerRosterRow
{
    /**
     * @param  Collection<int, Classroom>  $classrooms
     */
    public function __construct(
        /** The learner, with studentProfile loaded (null without a learner profile). */
        public User $student,
        /** The learner's classrooms among those in scope, with subject loaded, ordered by name. */
        public Collection $classrooms,
        /** The learner's total points (XP) from the game. */
        public int $points,
        /** The level of the total points with the current gamification settings. */
        public int $level,
        /** The mean of the best percentage of each quiz submitted in the classrooms in scope (1 decimal), or null. */
        public ?float $averageScore,
        /** The DepEd band of the rounded average score (NoData without attempts). */
        public PerformanceBand $band,
        /** The number of distinct quizzes submitted in the classrooms in scope. */
        public int $quizzesTaken,
        /** The number of missions completed in the subjects of the learner's classrooms in scope. */
        public int $missionsCompleted,
        /** When the learner last played the game. */
        public ?CarbonInterface $lastActiveAt,
        /** When the game last synced the learner's progress. */
        public ?CarbonInterface $lastSyncedAt,
        /** Whether the last sync is missing or more than 7 days old. */
        public bool $syncNeedsAttention,
    ) {}
}
