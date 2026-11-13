<?php

namespace App\Services\Monitoring;

use App\Enums\PerformanceBand;
use App\Enums\UserRole;
use App\Models\Classroom;
use App\Models\MissionProgress;
use App\Models\QuizAttempt;
use App\Models\StudentProfile;
use App\Models\User;
use App\Services\LevelCalculator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Builds the teacher's learners list ("My learners").
 *
 * The classrooms in scope are the teacher's active classrooms (or the one the teacher filtered by). Scores come from
 * the submitted attempts made in those classrooms: each learner's average is the mean of the best percentage of each
 * quiz, rounded to 1 decimal, and the band comes from that rounded value (the same rule as LearnerProgressService).
 * Averages are computed with one grouped query and the band filter is applied as a list of learner ids, so the SQL
 * stays database-agnostic.
 */
final class LearnerRoster
{
    /**
     * The number of learners per page.
     */
    public const PER_PAGE = 20;

    /**
     * The sortable columns and their default direction.
     *
     * @var array<string, string>
     */
    public const SORTS = [
        'name' => 'asc',
        'points' => 'desc',
        'last_active' => 'desc',
        'last_synced' => 'desc',
    ];

    public function __construct(private LevelCalculator $levels) {}

    /**
     * Get the teacher's active classrooms in subjects still assigned to them, with subject and learner count.
     *
     * @return EloquentCollection<int, Classroom>
     */
    public function classroomsFor(User $teacher): EloquentCollection
    {
        return Classroom::query()
            ->accessibleBy($teacher)
            ->active()
            ->with('subject')
            ->withCount('students')
            ->orderBy('name')
            ->orderBy('id')
            ->get();
    }

    /**
     * Build one page of learners enrolled in the classrooms.
     *
     * @param  EloquentCollection<int, Classroom>  $classrooms  the classrooms in scope (subject loaded)
     * @param  key-of<self::SORTS>  $sort
     * @param  'asc'|'desc'  $direction
     */
    public function build(EloquentCollection $classrooms, ?PerformanceBand $band, ?string $search, string $sort, string $direction): LearnerRosterPage
    {
        $classroomIds = $classrooms->modelKeys();
        $scores = $this->scores($classroomIds);
        $learners = $this->learnersIn($classroomIds);

        $bandCounts = array_fill_keys(array_column(PerformanceBand::cases(), 'value'), 0);
        $learnerAverages = [];
        $syncNeedsAttention = 0;

        foreach ($learners as $learner) {
            $average = $scores->get((int) $learner->id)['average'] ?? null;
            $bandCounts[PerformanceBand::fromScore($average)->value]++;

            if ($average !== null) {
                $learnerAverages[] = $average;
            }

            if (SyncHealth::needsAttention($learner->last_synced_at === null ? null : Carbon::parse($learner->last_synced_at))) {
                $syncNeedsAttention++;
            }
        }

        $rows = $this->learnersQuery($classroomIds)
            ->when(filled($search), fn (Builder $query) => $this->whereMatches($query, (string) $search))
            ->when($band !== null, fn (Builder $query) => $this->whereInBand($query, $band, $scores))
            ->with([
                'studentProfile',
                'enrolledClassrooms' => fn (BelongsToMany $query) => $query
                    ->whereIn('classrooms.id', $classroomIds)
                    ->with('subject')
                    ->orderBy('classrooms.name')
                    ->orderBy('classrooms.id'),
            ])
            ->tap(fn (Builder $query) => $this->sort($query, $sort, $direction))
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        $missionsCompleted = $this->missionsCompleted($rows->getCollection());

        $rows->through(fn (User $student): LearnerRosterRow => $this->row($student, $scores, $missionsCompleted));

        return new LearnerRosterPage(
            rows: $rows,
            learnerCount: $learners->count(),
            bandCounts: $bandCounts,
            averageScore: $learnerAverages === [] ? null : round(array_sum($learnerAverages) / count($learnerAverages), 1),
            needsAttention: $bandCounts[PerformanceBand::DidNotMeetExpectations->value],
            syncNeedsAttention: $syncNeedsAttention,
        );
    }

    /**
     * Get each learner's average of best quiz percentages and quiz count from the attempts made in the classrooms.
     *
     * @param  list<int>  $classroomIds
     * @return Collection<int, array{average: float, quizzes: int}>
     */
    public function scores(array $classroomIds): Collection
    {
        return QuizAttempt::query()
            ->submitted()
            ->whereIn('quiz_attempts.classroom_id', $classroomIds)
            ->toBase()
            ->select(['quiz_attempts.student_id', 'quiz_attempts.quiz_id'])
            ->selectRaw('max(quiz_attempts.percentage) as best')
            ->groupBy('quiz_attempts.student_id', 'quiz_attempts.quiz_id')
            ->get()
            ->groupBy(fn (object $row): int => (int) $row->student_id)
            ->map(fn (Collection $bestScores): array => [
                'average' => round((float) $bestScores->avg(fn (object $row): float => (float) $row->best), 1),
                'quizzes' => $bestScores->count(),
            ]);
    }

    /**
     * Get the learners enrolled in the classrooms with their last sync time.
     *
     * @param  list<int>  $classroomIds
     * @return Collection<int, object{id: int|string, last_synced_at: string|null}>
     */
    private function learnersIn(array $classroomIds): Collection
    {
        return $this->learnersQuery($classroomIds)
            ->leftJoin('student_profiles', 'student_profiles.user_id', '=', 'users.id')
            ->toBase()
            ->get(['users.id', 'student_profiles.last_synced_at']);
    }

    /**
     * Start a query of the learner accounts enrolled in any of the classrooms.
     *
     * @param  list<int>  $classroomIds
     * @return Builder<User>
     */
    private function learnersQuery(array $classroomIds): Builder
    {
        return User::query()
            ->ofRole(UserRole::Student)
            ->whereHas('enrolledClassrooms', fn (Builder $query) => $query->whereIn('classrooms.id', $classroomIds));
    }

    /**
     * Limit the query to learners whose name, username or LRN contains the term.
     *
     * @param  Builder<User>  $query
     */
    private function whereMatches(Builder $query, string $search): void
    {
        $query->where(fn (Builder $query) => $query
            ->search($search)
            ->orWhereHas('studentProfile', fn (Builder $profile) => $profile->whereLike('lrn', '%'.trim($search).'%')));
    }

    /**
     * Limit the query to learners whose average falls in the band (learners without attempts for NoData).
     *
     * @param  Builder<User>  $query
     * @param  Collection<int, array{average: float, quizzes: int}>  $scores
     */
    private function whereInBand(Builder $query, PerformanceBand $band, Collection $scores): void
    {
        if ($band === PerformanceBand::NoData) {
            $query->whereNotIn('users.id', $scores->keys()->all());

            return;
        }

        $query->whereIn('users.id', $scores
            ->filter(fn (array $score): bool => PerformanceBand::fromScore($score['average']) === $band)
            ->keys()
            ->all());
    }

    /**
     * Order the query by the sort column, then by name and id.
     *
     * @param  Builder<User>  $query
     * @param  'asc'|'desc'  $direction
     */
    private function sort(Builder $query, string $sort, string $direction): void
    {
        $profileColumn = match ($sort) {
            'points' => 'total_points',
            'last_active' => 'last_active_at',
            'last_synced' => 'last_synced_at',
            default => null,
        };

        if ($profileColumn !== null) {
            $query->orderBy(
                StudentProfile::query()->select($profileColumn)->whereColumn('student_profiles.user_id', 'users.id')->limit(1),
                $direction,
            );
        }

        $query->orderBy('users.name', $profileColumn === null ? $direction : 'asc')->orderBy('users.id');
    }

    /**
     * Count each learner's completed missions in the subjects of their classrooms in scope.
     *
     * @param  EloquentCollection<int, User>  $students  learners with enrolledClassrooms (in scope) loaded
     * @return array<int, int>
     */
    private function missionsCompleted(EloquentCollection $students): array
    {
        $subjectIds = $students
            ->flatMap(fn (User $student): array => $student->enrolledClassrooms->pluck('subject_id')->all())
            ->map(fn (mixed $subjectId): int => (int) $subjectId)
            ->unique()
            ->values()
            ->all();

        if ($students->isEmpty() || $subjectIds === []) {
            return [];
        }

        $counts = MissionProgress::query()
            ->completed()
            ->join('missions', 'missions.id', '=', 'mission_progress.mission_id')
            ->whereIn('mission_progress.student_id', $students->modelKeys())
            ->whereIn('missions.subject_id', $subjectIds)
            ->toBase()
            ->select(['mission_progress.student_id', 'missions.subject_id'])
            ->selectRaw('count(*) as aggregate')
            ->groupBy('mission_progress.student_id', 'missions.subject_id')
            ->get();

        $completed = [];

        foreach ($students as $student) {
            $studentSubjects = $student->enrolledClassrooms->pluck('subject_id')->map(fn (mixed $id): int => (int) $id)->all();

            $completed[$student->getKey()] = (int) $counts
                ->filter(fn (object $row): bool => (int) $row->student_id === $student->getKey()
                    && in_array((int) $row->subject_id, $studentSubjects, true))
                ->sum('aggregate');
        }

        return $completed;
    }

    /**
     * Build the list row of a learner.
     *
     * @param  Collection<int, array{average: float, quizzes: int}>  $scores
     * @param  array<int, int>  $missionsCompleted
     */
    private function row(User $student, Collection $scores, array $missionsCompleted): LearnerRosterRow
    {
        $profile = $student->studentProfile;
        $points = (int) ($profile?->total_points ?? 0);
        $score = $scores->get($student->getKey());
        $average = $score['average'] ?? null;

        return new LearnerRosterRow(
            student: $student,
            classrooms: $student->enrolledClassrooms,
            points: $points,
            level: $this->levels->levelFor($points),
            averageScore: $average,
            band: PerformanceBand::fromScore($average),
            quizzesTaken: $score['quizzes'] ?? 0,
            missionsCompleted: $missionsCompleted[$student->getKey()] ?? 0,
            lastActiveAt: $profile?->last_active_at,
            lastSyncedAt: $profile?->last_synced_at,
            syncNeedsAttention: SyncHealth::needsAttention($profile?->last_synced_at),
        );
    }
}
