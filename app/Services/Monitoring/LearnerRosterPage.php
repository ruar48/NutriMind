<?php

namespace App\Services\Monitoring;

use Illuminate\Pagination\LengthAwarePaginator;

/**
 * The teacher's learners list: one page of rows plus figures about every learner in scope.
 *
 * The page figures (learnerCount, bandCounts, averageScore, needsAttention, syncNeedsAttention) describe all learners
 * of the classrooms in scope and ignore the band and search filters, so they stay stable while the teacher filters.
 */
final readonly class LearnerRosterPage
{
    /**
     * @param  LengthAwarePaginator<int, LearnerRosterRow>  $rows
     * @param  array<string, int>  $bandCounts  learners per PerformanceBand value (every band present)
     */
    public function __construct(
        /** The filtered, sorted page of learners. */
        public LengthAwarePaginator $rows,
        /** The number of learners enrolled in the classrooms in scope. */
        public int $learnerCount,
        /** The number of learners in each performance band. */
        public array $bandCounts,
        /** The mean of the learners' average scores (1 decimal), or null when nobody has attempts. */
        public ?float $averageScore,
        /** The number of learners whose average Did Not Meet Expectations. */
        public int $needsAttention,
        /** The number of learners whose last game sync is missing or more than 7 days old. */
        public int $syncNeedsAttention,
    ) {}
}
