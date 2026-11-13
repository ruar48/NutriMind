<?php

namespace App\Services\Analytics;

use App\Enums\GradeLevel;
use App\Enums\PerformanceBand;
use App\Enums\ProgressStatus;
use App\Enums\QuestionType;
use App\Enums\UserRole;
use App\Models\ActivityProgress;
use App\Models\Classroom;
use App\Models\LearningModule;
use App\Models\MissionProgress;
use App\Models\QuizAttempt;
use App\Models\QuizAttemptAnswer;
use App\Models\QuizQuestion;
use App\Models\Subject;
use App\Models\User;
use App\Services\LearnerProgressSummary;
use App\Services\LevelCalculator;
use Carbon\CarbonImmutable;
use Carbon\WeekDay;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Aggregated learning figures for the dashboards and analytics pages, limited to an AnalyticsScope.
 *
 * Definitions shared by every figure:
 * - A quiz "result" is a learner's best submitted attempt at a quiz (per classroom) inside the period. Average
 *   scores, pass rates, the score histogram, performance bands, subject/grade/classroom/module figures all use
 *   results, like the learner profile does. Weekly trend points are the mean of every attempt submitted that week.
 * - An "active learner" submitted a quiz attempt, completed a mission or completed a learning activity.
 * - Only active learner accounts in scope count (see AnalyticsScope).
 *
 * Every figure comes from a bounded number of grouped queries (independent of the number of classrooms); weeks
 * and weekdays are bucketed in PHP from DATE() groups so the SQL stays database-agnostic. Figures are memoized
 * per scope for the lifetime of the instance (one request); resolve a new instance after changing data.
 */
class LearningAnalytics
{
    /**
     * Learners whose last game activity is at least this many days old (or who never played) need attention.
     */
    public const INACTIVE_AFTER_DAYS = 7;

    /**
     * The width of a score histogram bucket, in percentage points.
     */
    public const HISTOGRAM_BUCKET_SIZE = 10;

    /**
     * Questions answered fewer times than this are left out of the most-missed list (too few answers to judge).
     */
    public const MOST_MISSED_MIN_ANSWERS = 3;

    /**
     * Short weekday names, Monday first (ISO order).
     */
    public const WEEKDAYS = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];

    /**
     * @var array<string, Collection<int, object{studentId: int, quizId: int, classroomId: int|null, subjectId: int, moduleId: int|null, grade: int|null, score: float, passed: bool, attempts: int}>>
     */
    private array $resultsMemo = [];

    /**
     * @var array<string, array{attempts: Collection<int, object>, missions: Collection<int, object>, activities: Collection<int, object>}>
     */
    private array $activityMemo = [];

    /**
     * @var array<string, Collection<int, object{id: int, name: string, points: int, lastActiveAt: CarbonImmutable|null, lastSyncedAt: CarbonImmutable|null}>>
     */
    private array $learnerMemo = [];

    public function __construct(private LevelCalculator $levels) {}

    /**
     * Get the headline figures of the period, each with its change against the comparison period.
     *
     * Deltas of averages and rates are in percentage points (null when either period has no results); counts
     * change by whole numbers.
     *
     * @return array{learners: int, averageScore: float|null, averageScoreDelta: float|null, passRate: float|null, passRateDelta: float|null, results: int, attempts: int, attemptsDelta: int, activeLearners: int, activeLearnersDelta: int, missionsCompleted: int, missionsCompletedDelta: int, activitiesCompleted: int, activitiesCompletedDelta: int}
     */
    public function overview(AnalyticsScope $scope): array
    {
        $current = $this->periodTotals($scope);
        $previous = $this->periodTotals($scope->previous());

        return [
            'learners' => $this->learnerRows($scope)->count(),
            'averageScore' => $current['averageScore'],
            'averageScoreDelta' => $this->pointsDelta($current['averageScore'], $previous['averageScore']),
            'passRate' => $current['passRate'],
            'passRateDelta' => $this->pointsDelta($current['passRate'], $previous['passRate']),
            'results' => $current['results'],
            'attempts' => $current['attempts'],
            'attemptsDelta' => $current['attempts'] - $previous['attempts'],
            'activeLearners' => $current['activeLearners'],
            'activeLearnersDelta' => $current['activeLearners'] - $previous['activeLearners'],
            'missionsCompleted' => $current['missionsCompleted'],
            'missionsCompletedDelta' => $current['missionsCompleted'] - $previous['missionsCompleted'],
            'activitiesCompleted' => $current['activitiesCompleted'],
            'activitiesCompletedDelta' => $current['activitiesCompleted'] - $previous['activitiesCompleted'],
        ];
    }

    /**
     * Get the number of active learner accounts in scope.
     */
    public function learnerCount(AnalyticsScope $scope): int
    {
        return $this->learnerRows($scope)->count();
    }

    /**
     * Get one entry per ISO week (Monday start) of the period up to today, oldest first.
     *
     * averageScore is the mean of every attempt submitted that week (null without attempts).
     *
     * @return list<array{label: string, start: CarbonImmutable, averageScore: float|null, attempts: int, activeLearners: int}>
     */
    public function weeklyTrend(AnalyticsScope $scope): array
    {
        $activity = $this->activity($scope);
        $weekOf = fn (object $row): string => CarbonImmutable::parse($row->day)->startOfWeek(WeekDay::Monday)->toDateString();

        $attempts = $activity['attempts']->groupBy($weekOf);
        $missions = $activity['missions']->groupBy($weekOf);
        $activities = $activity['activities']->groupBy($weekOf);

        $lastWeek = $scope->period->elapsedEnd()->startOfWeek(WeekDay::Monday);
        $weeks = [];

        for ($week = $scope->period->from->startOfWeek(WeekDay::Monday); $week->lessThanOrEqualTo($lastWeek); $week = $week->addWeek()) {
            $key = $week->toDateString();
            $weekAttempts = $attempts->get($key, collect());
            $attemptCount = (int) $weekAttempts->sum('total');

            $weeks[] = [
                'label' => $week->format('M j'),
                'start' => $week,
                'averageScore' => $attemptCount === 0 ? null : round((float) $weekAttempts->sum('scoreSum') / $attemptCount, 1),
                'attempts' => $attemptCount,
                'activeLearners' => $weekAttempts
                    ->concat($missions->get($key, collect()))
                    ->concat($activities->get($key, collect()))
                    ->pluck('studentId')
                    ->unique()
                    ->count(),
            ];
        }

        return $weeks;
    }

    /**
     * Get the number of submitted quiz attempts on each day of the period up to today, oldest first.
     *
     * @return list<array{date: CarbonImmutable, label: string, attempts: int}>
     */
    public function dailyAttempts(AnalyticsScope $scope): array
    {
        $attemptsByDay = $this->activity($scope)['attempts']->groupBy('day');
        $lastDay = $scope->period->elapsedEnd()->startOfDay();
        $days = [];

        for ($day = $scope->period->from->startOfDay(); $day->lessThanOrEqualTo($lastDay); $day = $day->addDay()) {
            $days[] = [
                'date' => $day,
                'label' => $day->format('M j'),
                'attempts' => (int) $attemptsByDay->get($day->toDateString(), collect())->sum('total'),
            ];
        }

        return $days;
    }

    /**
     * Count the quiz results per score bucket of HISTOGRAM_BUCKET_SIZE points ("0–9" … "90–100").
     *
     * A result lands in the bucket of its whole percentage (89.99 counts in "80–89"); 100 belongs to the last one.
     *
     * @return list<array{label: string, value: int}>
     */
    public function scoreDistribution(AnalyticsScope $scope): array
    {
        $bucketCount = intdiv(100, self::HISTOGRAM_BUCKET_SIZE);
        $counts = array_fill(0, $bucketCount, 0);

        foreach ($this->results($scope) as $result) {
            $counts[min($bucketCount - 1, max(0, intdiv((int) floor($result->score), self::HISTOGRAM_BUCKET_SIZE)))]++;
        }

        $buckets = [];

        foreach ($counts as $index => $count) {
            $low = $index * self::HISTOGRAM_BUCKET_SIZE;
            $high = $index === $bucketCount - 1 ? 100 : $low + self::HISTOGRAM_BUCKET_SIZE - 1;
            $buckets[] = ['label' => $low.'–'.$high, 'value' => $count];
        }

        return $buckets;
    }

    /**
     * Count the learners in scope per performance band of their average result (No data without results).
     *
     * @return array<string, int> Keyed by PerformanceBand value, every band present.
     */
    public function bandDistribution(AnalyticsScope $scope): array
    {
        $counts = array_fill_keys(array_map(fn (PerformanceBand $band): string => $band->value, PerformanceBand::cases()), 0);
        $averages = $this->learnerAverages($scope);

        foreach ($averages as $average) {
            $counts[PerformanceBand::fromScore($average)->value]++;
        }

        $counts[PerformanceBand::NoData->value] = max(0, $this->learnerRows($scope)->count() - $averages->count());

        return $counts;
    }

    /**
     * Get the quiz figures per subject in scope (subjects without results included), ordered by name.
     *
     * The subjects are the scoped ones, or school-wide every active subject plus any subject with results.
     * byGrade holds the average per learner grade (GradeLevel value => average or null).
     *
     * @return list<array{subject: Subject, averageScore: float|null, passRate: float|null, results: int, learners: int, byGrade: array<int, float|null>}>
     */
    public function subjectPerformance(AnalyticsScope $scope): array
    {
        $resultsBySubject = $this->results($scope)->groupBy('subjectId');

        $subjects = Subject::query()
            ->when(
                $scope->subjectIds !== null,
                fn (Builder $query) => $query->whereKey($scope->subjectIds),
                fn (Builder $query) => $query->where(fn (Builder $query) => $query->active()->orWhereKey($resultsBySubject->keys()->all())),
            )
            ->orderBy('name')
            ->orderBy('id')
            ->get();

        return $subjects->map(function (Subject $subject) use ($resultsBySubject): array {
            $results = $resultsBySubject->get($subject->getKey(), collect());

            return [
                'subject' => $subject,
                ...$this->resultFigures($results),
                'byGrade' => collect(GradeLevel::cases())->mapWithKeys(fn (GradeLevel $grade): array => [
                    $grade->value => $this->mean($results->where('grade', $grade->value)->pluck('score')),
                ])->all(),
            ];
        })->values()->all();
    }

    /**
     * Get the quiz figures per learner grade (only the scoped grade when the scope has one).
     *
     * @return list<array{grade: GradeLevel, averageScore: float|null, passRate: float|null, results: int, learners: int}>
     */
    public function gradePerformance(AnalyticsScope $scope): array
    {
        $results = $this->results($scope);

        return collect(GradeLevel::cases())
            ->filter(fn (GradeLevel $grade): bool => $scope->grade === null || $scope->grade === $grade)
            ->map(fn (GradeLevel $grade): array => [
                'grade' => $grade,
                ...$this->resultFigures($results->where('grade', $grade->value)),
            ])
            ->values()
            ->all();
    }

    /**
     * Rank the classrooms with results by average result (highest first, then name and id).
     *
     * Each classroom comes with its subject and teacher.
     *
     * @return list<array{classroom: Classroom, averageScore: float|null, passRate: float|null, results: int, learners: int}>
     */
    public function classroomRanking(AnalyticsScope $scope, ?int $limit = null): array
    {
        $resultsByClassroom = $this->results($scope)->whereNotNull('classroomId')->groupBy('classroomId');

        if ($resultsByClassroom->isEmpty()) {
            return [];
        }

        $classrooms = Classroom::query()
            ->whereKey($resultsByClassroom->keys()->all())
            ->with(['subject', 'teacher'])
            ->get();

        return $classrooms
            ->map(fn (Classroom $classroom): array => [
                'classroom' => $classroom,
                ...$this->resultFigures($resultsByClassroom->get($classroom->getKey(), collect())),
            ])
            ->sort(fn (array $first, array $second): int => [$second['averageScore'], $first['classroom']->name, $first['classroom']->getKey()]
                <=> [$first['averageScore'], $second['classroom']->name, $second['classroom']->getKey()])
            ->when($limit !== null, fn (Collection $rows) => $rows->take($limit))
            ->values()
            ->all();
    }

    /**
     * Get the quiz figures per learning module (quizzes linked to a module), weakest first.
     *
     * Each module comes with its subject. Ordered by average result, then grade, sort order and title.
     *
     * @return list<array{module: LearningModule, averageScore: float|null, passRate: float|null, results: int, learners: int}>
     */
    public function moduleMastery(AnalyticsScope $scope): array
    {
        $resultsByModule = $this->results($scope)->whereNotNull('moduleId')->groupBy('moduleId');

        if ($resultsByModule->isEmpty()) {
            return [];
        }

        return LearningModule::query()
            ->whereKey($resultsByModule->keys()->all())
            ->with('subject')
            ->get()
            ->map(fn (LearningModule $module): array => [
                'module' => $module,
                ...$this->resultFigures($resultsByModule->get($module->getKey(), collect())),
            ])
            ->sort(fn (array $first, array $second): int => [$first['averageScore'], $first['module']->grade_level->value, $first['module']->sort_order, $first['module']->title, $first['module']->getKey()]
                <=> [$second['averageScore'], $second['module']->grade_level->value, $second['module']->sort_order, $second['module']->title, $second['module']->getKey()])
            ->values()
            ->all();
    }

    /**
     * Get the questions answered wrongly most often in the period's attempts (highest miss rate first).
     *
     * A miss is an incorrect or blank answer. Only questions answered at least MOST_MISSED_MIN_ANSWERS times and
     * missed at least once are listed. Each question comes with quiz.subject and its most chosen wrong answer
     * (identification answers are compared ignoring case and extra spaces).
     *
     * @return list<array{question: QuizQuestion, answered: int, missed: int, missRate: float, commonWrongAnswer: string|null, commonWrongCount: int}>
     */
    public function mostMissedQuestions(AnalyticsScope $scope, int $limit = 6): array
    {
        $missed = 'SUM(CASE WHEN quiz_attempt_answers.is_correct = 1 THEN 0 ELSE 1 END)';

        $rows = $this->answers($scope)
            ->groupBy('quiz_attempt_answers.quiz_question_id')
            ->select('quiz_attempt_answers.quiz_question_id')
            ->selectRaw('COUNT(*) as answered')
            ->selectRaw($missed.' as missed')
            ->havingRaw('COUNT(*) >= ?', [self::MOST_MISSED_MIN_ANSWERS])
            ->havingRaw($missed.' > 0')
            ->orderByRaw($missed.' * 1.0 / COUNT(*) DESC')
            ->orderByRaw($missed.' DESC')
            ->orderBy('quiz_attempt_answers.quiz_question_id')
            ->limit($limit)
            ->get();

        if ($rows->isEmpty()) {
            return [];
        }

        $questionIds = $rows->pluck('quiz_question_id')->map(fn (mixed $id): int => (int) $id)->all();
        $questions = QuizQuestion::query()->whereKey($questionIds)->with('quiz.subject')->get()->keyBy('id');

        $wrongAnswers = $this->answers($scope)
            ->whereIn('quiz_attempt_answers.quiz_question_id', $questionIds)
            ->where('quiz_attempt_answers.is_correct', false)
            ->whereNotNull('quiz_attempt_answers.answer')
            ->where('quiz_attempt_answers.answer', '<>', '')
            ->groupBy('quiz_attempt_answers.quiz_question_id', 'quiz_attempt_answers.answer')
            ->select('quiz_attempt_answers.quiz_question_id', 'quiz_attempt_answers.answer')
            ->selectRaw('COUNT(*) as total')
            ->get()
            ->groupBy(fn (object $row): int => (int) $row->quiz_question_id);

        return $rows
            ->filter(fn (object $row): bool => $questions->has((int) $row->quiz_question_id))
            ->map(function (object $row) use ($questions, $wrongAnswers): array {
                $question = $questions->get((int) $row->quiz_question_id);
                [$commonAnswer, $commonCount] = $this->commonWrongAnswer($question, $wrongAnswers->get($question->getKey(), collect()));

                return [
                    'question' => $question,
                    'answered' => (int) $row->answered,
                    'missed' => (int) $row->missed,
                    'missRate' => round((int) $row->missed / (int) $row->answered * 100, 1),
                    'commonWrongAnswer' => $commonAnswer,
                    'commonWrongCount' => $commonCount,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * Get the learners who need attention: an average result below 75 (Did Not Meet Expectations) in the period,
     * or no game activity for INACTIVE_AFTER_DAYS days or more (never played included).
     *
     * Ordered by low score first (lowest average first), then the longest inactive, then name. count, lowScore and
     * inactive cover every flagged learner; learners holds the first $limit of them with studentProfile loaded.
     *
     * @return array{count: int, lowScore: int, inactive: int, learners: list<array{student: User, averageScore: float|null, band: PerformanceBand, lowScore: bool, inactive: bool, lastActiveAt: CarbonImmutable|null}>}
     */
    public function atRiskLearners(AnalyticsScope $scope, ?int $limit = null): array
    {
        $averages = $this->learnerAverages($scope);
        $inactiveSince = now()->toImmutable()->subDays(self::INACTIVE_AFTER_DAYS);

        $flagged = $this->learnerRows($scope)
            ->map(function (object $learner) use ($averages, $inactiveSince): ?object {
                $average = $averages->get($learner->id);
                $lowScore = $average !== null && PerformanceBand::fromScore($average)->needsAttention();
                $inactive = $learner->lastActiveAt === null || $learner->lastActiveAt->lessThanOrEqualTo($inactiveSince);

                return $lowScore || $inactive
                    ? (object) ['learner' => $learner, 'average' => $average, 'lowScore' => $lowScore, 'inactive' => $inactive]
                    : null;
            })
            ->filter()
            ->sort(fn (object $first, object $second): int => [
                $second->lowScore,
                $first->lowScore ? $first->average : INF,
                $first->learner->lastActiveAt?->getTimestamp() ?? PHP_INT_MIN,
                Str::lower($first->learner->name),
                $first->learner->id,
            ] <=> [
                $first->lowScore,
                $second->lowScore ? $second->average : INF,
                $second->learner->lastActiveAt?->getTimestamp() ?? PHP_INT_MIN,
                Str::lower($second->learner->name),
                $second->learner->id,
            ])
            ->values();

        $listed = $limit === null ? $flagged : $flagged->take($limit);
        $students = $listed->isEmpty()
            ? collect()
            : User::query()->whereKey($listed->pluck('learner.id')->all())->with('studentProfile')->get()->keyBy('id');

        return [
            'count' => $flagged->count(),
            'lowScore' => $flagged->where('lowScore', true)->count(),
            'inactive' => $flagged->where('inactive', true)->count(),
            'learners' => $listed->map(fn (object $entry): array => [
                'student' => $students->get($entry->learner->id),
                'averageScore' => $entry->average,
                'band' => PerformanceBand::fromScore($entry->average),
                'lowScore' => $entry->lowScore,
                'inactive' => $entry->inactive,
                'lastActiveAt' => $entry->learner->lastActiveAt,
            ])->values()->all(),
        ];
    }

    /**
     * Rank the learners in scope by total game points (learners without points are left out).
     *
     * Ties share a rank (1, 1, 3) and are listed by name. Levels follow the current level settings.
     *
     * @return list<array{student: User, rank: int, points: int, level: int}>
     */
    public function leaderboard(AnalyticsScope $scope, int $limit = 10): array
    {
        $rows = $this->learnerRows($scope)
            ->filter(fn (object $learner): bool => $learner->points > 0)
            ->sort(fn (object $first, object $second): int => [$second->points, Str::lower($first->name), $first->id]
                <=> [$first->points, Str::lower($second->name), $second->id])
            ->take($limit)
            ->values();

        if ($rows->isEmpty()) {
            return [];
        }

        $students = User::query()->whereKey($rows->pluck('id')->all())->get()->keyBy('id');
        $entries = [];
        $previousPoints = null;
        $rank = 0;

        foreach ($rows as $position => $row) {
            if ($row->points !== $previousPoints) {
                $rank = $position + 1;
                $previousPoints = $row->points;
            }

            $entries[] = [
                'student' => $students->get($row->id),
                'rank' => $rank,
                'points' => $row->points,
                'level' => $this->levels->levelFor($row->points),
            ];
        }

        return $entries;
    }

    /**
     * Count the period's quiz attempts, mission completions and activity completions per weekday, Monday first.
     *
     * @return list<array{label: string, attempts: int, missions: int, activities: int}>
     */
    public function weekdayEngagement(AnalyticsScope $scope): array
    {
        $activity = $this->activity($scope);
        $weekdayOf = fn (object $row): int => CarbonImmutable::parse($row->day)->dayOfWeekIso;

        $totals = collect($activity)->map(fn (Collection $rows): Collection => $rows
            ->groupBy($weekdayOf)
            ->map(fn (Collection $dayRows): int => (int) $dayRows->sum('total')));

        return collect(self::WEEKDAYS)->map(fn (string $label, int $index): array => [
            'label' => $label,
            'attempts' => (int) $totals['attempts']->get($index + 1, 0),
            'missions' => (int) $totals['missions']->get($index + 1, 0),
            'activities' => (int) $totals['activities']->get($index + 1, 0),
        ])->all();
    }

    /**
     * Get how many of the missions and activities learners started in the period are completed (by now).
     *
     * A mission starts at started_at (created_at when missing), an activity at its first progress record.
     *
     * @return array{missions: array{started: int, completed: int, rate: float|null}, activities: array{started: int, completed: int, rate: float|null}}
     */
    public function completion(AnalyticsScope $scope): array
    {
        $range = [$scope->period->from, $scope->period->to];
        $completedCount = 'SUM(CASE WHEN %s.status = ? THEN 1 ELSE 0 END) as completed';

        $missions = $this->missionProgress($scope)
            ->whereBetween(DB::raw('COALESCE(mission_progress.started_at, mission_progress.created_at)'), $range)
            ->selectRaw('COUNT(*) as started')
            ->selectRaw(sprintf($completedCount, 'mission_progress'), [ProgressStatus::Completed->value])
            ->first();

        $activities = $this->activityProgress($scope)
            ->whereBetween('activity_progress.created_at', $range)
            ->selectRaw('COUNT(*) as started')
            ->selectRaw(sprintf($completedCount, 'activity_progress'), [ProgressStatus::Completed->value])
            ->first();

        return [
            'missions' => $this->completionFigures($missions),
            'activities' => $this->completionFigures($activities),
        ];
    }

    /**
     * Get the offline-sync health of the learners in scope (current state) and the period's offline attempts.
     *
     * upToDate: synced within LearnerProgressSummary::SYNC_OVERDUE_AFTER_DAYS days; overdue: last sync older than
     * that; neverSynced: played but never synced; notPlayed: never played nor synced. offlineShare is the
     * percentage of the period's attempts that were played offline.
     *
     * @return array{learners: int, upToDate: int, overdue: int, neverSynced: int, notPlayed: int, attempts: int, offlineAttempts: int, offlineShare: float|null}
     */
    public function syncHealth(AnalyticsScope $scope): array
    {
        $overdueBefore = now()->toImmutable()->subDays(LearnerProgressSummary::SYNC_OVERDUE_AFTER_DAYS);
        $learners = $this->learnerRows($scope);
        $attempts = $this->activity($scope)['attempts'];
        $attemptCount = (int) $attempts->sum('total');
        $offlineCount = (int) $attempts->sum('offline');

        return [
            'learners' => $learners->count(),
            'upToDate' => $learners->filter(fn (object $learner): bool => $learner->lastSyncedAt?->greaterThanOrEqualTo($overdueBefore) ?? false)->count(),
            'overdue' => $learners->filter(fn (object $learner): bool => $learner->lastSyncedAt?->lessThan($overdueBefore) ?? false)->count(),
            'neverSynced' => $learners->filter(fn (object $learner): bool => $learner->lastSyncedAt === null && $learner->lastActiveAt !== null)->count(),
            'notPlayed' => $learners->filter(fn (object $learner): bool => $learner->lastSyncedAt === null && $learner->lastActiveAt === null)->count(),
            'attempts' => $attemptCount,
            'offlineAttempts' => $offlineCount,
            'offlineShare' => $this->share($offlineCount, $attemptCount),
        ];
    }

    /**
     * Page through the active teachers (school-wide view) with what they did in the period.
     *
     * Counts: quizzes created and published in the period, feedback written in the period and non-archived
     * classrooms. A subject or grade in scope limits the teachers to those assigned the subject and every count
     * to quizzes/classrooms of that subject and grade (feedback through its classroom). Ordered by name.
     *
     * @return LengthAwarePaginator<int, User>
     */
    public function teacherActivity(AnalyticsScope $scope, int $perPage = 10, string $pageName = 'teachers'): LengthAwarePaginator
    {
        $range = [$scope->period->from, $scope->period->to];

        $inScope = fn (Builder $query, string $table): Builder => $query
            ->when($scope->subjectIds !== null, fn (Builder $query) => $query->whereIn($table.'.subject_id', $scope->subjectIds))
            ->when($scope->grade !== null, fn (Builder $query) => $query->where($table.'.grade_level', $scope->grade?->value));

        return User::query()
            ->ofRole(UserRole::Teacher)
            ->active()
            ->when($scope->subjectIds !== null, fn (Builder $query) => $query->whereHas(
                'assignedSubjects',
                fn (Builder $subjects) => $subjects->whereIn('subjects.id', $scope->subjectIds),
            ))
            ->withCount([
                'quizzes as quizzes_created_count' => fn (Builder $query) => $inScope($query, 'quizzes')->whereBetween('quizzes.created_at', $range),
                'quizzes as quizzes_published_count' => fn (Builder $query) => $inScope($query, 'quizzes')->whereBetween('quizzes.published_at', $range),
                'feedbackGiven as feedback_sent_count' => fn (Builder $query) => $query
                    ->whereBetween('learner_feedback.created_at', $range)
                    ->when(
                        $scope->subjectIds !== null || $scope->grade !== null,
                        fn (Builder $query) => $query->whereHas('classroom', fn (Builder $classroom) => $inScope($classroom, 'classrooms')),
                    ),
                'classrooms as active_classrooms_count' => fn (Builder $query) => $inScope($query, 'classrooms')->whereNull('classrooms.archived_at'),
            ])
            ->orderBy('name')
            ->orderBy('id')
            ->paginate($perPage, pageName: $pageName)
            ->withQueryString();
    }

    /**
     * Get the period's average, pass rate and counts used by overview().
     *
     * @return array{averageScore: float|null, passRate: float|null, results: int, attempts: int, activeLearners: int, missionsCompleted: int, activitiesCompleted: int}
     */
    private function periodTotals(AnalyticsScope $scope): array
    {
        $results = $this->results($scope);
        $activity = $this->activity($scope);

        return [
            'averageScore' => $this->mean($results->pluck('score')),
            'passRate' => $this->share($results->where('passed', true)->count(), $results->count()),
            'results' => $results->count(),
            'attempts' => (int) $activity['attempts']->sum('total'),
            'activeLearners' => $activity['attempts']
                ->concat($activity['missions'])
                ->concat($activity['activities'])
                ->pluck('studentId')
                ->unique()
                ->count(),
            'missionsCompleted' => (int) $activity['missions']->sum('total'),
            'activitiesCompleted' => (int) $activity['activities']->sum('total'),
        ];
    }

    /**
     * Get the average, pass rate, result count and learner count of a set of results.
     *
     * @param  Collection<int, object>  $results
     * @return array{averageScore: float|null, passRate: float|null, results: int, learners: int}
     */
    private function resultFigures(Collection $results): array
    {
        return [
            'averageScore' => $this->mean($results->pluck('score')),
            'passRate' => $this->share($results->where('passed', true)->count(), $results->count()),
            'results' => $results->count(),
            'learners' => $results->pluck('studentId')->unique()->count(),
        ];
    }

    /**
     * Get each learner's mean result (1 decimal), keyed by learner id; learners without results are absent.
     *
     * @return Collection<int, float>
     */
    private function learnerAverages(AnalyticsScope $scope): Collection
    {
        return $this->results($scope)
            ->groupBy('studentId')
            ->map(fn (Collection $results): float => (float) $this->mean($results->pluck('score')));
    }

    /**
     * Get the quiz results in scope: the best submitted attempt per learner, quiz and classroom in the period.
     *
     * @return Collection<int, object{studentId: int, quizId: int, classroomId: int|null, subjectId: int, moduleId: int|null, grade: int|null, score: float, passed: bool, attempts: int}>
     */
    private function results(AnalyticsScope $scope): Collection
    {
        return $this->resultsMemo[$scope->cacheKey()] ??= $this->attempts($scope)
            ->leftJoin('student_profiles', 'student_profiles.user_id', '=', 'quiz_attempts.student_id')
            ->groupBy(
                'quiz_attempts.student_id',
                'quiz_attempts.quiz_id',
                'quiz_attempts.classroom_id',
                'quizzes.subject_id',
                'quizzes.learning_module_id',
                'student_profiles.grade_level',
            )
            ->select([
                'quiz_attempts.student_id',
                'quiz_attempts.quiz_id',
                'quiz_attempts.classroom_id',
                'quizzes.subject_id',
                'quizzes.learning_module_id',
                'student_profiles.grade_level',
            ])
            ->selectRaw('MAX(quiz_attempts.percentage) as best_percentage')
            ->selectRaw('MAX(quiz_attempts.passed) as passed')
            ->selectRaw('COUNT(*) as attempt_count')
            ->get()
            ->map(fn (object $row): object => (object) [
                'studentId' => (int) $row->student_id,
                'quizId' => (int) $row->quiz_id,
                'classroomId' => $row->classroom_id === null ? null : (int) $row->classroom_id,
                'subjectId' => (int) $row->subject_id,
                'moduleId' => $row->learning_module_id === null ? null : (int) $row->learning_module_id,
                'grade' => $row->grade_level === null ? null : (int) $row->grade_level,
                'score' => (float) $row->best_percentage,
                'passed' => (bool) $row->passed,
                'attempts' => (int) $row->attempt_count,
            ]);
    }

    /**
     * Get the period's attempts, mission completions and activity completions per day and learner.
     *
     * @return array{attempts: Collection<int, object{day: string, studentId: int, total: int, scoreSum: float, offline: int}>, missions: Collection<int, object{day: string, studentId: int, total: int}>, activities: Collection<int, object{day: string, studentId: int, total: int}>}
     */
    private function activity(AnalyticsScope $scope): array
    {
        if (isset($this->activityMemo[$scope->cacheKey()])) {
            return $this->activityMemo[$scope->cacheKey()];
        }

        $range = [$scope->period->from, $scope->period->to];

        $attempts = $this->attempts($scope)
            ->selectRaw('DATE(quiz_attempts.submitted_at) as day')
            ->addSelect('quiz_attempts.student_id')
            ->selectRaw('COUNT(*) as total')
            ->selectRaw('SUM(quiz_attempts.percentage) as score_sum')
            ->selectRaw('SUM(CASE WHEN quiz_attempts.is_offline = 1 THEN 1 ELSE 0 END) as offline_total')
            ->groupByRaw('DATE(quiz_attempts.submitted_at), quiz_attempts.student_id')
            ->get()
            ->map(fn (object $row): object => (object) [
                'day' => (string) $row->day,
                'studentId' => (int) $row->student_id,
                'total' => (int) $row->total,
                'scoreSum' => (float) $row->score_sum,
                'offline' => (int) $row->offline_total,
            ]);

        $missions = $this->missionProgress($scope)
            ->where('mission_progress.status', ProgressStatus::Completed->value)
            ->whereBetween('mission_progress.completed_at', $range)
            ->selectRaw('DATE(mission_progress.completed_at) as day')
            ->addSelect('mission_progress.student_id')
            ->selectRaw('COUNT(*) as total')
            ->groupByRaw('DATE(mission_progress.completed_at), mission_progress.student_id')
            ->get()
            ->map(fn (object $row): object => (object) ['day' => (string) $row->day, 'studentId' => (int) $row->student_id, 'total' => (int) $row->total]);

        $activities = $this->activityProgress($scope)
            ->where('activity_progress.status', ProgressStatus::Completed->value)
            ->whereBetween('activity_progress.completed_at', $range)
            ->selectRaw('DATE(activity_progress.completed_at) as day')
            ->addSelect('activity_progress.student_id')
            ->selectRaw('COUNT(*) as total')
            ->groupByRaw('DATE(activity_progress.completed_at), activity_progress.student_id')
            ->get()
            ->map(fn (object $row): object => (object) ['day' => (string) $row->day, 'studentId' => (int) $row->student_id, 'total' => (int) $row->total]);

        return $this->activityMemo[$scope->cacheKey()] = ['attempts' => $attempts, 'missions' => $missions, 'activities' => $activities];
    }

    /**
     * Get the active learner accounts in scope with their points and game timestamps, keyed by id.
     *
     * @return Collection<int, object{id: int, name: string, points: int, lastActiveAt: CarbonImmutable|null, lastSyncedAt: CarbonImmutable|null}>
     */
    private function learnerRows(AnalyticsScope $scope): Collection
    {
        return $this->learnerMemo[$scope->populationKey()] ??= $this->learnerQuery($scope)
            ->leftJoin('student_profiles', 'student_profiles.user_id', '=', 'users.id')
            ->toBase()
            ->get(['users.id', 'users.name', 'student_profiles.total_points', 'student_profiles.last_active_at', 'student_profiles.last_synced_at'])
            ->map(fn (object $row): object => (object) [
                'id' => (int) $row->id,
                'name' => (string) $row->name,
                'points' => (int) ($row->total_points ?? 0),
                'lastActiveAt' => $row->last_active_at === null ? null : CarbonImmutable::parse($row->last_active_at),
                'lastSyncedAt' => $row->last_synced_at === null ? null : CarbonImmutable::parse($row->last_synced_at),
            ])
            ->keyBy('id');
    }

    /**
     * Get the query of the active learner accounts in scope.
     *
     * @return Builder<User>
     */
    private function learnerQuery(AnalyticsScope $scope): Builder
    {
        return User::query()
            ->ofRole(UserRole::Student)
            ->active()
            ->when($scope->grade !== null, fn (Builder $query) => $query->whereHas(
                'studentProfile',
                fn (Builder $profile) => $profile->where('grade_level', $scope->grade?->value),
            ))
            ->when($scope->classroomIds !== null, fn (Builder $query) => $query->whereHas(
                'enrolledClassrooms',
                fn (Builder $classrooms) => $classrooms->whereIn('classrooms.id', $scope->classroomIds),
            ))
            ->when($scope->classroomIds === null && $scope->subjectIds !== null, fn (Builder $query) => $query->whereHas(
                'enrolledClassrooms',
                fn (Builder $classrooms) => $classrooms->active()->whereIn('classrooms.subject_id', $scope->subjectIds),
            ));
    }

    /**
     * Get the submitted attempts in scope, joined with their quizzes.
     */
    private function attempts(AnalyticsScope $scope): QueryBuilder
    {
        return $this->constrainAttempts(
            QuizAttempt::query()->toBase()->join('quizzes', 'quizzes.id', '=', 'quiz_attempts.quiz_id'),
            $scope,
        );
    }

    /**
     * Get the answers of the submitted attempts in scope, joined with their attempts and quizzes.
     */
    private function answers(AnalyticsScope $scope): QueryBuilder
    {
        return $this->constrainAttempts(
            QuizAttemptAnswer::query()->toBase()
                ->join('quiz_attempts', 'quiz_attempts.id', '=', 'quiz_attempt_answers.quiz_attempt_id')
                ->join('quizzes', 'quizzes.id', '=', 'quiz_attempts.quiz_id'),
            $scope,
        );
    }

    /**
     * Limit a query that joins quiz_attempts and quizzes to the submitted attempts in scope.
     */
    private function constrainAttempts(QueryBuilder $query, AnalyticsScope $scope): QueryBuilder
    {
        return $query
            ->whereNotNull('quiz_attempts.submitted_at')
            ->whereBetween('quiz_attempts.submitted_at', [$scope->period->from, $scope->period->to])
            ->whereIn('quiz_attempts.student_id', $this->learnerQuery($scope)->select('users.id'))
            ->when($scope->classroomIds !== null, fn (QueryBuilder $query) => $query->whereIn('quiz_attempts.classroom_id', $scope->classroomIds))
            ->when($scope->subjectIds !== null, fn (QueryBuilder $query) => $query->whereIn('quizzes.subject_id', $scope->subjectIds));
    }

    /**
     * Get the mission progress of the learners in scope on missions of the scoped subjects.
     */
    private function missionProgress(AnalyticsScope $scope): QueryBuilder
    {
        return MissionProgress::query()->toBase()
            ->join('missions', 'missions.id', '=', 'mission_progress.mission_id')
            ->whereIn('mission_progress.student_id', $this->learnerQuery($scope)->select('users.id'))
            ->when($scope->subjectIds !== null, fn (QueryBuilder $query) => $query->whereIn('missions.subject_id', $scope->subjectIds));
    }

    /**
     * Get the activity progress of the learners in scope on activities of modules of the scoped subjects.
     */
    private function activityProgress(AnalyticsScope $scope): QueryBuilder
    {
        return ActivityProgress::query()->toBase()
            ->join('learning_activities', 'learning_activities.id', '=', 'activity_progress.learning_activity_id')
            ->join('learning_modules', 'learning_modules.id', '=', 'learning_activities.learning_module_id')
            ->whereIn('activity_progress.student_id', $this->learnerQuery($scope)->select('users.id'))
            ->when($scope->subjectIds !== null, fn (QueryBuilder $query) => $query->whereIn('learning_modules.subject_id', $scope->subjectIds));
    }

    /**
     * Pick the most chosen wrong answer of a question as display text with its count (null when none).
     *
     * @param  Collection<int, object{answer: string, total: int|string}>  $answers
     * @return array{0: string|null, 1: int}
     */
    private function commonWrongAnswer(QuizQuestion $question, Collection $answers): array
    {
        $isTyped = $question->type === QuestionType::Identification;

        $groups = $answers
            ->groupBy(fn (object $row): string => $isTyped ? Str::lower(Str::squish((string) $row->answer)) : trim((string) $row->answer))
            ->map(fn (Collection $rows, int|string $key): array => ['key' => (string) $key, 'answer' => (string) $rows->first()->answer, 'total' => (int) $rows->sum('total')])
            ->filter(fn (array $group): bool => $group['key'] !== '')
            ->sort(fn (array $first, array $second): int => [$second['total'], $first['key']] <=> [$first['total'], $second['key']]);

        $top = $groups->first();

        return $top === null ? [null, 0] : [$question->answerText($top['answer']), $top['total']];
    }

    /**
     * Get the started/completed counts and completion rate of an aggregate row.
     *
     * @return array{started: int, completed: int, rate: float|null}
     */
    private function completionFigures(?object $row): array
    {
        $started = (int) ($row->started ?? 0);
        $completed = (int) ($row->completed ?? 0);

        return ['started' => $started, 'completed' => $completed, 'rate' => $this->share($completed, $started)];
    }

    /**
     * Get the mean of the values rounded to 1 decimal, or null when there are none.
     *
     * @param  Collection<int, float>  $values
     */
    private function mean(Collection $values): ?float
    {
        return $values->isEmpty() ? null : round((float) $values->avg(), 1);
    }

    /**
     * Get part / whole as a percentage rounded to 1 decimal, or null when the whole is zero.
     */
    private function share(int $part, int $whole): ?float
    {
        return $whole === 0 ? null : round($part / $whole * 100, 1);
    }

    /**
     * Get the change between two percentages in points, or null when either is missing.
     */
    private function pointsDelta(?float $current, ?float $previous): ?float
    {
        return $current === null || $previous === null ? null : round($current - $previous, 1);
    }
}
