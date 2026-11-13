<?php

namespace App\Services\Reports\Types;

use App\Enums\PerformanceBand;
use App\Enums\ProgressStatus;
use App\Enums\ReportType;
use App\Enums\UserRole;
use App\Models\MissionProgress;
use App\Models\User;
use App\Services\LevelCalculator;
use App\Services\Reports\ColumnType;
use App\Services\Reports\Report;
use App\Services\Reports\ReportColumn;
use App\Services\Reports\ReportRow;
use App\Services\Reports\ReportScope;
use App\Services\Reports\ReportStat;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Collection;

/**
 * Learner performance: one row per learner enrolled in a covered classroom.
 *
 * Quiz figures use the learner's best attempt per quiz among the attempts submitted in the covered classrooms
 * (and period); missions completed counts the learner's completed missions in the subjects of their covered
 * classrooms (completed in the period). Points and level are the learner's game totals.
 */
final class StudentPerformanceReport
{
    public function __construct(private LevelCalculator $levels) {}

    /**
     * Build the report for the scope.
     */
    public function build(ReportScope $scope): Report
    {
        $classroomIds = $scope->classroomIds();
        $learners = $this->learners($classroomIds);
        $scores = $this->bestScores($scope);
        $missions = $this->completedMissions($scope);

        $rows = $learners->map(function (User $learner) use ($scope, $scores, $missions): ReportRow {
            $bestScores = $scores->get($learner->id, collect());
            $percentages = $bestScores->map(fn (object $score): float => (float) $score->best_percentage);
            $average = $percentages->isEmpty() ? null : round($percentages->avg(), 1);
            $points = (int) ($learner->studentProfile?->total_points ?? 0);

            return new ReportRow(
                cells: [
                    'learner' => $learner->name,
                    'lrn' => $learner->studentProfile?->lrn,
                    'grade' => $learner->studentProfile?->grade_level,
                    'classrooms' => $learner->enrolledClassrooms->pluck('name')->all(),
                    'quizzes_taken' => $bestScores->count(),
                    'average' => $average,
                    'highest' => $percentages->isEmpty() ? null : round($percentages->max(), 1),
                    'lowest' => $percentages->isEmpty() ? null : round($percentages->min(), 1),
                    'quizzes_passed' => $bestScores->filter(fn (object $score): bool => (int) $score->passed === 1)->count(),
                    'missions_completed' => (int) ($missions[$learner->id] ?? 0),
                    'points' => $points,
                    'level' => $this->levels->levelFor($points),
                    'band' => PerformanceBand::fromScore($average),
                ],
                url: route($scope->forAdmin() ? 'admin.students.show' : 'teacher.students.show', $learner),
                tags: $learner->is_active ? [] : [['label' => 'Inactive account', 'color' => 'slate', 'icon' => 'ban']],
            );
        })->values();

        return new Report(
            type: ReportType::StudentPerformance,
            title: ReportType::StudentPerformance->label(),
            description: ReportType::StudentPerformance->description(),
            filters: $scope->describeClassrooms(),
            summary: $this->summary($rows),
            columns: $this->columns(),
            rows: $rows,
            notes: array_values(array_filter([
                'Average, highest and lowest scores use each learner’s best attempt per quiz. Quizzes passed counts the quizzes with a passing attempt.',
                'Missions completed counts the learner’s missions in the subjects of the reported classrooms. Points and levels are the learner’s game totals.',
                $scope->filters->hasPeriod() ? 'Only quiz attempts submitted and missions completed in the period are counted.' : null,
            ])),
            breakdown: [
                'title' => 'Learners by performance band',
                'description' => 'DepEd descriptors of each learner’s average score',
                'segments' => array_map(fn (PerformanceBand $band): array => [
                    'label' => $band->label(),
                    'value' => $rows->filter(fn (ReportRow $row): bool => $row->value('band') === $band)->count(),
                    'color' => $band->color(),
                ], PerformanceBand::cases()),
            ],
        );
    }

    /**
     * Get the learners enrolled in the covered classrooms, with their profile and covered classrooms.
     *
     * @param  list<int>  $classroomIds
     * @return Collection<int, User>
     */
    private function learners(array $classroomIds): Collection
    {
        return User::query()
            ->ofRole(UserRole::Student)
            ->whereHas('enrolledClassrooms', fn (Builder $query) => $query->whereIn('classrooms.id', $classroomIds))
            ->with([
                'studentProfile',
                'enrolledClassrooms' => fn (BelongsToMany $query) => $query
                    ->whereIn('classrooms.id', $classroomIds)
                    ->select('classrooms.id', 'classrooms.name')
                    ->orderBy('classrooms.name')
                    ->orderBy('classrooms.id'),
            ])
            ->orderBy('name')
            ->orderBy('id')
            ->get();
    }

    /**
     * Get each learner's best percentage and pass state per quiz, grouped by learner id.
     *
     * @return Collection<int|string, Collection<int, object{student_id: int, quiz_id: int, best_percentage: float|string, passed: int|string}>>
     */
    private function bestScores(ReportScope $scope): Collection
    {
        return $scope->submittedAttempts()
            ->select('quiz_attempts.student_id', 'quiz_attempts.quiz_id')
            ->selectRaw('MAX(quiz_attempts.percentage) as best_percentage')
            ->selectRaw('MAX(quiz_attempts.passed) as passed')
            ->groupBy('quiz_attempts.student_id', 'quiz_attempts.quiz_id')
            ->get()
            ->groupBy('student_id');
    }

    /**
     * Count each learner's completed missions in the subjects of their covered classrooms, keyed by learner id.
     *
     * @return Collection<int|string, int|string>
     */
    private function completedMissions(ReportScope $scope): Collection
    {
        $classroomIds = $scope->classroomIds();

        $query = MissionProgress::query()
            ->toBase()
            ->join('missions', 'missions.id', '=', 'mission_progress.mission_id')
            ->where('mission_progress.status', ProgressStatus::Completed->value)
            ->whereExists(fn (QueryBuilder $enrollment) => $enrollment
                ->selectRaw('1')
                ->from('classroom_student')
                ->join('classrooms', 'classrooms.id', '=', 'classroom_student.classroom_id')
                ->whereColumn('classroom_student.student_id', 'mission_progress.student_id')
                ->whereColumn('classrooms.subject_id', 'missions.subject_id')
                ->whereIn('classrooms.id', $classroomIds))
            ->groupBy('mission_progress.student_id')
            ->selectRaw('mission_progress.student_id as student_id, COUNT(*) as completed');

        $scope->applyPeriod($query, 'mission_progress.completed_at');

        return $query->pluck('completed', 'student_id');
    }

    /**
     * Build the summary tiles.
     *
     * @param  Collection<int, ReportRow>  $rows
     * @return list<ReportStat>
     */
    private function summary(Collection $rows): array
    {
        $averages = $rows->map(fn (ReportRow $row): ?float => $row->value('average'))->filter(fn (?float $average): bool => $average !== null);
        $needsAttention = $rows->filter(fn (ReportRow $row): bool => $row->value('band') === PerformanceBand::DidNotMeetExpectations)->count();

        return [
            ReportStat::count('Learners', $rows->count(), 'users', 'sky', 'Enrolled in the reported classrooms'),
            ReportStat::percent(
                'Average score',
                $averages->isEmpty() ? null : round($averages->avg(), 1),
                'target',
                'leaf',
                $averages->isEmpty() ? 'No quiz attempts yet' : trans_choice('Across :count learner with quiz scores|Across :count learners with quiz scores', $averages->count()),
            ),
            ReportStat::count('Needs attention', $needsAttention, 'triangle-alert', 'berry', 'Did Not Meet Expectations (below 75%)'),
            ReportStat::count('Missions completed', (int) $rows->sum(fn (ReportRow $row): int => $row->value('missions_completed')), 'flag', 'carrot', 'By these learners'),
        ];
    }

    /**
     * Get the report columns.
     *
     * @return list<ReportColumn>
     */
    private function columns(): array
    {
        return [
            new ReportColumn('learner', 'Learner'),
            new ReportColumn('lrn', 'LRN'),
            new ReportColumn('grade', 'Grade', ColumnType::Badge),
            new ReportColumn('classrooms', 'Classrooms', ColumnType::List),
            new ReportColumn('quizzes_taken', 'Quizzes taken', ColumnType::Number),
            new ReportColumn('average', 'Average score', ColumnType::Score),
            new ReportColumn('highest', 'Highest score', ColumnType::Score),
            new ReportColumn('lowest', 'Lowest score', ColumnType::Score),
            new ReportColumn('quizzes_passed', 'Quizzes passed', ColumnType::Number),
            new ReportColumn('missions_completed', 'Missions completed', ColumnType::Number),
            new ReportColumn('points', 'Points', ColumnType::Number),
            new ReportColumn('level', 'Level', ColumnType::Level),
            new ReportColumn('band', 'Performance band', ColumnType::Band),
        ];
    }
}
