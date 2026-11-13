<?php

namespace App\Services;

use App\Enums\PerformanceBand;
use App\Models\Classroom;
use App\Models\MissionProgress;
use App\Models\QuizAttempt;
use App\Models\Reward;
use App\Models\Subject;
use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;

/**
 * A learner's progress as the web portals show it, built by LearnerProgressService::summarize().
 *
 * When the summary was built for a list of subjects (the teacher view), every quiz, mission and activity figure
 * and the classrooms only cover those subjects; points, level, rewards and the activity timestamps always describe
 * the whole learner profile.
 */
final readonly class LearnerProgressSummary
{
    /**
     * The number of days after which a learner's last game sync counts as overdue.
     */
    public const SYNC_OVERDUE_AFTER_DAYS = 7;

    /**
     * @param  Collection<int, Classroom>  $classrooms
     * @param  array{level: int, into: int, needed: int, percent: float}  $levelProgress
     * @param  list<array{label: string, start: CarbonImmutable, average: float|null, attempts: int}>  $weeklyScores
     * @param  list<array{subject: Subject, averageScore: float|null, quizzesTaken: int, missionsCompleted: int, activitiesCompleted: int, activitiesTotal: int}>  $subjectBreakdown
     * @param  Collection<int, QuizAttempt>  $recentAttempts
     * @param  Collection<int, MissionProgress>  $recentMissions
     * @param  Collection<int, Reward>  $rewards
     */
    public function __construct(
        /** The learner, with the studentProfile relation loaded (null when the account has no learner profile). */
        public User $student,
        /** The active (not archived) classrooms the learner is enrolled in, with subject loaded, ordered by name. */
        public Collection $classrooms,
        /** The learner's total points (XP) from the game. */
        public int $totalPoints,
        /** The level calculated from the total points with the current gamification settings. */
        public int $level,
        /** The highest level a learner can reach with the current settings. */
        public int $maxLevel,
        /** The points earned inside the current level (0 at the maximum level). */
        public int $pointsIntoLevel,
        /** The points the current level spans, i.e. the XP bar's maximum (0 at the maximum level). */
        public int $pointsForNextLevel,
        /** The share of the current level completed, 0–100 with 1 decimal (100.0 at the maximum level). */
        public float $levelPercent,
        /** The LevelCalculator progress array, ready for <x-xp-bar :progress="…">. */
        public array $levelProgress,
        /** The number of distinct quizzes with at least one submitted attempt. */
        public int $quizzesTaken,
        /** The number of distinct quizzes whose best attempt passed. */
        public int $quizzesPassed,
        /** The mean over quizzes of the best attempt's percentage, rounded to 1 decimal (null without attempts). */
        public ?float $averageScore,
        /** The DepEd performance band of the rounded average score (NoData without attempts). */
        public PerformanceBand $band,
        /** The number of completed missions. */
        public int $missionsCompleted,
        /** The number of missions in progress. */
        public int $missionsInProgress,
        /** The number of completed activities among those counted in activitiesTotal. */
        public int $activitiesCompleted,
        /** The published activities of published modules for the learner's grade in the learner's classroom subjects. */
        public int $activitiesTotal,
        /** The number of rewards the learner earned. */
        public int $rewardsEarned,
        /** When the learner last played the game, as reported by the game. */
        public ?CarbonInterface $lastActiveAt,
        /** When the game last synced the learner's progress to the server. */
        public ?CarbonInterface $lastSyncedAt,
        /** The last 8 ISO weeks (Monday start), oldest first, ending with the current week: mean attempt percentage and attempt count. */
        public array $weeklyScores,
        /** One entry per subject of the learner's active classrooms, ordered by subject name. */
        public array $subjectBreakdown,
        /** The latest 5 submitted attempts, newest first, with quiz.subject loaded. */
        public Collection $recentAttempts,
        /** The latest 5 mission progress records by last update, newest first, with mission.subject loaded. */
        public Collection $recentMissions,
        /** The rewards the learner earned, latest award first (pivot awarded_at loaded). */
        public Collection $rewards,
    ) {}

    /**
     * Determine whether the game has not synced the learner's progress for more than the allowed number of days.
     *
     * A learner who played but never synced is overdue; a learner who never played is not.
     */
    public function isSyncOverdue(): bool
    {
        if ($this->lastSyncedAt === null) {
            return $this->lastActiveAt !== null;
        }

        return $this->lastSyncedAt->lt(now()->subDays(self::SYNC_OVERDUE_AFTER_DAYS));
    }
}
