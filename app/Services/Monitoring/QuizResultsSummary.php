<?php

namespace App\Services\Monitoring;

use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

/**
 * The headline figures of a quiz's results, for all its classrooms or one of them.
 *
 * Assigned learners are the learners enrolled in the quiz's classrooms in scope. Score figures use each learner's
 * best submitted attempt (highest percentage, then a passing one, then the latest); attempt figures (duration, offline)
 * use every submitted attempt.
 */
final readonly class QuizResultsSummary
{
    /**
     * @param  list<array{label: string, value: int}>  $histogram  10 buckets of best percentages: 0–9 … 80–89, 90–100
     * @param  Collection<int, User>  $notAttempted  assigned learners without a submitted attempt (studentProfile and
     *                                               enrolledClassrooms in scope loaded), ordered by name
     */
    public function __construct(
        /** The number of learners enrolled in the quiz's classrooms in scope. */
        public int $assignedLearners,
        /** The number of assigned learners with at least one submitted attempt. */
        public int $attemptedLearners,
        /** attemptedLearners / assignedLearners × 100 (1 decimal), or null without assigned learners. */
        public ?float $completionRate,
        /** The number of learners with a submitted attempt (assigned or not), i.e. the learners the scores describe. */
        public int $learnersWithResults,
        /** The number of submitted attempts. */
        public int $submittedAttempts,
        /** The mean of the learners' best percentages (1 decimal), or null without attempts. */
        public ?float $averageScore,
        /** The share of learners whose best attempt passed (1 decimal), or null without attempts. */
        public ?float $passRate,
        /** The mean duration of the submitted attempts in whole seconds, or null when none reported a duration. */
        public ?int $averageDurationSeconds,
        /** The number of submitted attempts played offline. */
        public int $offlineSubmissions,
        /** The learners per bucket of best percentage. */
        public array $histogram,
        /** The assigned learners who have not submitted the quiz yet. */
        public Collection $notAttempted,
    ) {}
}
