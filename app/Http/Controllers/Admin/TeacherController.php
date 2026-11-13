<?php

namespace App\Http\Controllers\Admin;

use App\Enums\QuizStatus;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\IndexTeacherRequest;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Teacher accounts with their teaching load: assigned subjects, classrooms, learners and quizzes.
 */
class TeacherController extends Controller
{
    /**
     * Teachers per page.
     */
    private const PER_PAGE = 20;

    /**
     * List the teachers with subjects, active classrooms, distinct learners and published quizzes.
     */
    public function index(IndexTeacherRequest $request): View
    {
        $sort = $request->sort();

        $teachers = $request->constrainStatus($request->constrainSearch(User::query()->ofRole(UserRole::Teacher)))
            ->when($request->subject(), fn (Builder $query, int $subjectId): Builder => $query->whereHas(
                'assignedSubjects',
                fn (Builder $subject): Builder => $subject->whereKey($subjectId),
            ))
            ->select('users.*')
            ->addSelect(['learners_count' => $this->distinctLearnersCount()->whereColumn('classrooms.teacher_id', 'users.id')])
            ->withCount([
                'classrooms as active_classrooms_count' => fn (Builder $classroom): Builder => $classroom->active(),
                'quizzes as published_quizzes_count' => fn (Builder $quiz): Builder => $quiz->published(),
            ])
            ->with(['assignedSubjects' => fn (BelongsToMany $subjects): BelongsToMany => $subjects->orderBy('name')->orderBy('subjects.id')])
            ->orderBy($sort, $request->direction($sort === 'name' ? 'asc' : 'desc'))
            ->when($sort !== 'name', fn (Builder $query): Builder => $query->orderBy('name'))
            ->orderBy('id')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        return view('admin.teachers.index', [
            'teachers' => $teachers,
            'subjectOptions' => Subject::query()->orderBy('name')->orderBy('id')->pluck('name', 'id')->all(),
            'teachersWithoutSubjects' => User::query()->ofRole(UserRole::Teacher)->active()->doesntHave('assignedSubjects')->count(),
            'isFiltered' => $request->search() !== null || $request->status() !== null || $request->subject() !== null,
        ]);
    }

    /**
     * Show the teacher's profile: subjects, classrooms, quizzes by status, lesson plans and account actions.
     */
    public function show(User $teacher): View
    {
        $teacher->load(['assignedSubjects' => fn (BelongsToMany $subjects): BelongsToMany => $subjects->orderBy('name')->orderBy('subjects.id')])
            ->loadCount(['lessonPlans', 'questionBankItems', 'feedbackGiven']);

        $classrooms = $teacher->classrooms()
            ->with(['subject', 'schoolYear'])
            ->withCount('students')
            ->orderByRaw('CASE WHEN classrooms.archived_at IS NULL THEN 0 ELSE 1 END')
            ->orderBy('name')
            ->orderBy('id')
            ->get();

        $quizzesByStatus = $teacher->quizzes()
            ->toBase()
            ->select('status')
            ->selectRaw('count(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $quizCounts = collect(QuizStatus::cases())
            ->mapWithKeys(fn (QuizStatus $status): array => [$status->value => (int) ($quizzesByStatus[$status->value] ?? 0)])
            ->all();

        return view('admin.teachers.show', [
            'teacher' => $teacher,
            'classrooms' => $classrooms,
            'quizCounts' => $quizCounts,
            'learnersCount' => $this->distinctLearnersCount()->where('classrooms.teacher_id', $teacher->id)->value('aggregate') ?? 0,
        ]);
    }

    /**
     * Build the count of distinct learners enrolled in active (not archived) classrooms; callers add the teacher
     * constraint (a value, or users.id for a correlated subquery).
     */
    private function distinctLearnersCount(): QueryBuilder
    {
        return DB::table('classroom_student')
            ->join('classrooms', 'classrooms.id', '=', 'classroom_student.classroom_id')
            ->whereNull('classrooms.archived_at')
            ->selectRaw('count(distinct classroom_student.student_id) as aggregate');
    }
}
