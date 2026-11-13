<?php

namespace App\Services;

use App\Enums\GradeLevel;
use App\Enums\PerformanceBand;
use App\Enums\ProgressStatus;
use App\Models\ActivityProgress;
use App\Models\Classroom;
use App\Models\LearningActivity;
use App\Models\MissionProgress;
use App\Models\QuizAttempt;
use App\Models\Subject;
use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\WeekDay;
use Illuminate\Container\Attributes\Scoped;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;

/**
 * Builds the progress summary the admin, teacher and parent portals show for one learner.
 *
 * The work is a fixed number of queries whatever the learner's history size: profile, classrooms (+ subjects),
 * submitted attempts, recent attempts (+ quizzes, subjects), mission counts, recent missions (+ missions, subjects),
 * activity totals and completions, and rewards. Weeks and best attempts are computed in PHP so the SQL stays
 * database-agnostic.
 */
#[Scoped]
class LearnerProgressService
{
    /**
     * How many recent attempts and mission progress records the summary lists.
     */
    public const RECENT_LIMIT = 5;

    /**
     * How many ISO weeks the weekly score series covers, ending with the current week.
     */
    public const WEEKS = 8;

    public function __construct(private LevelCalculator $levels) {}

    /**
     * Summarize the learner's progress, optionally limited to the given subjects (the teacher view).
     *
     * Null covers every subject; an array (even an empty one) limits every quiz, mission and activity figure and
     * the classrooms to those subjects. Points, level, rewards and the activity timestamps always describe the
     * whole profile.
     *
     * @param  list<int>|null  $subjectIds
     */
    public function summarize(User $student, ?array $subjectIds = null): LearnerProgressSummary
    {
        $subjectIds = $subjectIds === null
            ? null
            : array_values(array_unique(array_map(fn (mixed $subjectId): int => (int) $subjectId, $subjectIds)));

        $profile = $student->loadMissing('studentProfile')->studentProfile;
        $totalPoints = (int) ($profile?->total_points ?? 0);
        $levelProgress = $this->levels->progress($totalPoints);

        $classrooms = $this->classrooms($student, $subjectIds);
        $subjects = $this->subjectsOf($classrooms);

        $attempts = $this->submittedAttempts($student, $subjectIds);
        $bestAttempts = $this->bestAttemptPerQuiz($attempts);
        $averageScore = $this->averageOf($bestAttempts);

        $missionCounts = $this->missionCounts($student, $subjectIds);
        $activityCounts = $this->activityCounts($student, $profile?->grade_level, $subjects->modelKeys());
        $rewards = $student->rewards()->orderByPivot('awarded_at', 'desc')->orderByDesc('rewards.id')->get();

        return new LearnerProgressSummary(
            student: $student,
            classrooms: $classrooms,
            totalPoints: $totalPoints,
            level: $levelProgress['level'],
            maxLevel: $this->levels->maxLevel(),
            pointsIntoLevel: $levelProgress['into'],
            pointsForNextLevel: $levelProgress['needed'],
            levelPercent: $levelProgress['percent'],
            levelProgress: $levelProgress,
            quizzesTaken: $bestAttempts->count(),
            quizzesPassed: $bestAttempts->filter(fn (QuizAttempt $attempt): bool => $attempt->passed)->count(),
            averageScore: $averageScore,
            band: PerformanceBand::fromScore($averageScore),
            missionsCompleted: (int) $missionCounts->where('status', ProgressStatus::Completed->value)->sum('aggregate'),
            missionsInProgress: (int) $missionCounts->where('status', ProgressStatus::InProgress->value)->sum('aggregate'),
            activitiesCompleted: (int) $activityCounts['completed']->sum(),
            activitiesTotal: (int) $activityCounts['total']->sum(),
            rewardsEarned: $rewards->count(),
            lastActiveAt: $profile?->last_active_at,
            lastSyncedAt: $profile?->last_synced_at,
            weeklyScores: $this->weeklyScores($attempts),
            subjectBreakdown: $this->subjectBreakdown($subjects, $bestAttempts, $missionCounts, $activityCounts),
            recentAttempts: $this->recentAttempts($student, $subjectIds),
            recentMissions: $this->recentMissions($student, $subjectIds),
            rewards: $rewards,
        );
    }

    /**
     * Get the subjects a teacher may follow for a learner: those of the teacher's classrooms the learner is enrolled
     * in, limited to subjects still assigned to the teacher (archived classrooms count, like UserPolicy::viewAsTeacher).
     *
     * Pass the result to summarize() for the teacher's view of the learner.
     *
     * @return list<int>
     */
    public function teacherSubjectIds(User $teacher, User $student): array
    {
        return $student->enrolledClassrooms()
            ->accessibleBy($teacher)
            ->pluck('classrooms.subject_id')
            ->map(fn (mixed $subjectId): int => (int) $subjectId)
            ->unique()
            ->sort()
            ->values()
            ->all();
    }

    /**
     * Get the learner's active classrooms (in the given subjects) with their subjects, ordered by name.
     *
     * @param  list<int>|null  $subjectIds
     * @return EloquentCollection<int, Classroom>
     */
    private function classrooms(User $student, ?array $subjectIds): EloquentCollection
    {
        return $student->enrolledClassrooms()
            ->active()
            ->when($subjectIds !== null, fn (Builder $query) => $query->whereIn('classrooms.subject_id', $subjectIds))
            ->with('subject')
            ->orderBy('classrooms.name')
            ->orderBy('classrooms.id')
            ->get();
    }

    /**
     * Get the distinct subjects of the classrooms ordered by name.
     *
     * @param  EloquentCollection<int, Classroom>  $classrooms
     * @return EloquentCollection<int, Subject>
     */
    private function subjectsOf(EloquentCollection $classrooms): EloquentCollection
    {
        return new EloquentCollection($classrooms
            ->map(fn (Classroom $classroom): Subject => $classroom->subject)
            ->unique(fn (Subject $subject): int => $subject->getKey())
            ->sort(fn (Subject $first, Subject $second): int => strcasecmp($first->name, $second->name) ?: $first->getKey() <=> $second->getKey())
            ->values()
            ->all());
    }

    /**
     * Get the learner's submitted attempts (in the given subjects) with the subject of each quiz.
     *
     * @param  list<int>|null  $subjectIds
     * @return EloquentCollection<int, QuizAttempt>
     */
    private function submittedAttempts(User $student, ?array $subjectIds): EloquentCollection
    {
        return $student->quizAttempts()
            ->submitted()
            ->join('quizzes', 'quizzes.id', '=', 'quiz_attempts.quiz_id')
            ->when($subjectIds !== null, fn (Builder $query) => $query->whereIn('quizzes.subject_id', $subjectIds))
            ->get([
                'quiz_attempts.id',
                'quiz_attempts.quiz_id',
                'quiz_attempts.percentage',
                'quiz_attempts.passed',
                'quiz_attempts.submitted_at',
                'quizzes.subject_id',
            ]);
    }

    /**
     * Pick each quiz's best attempt: the highest percentage, then a passing one, then the latest.
     *
     * @param  EloquentCollection<int, QuizAttempt>  $attempts
     * @return Collection<int, QuizAttempt>
     */
    private function bestAttemptPerQuiz(EloquentCollection $attempts): Collection
    {
        return $attempts->toBase()
            ->groupBy(fn (QuizAttempt $attempt): int => (int) $attempt->quiz_id)
            ->map(fn (Collection $quizAttempts): QuizAttempt => $quizAttempts
                ->sortByDesc(fn (QuizAttempt $attempt): array => [(float) $attempt->percentage, $attempt->passed, $attempt->getKey()])
                ->first());
    }

    /**
     * Get the mean percentage of the attempts rounded to 1 decimal, or null when there are none.
     *
     * @param  Collection<int, QuizAttempt>  $attempts
     */
    private function averageOf(Collection $attempts): ?float
    {
        if ($attempts->isEmpty()) {
            return null;
        }

        return round((float) $attempts->avg(fn (QuizAttempt $attempt): float => (float) $attempt->percentage), 1);
    }

    /**
     * Count the learner's completed and in-progress missions per mission subject (in the given subjects).
     *
     * @param  list<int>|null  $subjectIds
     * @return Collection<int, object{subject_id: int|string, status: string, aggregate: int|string}>
     */
    private function missionCounts(User $student, ?array $subjectIds): Collection
    {
        return $student->missionProgress()
            ->join('missions', 'missions.id', '=', 'mission_progress.mission_id')
            ->whereIn('mission_progress.status', [ProgressStatus::Completed->value, ProgressStatus::InProgress->value])
            ->when($subjectIds !== null, fn (Builder $query) => $query->whereIn('missions.subject_id', $subjectIds))
            ->toBase()
            ->select(['missions.subject_id', 'mission_progress.status'])
            ->selectRaw('count(*) as aggregate')
            ->groupBy('missions.subject_id', 'mission_progress.status')
            ->get();
    }

    /**
     * Count the activities the learner can play and has completed, per subject.
     *
     * Only published activities of published modules for the learner's grade in the given subjects count, so the
     * completed figure never exceeds the total.
     *
     * @param  list<int>  $subjectIds
     * @return array{total: Collection<int, int|string>, completed: Collection<int, int|string>}
     */
    private function activityCounts(User $student, ?GradeLevel $grade, array $subjectIds): array
    {
        if ($grade === null || $subjectIds === []) {
            return ['total' => collect(), 'completed' => collect()];
        }

        $catalog = fn (Builder $query) => $query
            ->join('learning_modules', 'learning_modules.id', '=', 'learning_activities.learning_module_id')
            ->where('learning_activities.is_published', true)
            ->where('learning_modules.is_published', true)
            ->where('learning_modules.grade_level', $grade->value)
            ->whereIn('learning_modules.subject_id', $subjectIds);

        $total = LearningActivity::query()
            ->tap($catalog)
            ->toBase()
            ->select('learning_modules.subject_id')
            ->selectRaw('count(*) as aggregate')
            ->groupBy('learning_modules.subject_id')
            ->pluck('aggregate', 'subject_id');

        $completed = ActivityProgress::query()
            ->whereBelongsTo($student, 'student')
            ->completed()
            ->join('learning_activities', 'learning_activities.id', '=', 'activity_progress.learning_activity_id')
            ->tap($catalog)
            ->toBase()
            ->select('learning_modules.subject_id')
            ->selectRaw('count(*) as aggregate')
            ->groupBy('learning_modules.subject_id')
            ->pluck('aggregate', 'subject_id');

        return ['total' => $total, 'completed' => $completed];
    }

    /**
     * Bucket the attempts into the last ISO weeks: mean percentage (1 decimal) and attempt count per week.
     *
     * @param  EloquentCollection<int, QuizAttempt>  $attempts
     * @return list<array{label: string, start: CarbonImmutable, average: float|null, attempts: int}>
     */
    private function weeklyScores(EloquentCollection $attempts): array
    {
        $currentWeek = now()->toImmutable()->startOfWeek(WeekDay::Monday);

        $attemptsByWeek = $attempts->toBase()->groupBy(
            fn (QuizAttempt $attempt): string => $attempt->submitted_at->toImmutable()->startOfWeek(WeekDay::Monday)->toDateString(),
        );

        $weeks = [];

        for ($weeksAgo = self::WEEKS - 1; $weeksAgo >= 0; $weeksAgo--) {
            $start = $currentWeek->subWeeks($weeksAgo);
            $weekAttempts = $attemptsByWeek->get($start->toDateString(), collect());

            $weeks[] = [
                'label' => $start->format('M j'),
                'start' => $start,
                'average' => $this->averageOf($weekAttempts),
                'attempts' => $weekAttempts->count(),
            ];
        }

        return $weeks;
    }

    /**
     * Build one entry per subject with its quiz, mission and activity figures.
     *
     * @param  EloquentCollection<int, Subject>  $subjects
     * @param  Collection<int, QuizAttempt>  $bestAttempts
     * @param  Collection<int, object{subject_id: int|string, status: string, aggregate: int|string}>  $missionCounts
     * @param  array{total: Collection<int, int|string>, completed: Collection<int, int|string>}  $activityCounts
     * @return list<array{subject: Subject, averageScore: float|null, quizzesTaken: int, missionsCompleted: int, activitiesCompleted: int, activitiesTotal: int}>
     */
    private function subjectBreakdown(EloquentCollection $subjects, Collection $bestAttempts, Collection $missionCounts, array $activityCounts): array
    {
        $bestAttemptsBySubject = $bestAttempts->groupBy(fn (QuizAttempt $attempt): int => (int) $attempt->subject_id);

        $missionsCompletedBySubject = $missionCounts
            ->where('status', ProgressStatus::Completed->value)
            ->mapWithKeys(fn (object $row): array => [(int) $row->subject_id => (int) $row->aggregate]);

        return $subjects->map(function (Subject $subject) use ($bestAttemptsBySubject, $missionsCompletedBySubject, $activityCounts): array {
            $subjectAttempts = $bestAttemptsBySubject->get($subject->getKey(), collect());

            return [
                'subject' => $subject,
                'averageScore' => $this->averageOf($subjectAttempts),
                'quizzesTaken' => $subjectAttempts->count(),
                'missionsCompleted' => $missionsCompletedBySubject->get($subject->getKey(), 0),
                'activitiesCompleted' => (int) $activityCounts['completed']->get($subject->getKey(), 0),
                'activitiesTotal' => (int) $activityCounts['total']->get($subject->getKey(), 0),
            ];
        })->values()->all();
    }

    /**
     * Get the learner's latest submitted attempts (in the given subjects) with quiz.subject loaded.
     *
     * @param  list<int>|null  $subjectIds
     * @return EloquentCollection<int, QuizAttempt>
     */
    private function recentAttempts(User $student, ?array $subjectIds): EloquentCollection
    {
        return $student->quizAttempts()
            ->submitted()
            ->when($subjectIds !== null, fn (Builder $query) => $query->whereHas(
                'quiz',
                fn (Builder $quiz) => $quiz->whereIn('subject_id', $subjectIds),
            ))
            ->with('quiz.subject')
            ->latest('submitted_at')
            ->orderByDesc('id')
            ->limit(self::RECENT_LIMIT)
            ->get();
    }

    /**
     * Get the learner's most recently updated mission progress (in the given subjects) with mission.subject loaded.
     *
     * @param  list<int>|null  $subjectIds
     * @return EloquentCollection<int, MissionProgress>
     */
    private function recentMissions(User $student, ?array $subjectIds): EloquentCollection
    {
        return $student->missionProgress()
            ->when($subjectIds !== null, fn (Builder $query) => $query->whereHas(
                'mission',
                fn (Builder $mission) => $mission->whereIn('subject_id', $subjectIds),
            ))
            ->with('mission.subject')
            ->latest('updated_at')
            ->orderByDesc('id')
            ->limit(self::RECENT_LIMIT)
            ->get();
    }
}
