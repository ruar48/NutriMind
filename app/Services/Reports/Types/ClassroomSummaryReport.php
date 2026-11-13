<?php

namespace App\Services\Reports\Types;

use App\Enums\PerformanceBand;
use App\Enums\ProgressStatus;
use App\Enums\QuizStatus;
use App\Enums\ReportType;
use App\Models\Classroom;
use App\Models\MissionProgress;
use App\Models\QuizAttempt;
use App\Services\Reports\ColumnType;
use App\Services\Reports\Report;
use App\Services\Reports\ReportColumn;
use App\Services\Reports\ReportRow;
use App\Services\Reports\ReportScope;
use App\Services\Reports\ReportStat;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Classroom summary: one row per covered classroom.
 *
 * Average score = mean of each learner's best attempt per quiz submitted in the classroom (in the period). Quiz
 * completion = submitted (enrolled learner, assigned published or closed quiz) pairs out of enrolled learners ×
 * assigned quizzes; with a period only the quizzes taken in the classroom during the period count. Missions
 * completed counts enrolled learners' completions of the classroom's subject and grade (in the period). Active
 * learners played the game in the last 7 days.
 */
final class ClassroomSummaryReport
{
    /**
     * The number of days a learner counts as active after last playing.
     */
    public const ACTIVE_WITHIN_DAYS = 7;

    /**
     * Build the report for the scope.
     */
    public function build(ReportScope $scope): Report
    {
        $classrooms = $scope->classrooms()->load('teacher:id,name')->loadCount('students');
        $bestScores = $this->bestScores($scope)->groupBy('classroom_id');
        $enrolled = $this->enrollments($scope);
        $completion = $this->quizCompletion($scope, $bestScores, $enrolled);
        $missions = $this->completedMissions($scope);
        $active = $this->activeLearners($scope);

        $rows = $classrooms->map(function (Classroom $classroom) use ($scope, $bestScores, $completion, $missions, $active): ReportRow {
            $scores = $bestScores->get($classroom->id, collect());
            [$submitted, $expected] = $completion[$classroom->id];

            return new ReportRow(
                cells: [
                    'classroom' => $classroom->name,
                    'subject' => $classroom->subject,
                    'teacher' => $classroom->teacher->name,
                    'grade' => $classroom->grade_level,
                    'learners' => (int) $classroom->students_count,
                    'average' => $scores->isEmpty() ? null : round($scores->avg(fn (object $score): float => (float) $score->best_percentage), 1),
                    'quiz_completion' => $expected === 0 ? null : round($submitted / $expected * 100, 1),
                    'missions_completed' => (int) ($missions[$classroom->id] ?? 0),
                    'active_learners' => (int) ($active[$classroom->id] ?? 0),
                ],
                url: $scope->forAdmin() ? null : route('teacher.classrooms.show', $classroom),
                tags: $classroom->isArchived() ? [['label' => 'Archived', 'color' => 'slate', 'icon' => 'archive']] : [],
            );
        })->values();

        return new Report(
            type: ReportType::ClassroomSummary,
            title: ReportType::ClassroomSummary->label(),
            description: ReportType::ClassroomSummary->description(),
            filters: $scope->describeClassrooms(),
            summary: $this->summary($rows, $classrooms->filter(fn (Classroom $classroom): bool => $classroom->isArchived())->count(), $bestScores, $enrolled, $completion),
            columns: $this->columns(),
            rows: $rows,
            notes: array_values(array_filter([
                'Average score uses each learner’s best attempt per quiz. Quiz completion is the share of assigned published quizzes that enrolled learners have submitted.',
                'Active learners played the game in the last '.self::ACTIVE_WITHIN_DAYS.' days.',
                $scope->filters->hasPeriod() ? 'Averages count attempts submitted in the period, quiz completion counts the quizzes taken in the period and missions completed counts completions in the period.' : null,
            ])),
            breakdown: [
                'title' => 'Classrooms by average score',
                'description' => 'DepEd descriptor of each classroom’s average score',
                'segments' => array_map(fn (PerformanceBand $band): array => [
                    'label' => $band->label(),
                    'value' => $rows->filter(fn (ReportRow $row): bool => PerformanceBand::fromScore($row->value('average')) === $band)->count(),
                    'color' => $band->color(),
                ], PerformanceBand::cases()),
            ],
        );
    }

    /**
     * Get each learner's best percentage per quiz in each covered classroom (attempts submitted in the period).
     *
     * @return Collection<int, object{classroom_id: int, student_id: int, quiz_id: int, best_percentage: float|string}>
     */
    private function bestScores(ReportScope $scope): Collection
    {
        return $scope->submittedAttempts()
            ->select('quiz_attempts.classroom_id', 'quiz_attempts.student_id', 'quiz_attempts.quiz_id')
            ->selectRaw('MAX(quiz_attempts.percentage) as best_percentage')
            ->groupBy('quiz_attempts.classroom_id', 'quiz_attempts.student_id', 'quiz_attempts.quiz_id')
            ->get();
    }

    /**
     * Get the ids of the learners enrolled in each covered classroom, keyed by classroom id.
     *
     * @return Collection<int|string, Collection<int, int>>
     */
    private function enrollments(ReportScope $scope): Collection
    {
        return DB::table('classroom_student')
            ->whereIn('classroom_id', $scope->classroomIds())
            ->orderBy('classroom_id')
            ->orderBy('student_id')
            ->get(['classroom_id', 'student_id'])
            ->groupBy('classroom_id')
            ->map(fn (Collection $rows): Collection => $rows->map(fn (object $row): int => (int) $row->student_id));
    }

    /**
     * Count, per classroom, the submitted (enrolled learner, quiz) pairs and the expected pairs, keyed by classroom id.
     *
     * @param  Collection<int|string, Collection<int, object>>  $bestScores
     * @param  Collection<int|string, Collection<int, int>>  $enrolled
     * @return array<int, array{0: int, 1: int}>
     */
    private function quizCompletion(ReportScope $scope, Collection $bestScores, Collection $enrolled): array
    {
        $assigned = DB::table('classroom_quiz')
            ->join('quizzes', 'quizzes.id', '=', 'classroom_quiz.quiz_id')
            ->whereIn('classroom_quiz.classroom_id', $scope->classroomIds())
            ->whereIn('quizzes.status', [QuizStatus::Published->value, QuizStatus::Closed->value])
            ->get(['classroom_quiz.classroom_id', 'classroom_quiz.quiz_id'])
            ->groupBy('classroom_id');

        $submitted = $scope->filters->hasPeriod() ? $this->submittedPairs($scope)->groupBy('classroom_id') : $bestScores;

        $completion = [];

        foreach ($scope->classroomIds() as $classroomId) {
            $learners = $enrolled->get($classroomId, collect())->flip();
            $quizIds = $assigned->get($classroomId, collect())->map(fn (object $row): int => (int) $row->quiz_id);

            if ($scope->filters->hasPeriod()) {
                $takenInPeriod = $bestScores->get($classroomId, collect())->map(fn (object $row): int => (int) $row->quiz_id)->unique();
                $quizIds = $quizIds->intersect($takenInPeriod);
            }

            $quizIds = $quizIds->flip();
            $completed = $submitted->get($classroomId, collect())
                ->filter(fn (object $row): bool => $learners->has((int) $row->student_id) && $quizIds->has((int) $row->quiz_id))
                ->count();

            $completion[$classroomId] = [$completed, $learners->count() * $quizIds->count()];
        }

        return $completion;
    }

    /**
     * Get every distinct (classroom, learner, quiz) with a submitted attempt in the covered classrooms, at any time.
     *
     * @return Collection<int, object{classroom_id: int, student_id: int, quiz_id: int}>
     */
    private function submittedPairs(ReportScope $scope): Collection
    {
        return QuizAttempt::query()
            ->toBase()
            ->whereNotNull('submitted_at')
            ->whereIn('classroom_id', $scope->classroomIds())
            ->distinct()
            ->get(['classroom_id', 'student_id', 'quiz_id']);
    }

    /**
     * Count the enrolled learners' completed missions of each classroom's subject and grade, keyed by classroom id.
     *
     * @return Collection<int|string, int|string>
     */
    private function completedMissions(ReportScope $scope): Collection
    {
        $query = MissionProgress::query()
            ->toBase()
            ->join('missions', 'missions.id', '=', 'mission_progress.mission_id')
            ->join('classroom_student', 'classroom_student.student_id', '=', 'mission_progress.student_id')
            ->join('classrooms', 'classrooms.id', '=', 'classroom_student.classroom_id')
            ->whereIn('classrooms.id', $scope->classroomIds())
            ->whereColumn('missions.subject_id', 'classrooms.subject_id')
            ->where(fn (QueryBuilder $grade) => $grade->whereNull('missions.grade_level')->orWhereColumn('missions.grade_level', 'classrooms.grade_level'))
            ->where('mission_progress.status', ProgressStatus::Completed->value)
            ->groupBy('classrooms.id')
            ->selectRaw('classrooms.id as classroom_id, COUNT(*) as completed');

        $scope->applyPeriod($query, 'mission_progress.completed_at');

        return $query->pluck('completed', 'classroom_id');
    }

    /**
     * Count the enrolled learners who played in the last days, keyed by classroom id.
     *
     * @return Collection<int|string, int|string>
     */
    private function activeLearners(ReportScope $scope): Collection
    {
        return DB::table('classroom_student')
            ->join('student_profiles', 'student_profiles.user_id', '=', 'classroom_student.student_id')
            ->whereIn('classroom_student.classroom_id', $scope->classroomIds())
            ->where('student_profiles.last_active_at', '>=', now()->subDays(self::ACTIVE_WITHIN_DAYS))
            ->groupBy('classroom_student.classroom_id')
            ->selectRaw('classroom_student.classroom_id as classroom_id, COUNT(*) as learners')
            ->pluck('learners', 'classroom_id');
    }

    /**
     * Build the summary tiles.
     *
     * @param  Collection<int, ReportRow>  $rows
     * @param  int  $archived  The number of archived classrooms among the rows.
     * @param  Collection<int|string, Collection<int, object>>  $bestScores
     * @param  Collection<int|string, Collection<int, int>>  $enrolled
     * @param  array<int, array{0: int, 1: int}>  $completion
     * @return list<ReportStat>
     */
    private function summary(Collection $rows, int $archived, Collection $bestScores, Collection $enrolled, array $completion): array
    {
        $scores = $bestScores->flatten(1);
        $submitted = array_sum(array_column($completion, 0));
        $expected = array_sum(array_column($completion, 1));

        return [
            ReportStat::count('Classrooms', $rows->count(), 'school', 'grape', $archived > 0 ? trans_choice(':count archived classroom|:count archived classrooms', $archived) : 'Active classrooms'),
            ReportStat::count('Learners', $enrolled->flatten()->unique()->count(), 'users', 'sky', 'Each learner counted once'),
            ReportStat::percent(
                'Average score',
                $scores->isEmpty() ? null : round($scores->avg(fn (object $score): float => (float) $score->best_percentage), 1),
                'target',
                'leaf',
                'Each learner’s best attempt per quiz',
            ),
            ReportStat::percent('Quiz completion', $expected === 0 ? null : round($submitted / $expected * 100, 1), 'clipboard-check', 'teal', $expected === 0 ? 'No published quizzes assigned yet' : number_format($submitted).' of '.number_format($expected).' quizzes submitted'),
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
            new ReportColumn('classroom', 'Classroom'),
            new ReportColumn('subject', 'Subject', ColumnType::Subject),
            new ReportColumn('teacher', 'Teacher'),
            new ReportColumn('grade', 'Grade', ColumnType::Badge),
            new ReportColumn('learners', 'Learners', ColumnType::Number),
            new ReportColumn('average', 'Average score', ColumnType::Score),
            new ReportColumn('quiz_completion', 'Quiz completion', ColumnType::Rate),
            new ReportColumn('missions_completed', 'Missions completed', ColumnType::Number),
            new ReportColumn('active_learners', 'Active learners (7 days)', ColumnType::Number),
        ];
    }
}
