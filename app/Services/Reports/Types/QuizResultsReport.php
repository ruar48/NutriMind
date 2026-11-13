<?php

namespace App\Services\Reports\Types;

use App\Enums\GradeLevel;
use App\Enums\PerformanceBand;
use App\Enums\QuizStatus;
use App\Enums\ReportType;
use App\Models\Classroom;
use App\Models\Quiz;
use App\Services\Reports\ColumnType;
use App\Services\Reports\Report;
use App\Services\Reports\ReportColumn;
use App\Services\Reports\ReportRow;
use App\Services\Reports\ReportScope;
use App\Services\Reports\ReportStat;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Quiz results: one row per published or closed quiz assigned to a covered classroom.
 *
 * Teachers only see their own quizzes in subjects still assigned to them. Every figure is limited to the covered
 * classrooms the quiz is assigned to: attempts submitted there (in the period), learners enrolled there, and their
 * due dates. Scores use each learner's best attempt. With a period, quizzes without attempts in it are left out.
 */
final class QuizResultsReport
{
    /**
     * Build the report for the scope.
     */
    public function build(ReportScope $scope): Report
    {
        $quizzes = $this->quizzes($scope);
        $learnerScores = $this->learnerScores($scope, $quizzes->modelKeys());
        $assigned = $this->assignedLearners($scope, $quizzes->modelKeys());

        if ($scope->filters->hasPeriod()) {
            $quizzes = $quizzes->filter(fn (Quiz $quiz): bool => $learnerScores->has($quiz->id))->values();
        }

        $rows = $quizzes->map(function (Quiz $quiz) use ($scope, $learnerScores, $assigned): ReportRow {
            $scores = $learnerScores->get($quiz->id, collect());
            $percentages = $scores->map(fn (object $score): float => (float) $score->best_percentage);
            $attempted = $scores->count();
            $passed = $scores->filter(fn (object $score): bool => (int) $score->passed === 1)->count();
            $dueDates = $quiz->classrooms
                ->map(fn (Classroom $classroom): ?CarbonInterface => $classroom->pivot->due_at)
                ->filter()
                ->sortBy(fn (CarbonInterface $dueAt): int => $dueAt->getTimestamp())
                ->values();

            return new ReportRow(
                cells: [
                    'quiz' => $quiz->title,
                    'subject' => $quiz->subject,
                    'classrooms' => $quiz->classrooms->pluck('name')->all(),
                    'teacher' => $quiz->teacher->name,
                    'attempts' => (int) $scores->sum(fn (object $score): int => (int) $score->attempts),
                    'learners_attempted' => $attempted,
                    'learners_assigned' => (int) ($assigned[$quiz->id] ?? 0),
                    'average' => $percentages->isEmpty() ? null : round($percentages->avg(), 1),
                    'pass_rate' => $attempted === 0 ? null : round($passed / $attempted * 100, 1),
                    'highest' => $percentages->isEmpty() ? null : round($percentages->max(), 1),
                    'lowest' => $percentages->isEmpty() ? null : round($percentages->min(), 1),
                    'due' => $dueDates->isEmpty() ? null : [$dueDates->first(), $dueDates->last()],
                ],
                url: $scope->forAdmin() ? null : route('teacher.quizzes.results.show', $quiz),
                tags: [['label' => $quiz->status->label(), 'color' => $quiz->status->color(), 'icon' => $quiz->status->icon()]],
            );
        })->values();

        return new Report(
            type: ReportType::QuizResults,
            title: ReportType::QuizResults->label(),
            description: ReportType::QuizResults->description(),
            filters: $scope->describeClassrooms(),
            summary: $this->summary($rows, $learnerScores),
            columns: $this->columns($scope),
            rows: $rows,
            notes: array_values(array_filter([
                'Average score, pass rate, highest and lowest scores use each learner’s best attempt. Draft quizzes are not included.',
                'Learners assigned counts the learners enrolled in the reported classrooms the quiz is assigned to.',
                $scope->filters->hasPeriod() ? 'Only attempts submitted in the period are counted; quizzes without attempts in the period are left out.' : null,
            ])),
            breakdown: [
                'title' => 'Quizzes by average score',
                'description' => 'DepEd descriptor of each quiz’s average score',
                'segments' => array_map(fn (PerformanceBand $band): array => [
                    'label' => $band->label(),
                    'value' => $rows->filter(fn (ReportRow $row): bool => PerformanceBand::fromScore($row->value('average')) === $band)->count(),
                    'color' => $band->color(),
                ], PerformanceBand::cases()),
            ],
        );
    }

    /**
     * Get the published and closed quizzes assigned to a covered classroom, newest first, with their subject,
     * teacher and covered classrooms (with the assignment's due date).
     *
     * @return EloquentCollection<int, Quiz>
     */
    private function quizzes(ReportScope $scope): EloquentCollection
    {
        $classroomIds = $scope->classroomIds();
        $filters = $scope->filters;

        return Quiz::query()
            ->whereIn('status', [QuizStatus::Published, QuizStatus::Closed])
            ->whereHas('classrooms', fn (Builder $query) => $query->whereIn('classrooms.id', $classroomIds))
            ->when(! $scope->forAdmin(), fn (Builder $query) => $query
                ->where('teacher_id', $scope->viewer->getKey())
                ->whereIn('subject_id', $scope->viewer->assignedSubjectIds()))
            ->when($filters->subjectId, fn (Builder $query, int $subjectId) => $query->where('subject_id', $subjectId))
            ->when($filters->gradeLevel, fn (Builder $query, GradeLevel $grade) => $query->where('grade_level', $grade))
            ->when($scope->forAdmin() ? $filters->teacherId : null, fn (Builder $query, int $teacherId) => $query->where('teacher_id', $teacherId))
            ->with([
                'subject',
                'teacher:id,name',
                'classrooms' => fn (BelongsToMany $query) => $query
                    ->whereIn('classrooms.id', $classroomIds)
                    ->select('classrooms.id', 'classrooms.name')
                    ->orderBy('classrooms.name')
                    ->orderBy('classrooms.id'),
            ])
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->get();
    }

    /**
     * Get each learner's attempt count, best percentage and pass state per quiz, grouped by quiz id.
     *
     * @param  list<int>  $quizIds
     * @return Collection<int|string, Collection<int, object{quiz_id: int, student_id: int, attempts: int|string, best_percentage: float|string, passed: int|string}>>
     */
    private function learnerScores(ReportScope $scope, array $quizIds): Collection
    {
        return $scope->submittedAttempts()
            ->whereIn('quiz_attempts.quiz_id', $quizIds)
            ->select('quiz_attempts.quiz_id', 'quiz_attempts.student_id')
            ->selectRaw('COUNT(*) as attempts')
            ->selectRaw('MAX(quiz_attempts.percentage) as best_percentage')
            ->selectRaw('MAX(quiz_attempts.passed) as passed')
            ->groupBy('quiz_attempts.quiz_id', 'quiz_attempts.student_id')
            ->get()
            ->groupBy('quiz_id');
    }

    /**
     * Count the distinct learners enrolled in the covered classrooms each quiz is assigned to, keyed by quiz id.
     *
     * @param  list<int>  $quizIds
     * @return Collection<int|string, int|string>
     */
    private function assignedLearners(ReportScope $scope, array $quizIds): Collection
    {
        return DB::table('classroom_quiz')
            ->join('classroom_student', 'classroom_student.classroom_id', '=', 'classroom_quiz.classroom_id')
            ->whereIn('classroom_quiz.quiz_id', $quizIds)
            ->whereIn('classroom_quiz.classroom_id', $scope->classroomIds())
            ->groupBy('classroom_quiz.quiz_id')
            ->selectRaw('classroom_quiz.quiz_id as quiz_id, COUNT(DISTINCT classroom_student.student_id) as learners')
            ->pluck('learners', 'quiz_id');
    }

    /**
     * Build the summary tiles from every learner's best score on the listed quizzes.
     *
     * @param  Collection<int, ReportRow>  $rows
     * @param  Collection<int|string, Collection<int, object>>  $learnerScores
     * @return list<ReportStat>
     */
    private function summary(Collection $rows, Collection $learnerScores): array
    {
        $listed = $rows->count();
        $scores = $learnerScores->flatten(1);
        $attempted = (int) $rows->sum(fn (ReportRow $row): int => $row->value('learners_attempted'));
        $assigned = (int) $rows->sum(fn (ReportRow $row): int => $row->value('learners_assigned'));
        $passed = $scores->filter(fn (object $score): bool => (int) $score->passed === 1)->count();

        return [
            ReportStat::count('Quizzes', $listed, 'clipboard-check', 'sky', 'Published and closed quizzes'),
            ReportStat::count(
                'Attempts',
                (int) $rows->sum(fn (ReportRow $row): int => $row->value('attempts')),
                'list-checks',
                'grape',
                $assigned === 0 ? 'No learners assigned yet' : ColumnType::percent(min(100, $attempted / $assigned * 100)).' of assigned learners took part',
            ),
            ReportStat::percent(
                'Average score',
                $scores->isEmpty() ? null : round($scores->avg(fn (object $score): float => (float) $score->best_percentage), 1),
                'target',
                'leaf',
                'Each learner’s best attempt',
            ),
            ReportStat::percent(
                'Pass rate',
                $scores->isEmpty() ? null : round($passed / $scores->count() * 100, 1),
                'badge-check',
                'teal',
                $scores->isEmpty() ? 'No attempts yet' : 'Learners with a passing attempt',
            ),
        ];
    }

    /**
     * Get the report columns (the teacher column is shown to administrators only).
     *
     * @return list<ReportColumn>
     */
    private function columns(ReportScope $scope): array
    {
        return array_values(array_filter([
            new ReportColumn('quiz', 'Quiz'),
            new ReportColumn('subject', 'Subject', ColumnType::Subject),
            new ReportColumn('classrooms', 'Classrooms', ColumnType::List),
            $scope->forAdmin() ? new ReportColumn('teacher', 'Teacher') : null,
            new ReportColumn('attempts', 'Attempts', ColumnType::Number),
            new ReportColumn('learners_attempted', 'Learners attempted', ColumnType::Number),
            new ReportColumn('learners_assigned', 'Learners assigned', ColumnType::Number),
            new ReportColumn('average', 'Average score', ColumnType::Score),
            new ReportColumn('pass_rate', 'Pass rate', ColumnType::Rate),
            new ReportColumn('highest', 'Highest score', ColumnType::Score),
            new ReportColumn('lowest', 'Lowest score', ColumnType::Score),
            new ReportColumn('due', 'Due', ColumnType::DateRange, 'No due date'),
        ]));
    }
}
