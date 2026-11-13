<?php

namespace App\Services\Analytics;

use App\Enums\QuizStatus;
use App\Enums\UserRole;
use App\Models\Classroom;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\WeekDay;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Collection as BaseCollection;
use Illuminate\Support\Facades\DB;

/**
 * The teacher dashboard's classroom facts: classroom cards, quiz completion, quizzes due soon and the latest
 * submissions. Everything is limited to the classrooms passed in (the teacher's accessible, non-archived ones).
 */
class TeacherOverview
{
    /**
     * Get the teacher's non-archived classrooms in subjects still assigned to them, with their subject and the
     * number of active learners enrolled, ordered by name.
     *
     * @return Collection<int, Classroom>
     */
    public function classrooms(User $teacher): Collection
    {
        return Classroom::query()
            ->accessibleBy($teacher)
            ->active()
            ->with('subject')
            ->withCount(['students' => fn (Builder $students) => $students->where('users.is_active', true)])
            ->orderBy('name')
            ->orderBy('id')
            ->get();
    }

    /**
     * Count, per classroom, the learner–quiz pairs of the quizzes learners can take now (published or closed,
     * available) and how many of those pairs have a submitted attempt.
     *
     * @param  list<int>  $classroomIds
     * @return BaseCollection<int, array{assigned: int, submitted: int, rate: float|null}> Keyed by classroom id.
     */
    public function quizCompletion(array $classroomIds): BaseCollection
    {
        if ($classroomIds === []) {
            return collect();
        }

        return DB::table('classroom_quiz')
            ->join('quizzes', 'quizzes.id', '=', 'classroom_quiz.quiz_id')
            ->join('classroom_student', 'classroom_student.classroom_id', '=', 'classroom_quiz.classroom_id')
            ->join('users', 'users.id', '=', 'classroom_student.student_id')
            ->whereIn('classroom_quiz.classroom_id', $classroomIds)
            ->whereIn('quizzes.status', [QuizStatus::Published->value, QuizStatus::Closed->value])
            ->where('users.role', UserRole::Student->value)
            ->where('users.is_active', true)
            ->where(fn (QueryBuilder $query) => $query
                ->whereNull('classroom_quiz.available_from')
                ->orWhere('classroom_quiz.available_from', '<=', now()))
            ->groupBy('classroom_quiz.classroom_id')
            ->select('classroom_quiz.classroom_id')
            ->selectRaw('COUNT(*) as assigned')
            ->selectRaw('SUM(CASE WHEN EXISTS ('
                .'SELECT 1 FROM quiz_attempts'
                .' WHERE quiz_attempts.quiz_id = classroom_quiz.quiz_id'
                .' AND quiz_attempts.student_id = classroom_student.student_id'
                .' AND quiz_attempts.submitted_at IS NOT NULL'
                .') THEN 1 ELSE 0 END) as submitted')
            ->get()
            ->mapWithKeys(fn (object $row): array => [(int) $row->classroom_id => [
                'assigned' => (int) $row->assigned,
                'submitted' => (int) $row->submitted,
                'rate' => (int) $row->assigned === 0 ? null : round((int) $row->submitted / (int) $row->assigned * 100, 1),
            ]]);
    }

    /**
     * Get the published quizzes due in the classrooms within the next days, soonest first.
     *
     * submitted counts the enrolled active learners of that classroom who submitted an attempt there; enrolled is
     * the classroom's students_count.
     *
     * @param  Collection<int, Classroom>  $classrooms  Loaded with students_count.
     * @return list<array{quiz: Quiz, classroom: Classroom, dueAt: CarbonImmutable, submitted: int, enrolled: int}>
     */
    public function dueSoon(Collection $classrooms, int $days = 7, int $limit = 5): array
    {
        if ($classrooms->isEmpty()) {
            return [];
        }

        $now = now()->toImmutable();

        $submitted = QuizAttempt::query()->toBase()
            ->join('users', 'users.id', '=', 'quiz_attempts.student_id')
            ->join('classroom_student', function (JoinClause $join): void {
                $join->on('classroom_student.student_id', '=', 'quiz_attempts.student_id')
                    ->on('classroom_student.classroom_id', '=', 'quiz_attempts.classroom_id');
            })
            ->whereColumn('quiz_attempts.quiz_id', 'classroom_quiz.quiz_id')
            ->whereColumn('quiz_attempts.classroom_id', 'classroom_quiz.classroom_id')
            ->whereNotNull('quiz_attempts.submitted_at')
            ->where('users.is_active', true)
            ->selectRaw('COUNT(DISTINCT quiz_attempts.student_id)');

        $rows = $this->publishedAssignments($classrooms->modelKeys())
            ->whereBetween('classroom_quiz.due_at', [$now, $now->addDays($days)->endOfDay()])
            ->orderBy('classroom_quiz.due_at')
            ->orderBy('classroom_quiz.id')
            ->limit($limit)
            ->select(['classroom_quiz.quiz_id', 'classroom_quiz.classroom_id', 'classroom_quiz.due_at'])
            ->selectSub($submitted, 'submitted')
            ->get();

        if ($rows->isEmpty()) {
            return [];
        }

        $quizzes = Quiz::query()->whereKey($rows->pluck('quiz_id')->all())->with('subject')->get()->keyBy('id');
        $classroomsById = $classrooms->keyBy('id');

        return $rows->map(fn (object $row): array => [
            'quiz' => $quizzes->get((int) $row->quiz_id),
            'classroom' => $classroomsById->get((int) $row->classroom_id),
            'dueAt' => CarbonImmutable::parse($row->due_at),
            'submitted' => (int) $row->submitted,
            'enrolled' => (int) $classroomsById->get((int) $row->classroom_id)->students_count,
        ])->values()->all();
    }

    /**
     * Count the distinct published quizzes still due in the classrooms this week (from now until Sunday night).
     *
     * @param  list<int>  $classroomIds
     */
    public function quizzesDueThisWeek(array $classroomIds): int
    {
        if ($classroomIds === []) {
            return 0;
        }

        $now = now()->toImmutable();

        return $this->publishedAssignments($classroomIds)
            ->whereBetween('classroom_quiz.due_at', [$now, $now->endOfWeek(WeekDay::Sunday)])
            ->distinct()
            ->count('classroom_quiz.quiz_id');
    }

    /**
     * Get the latest submitted attempts in the classrooms, with learner and quiz.
     *
     * @param  list<int>  $classroomIds
     * @return Collection<int, QuizAttempt>
     */
    public function recentSubmissions(array $classroomIds, int $limit = 6): Collection
    {
        return QuizAttempt::query()
            ->submitted()
            ->whereIn('classroom_id', $classroomIds)
            ->with(['student', 'quiz'])
            ->latest('submitted_at')
            ->orderByDesc('id')
            ->limit($limit)
            ->get();
    }

    /**
     * Get the assignments of published quizzes to the classrooms.
     *
     * @param  list<int>  $classroomIds
     */
    private function publishedAssignments(array $classroomIds): QueryBuilder
    {
        return DB::table('classroom_quiz')
            ->join('quizzes', 'quizzes.id', '=', 'classroom_quiz.quiz_id')
            ->whereIn('classroom_quiz.classroom_id', $classroomIds)
            ->where('quizzes.status', QuizStatus::Published->value);
    }
}
