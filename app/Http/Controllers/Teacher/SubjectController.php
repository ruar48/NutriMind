<?php

namespace App\Http\Controllers\Teacher;

use App\Enums\GradeLevel;
use App\Http\Controllers\Controller;
use App\Models\Classroom;
use App\Models\LearningModule;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Routing\Attributes\Controllers\Authorize;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class SubjectController extends Controller
{
    /**
     * Show the active subjects assigned to the teacher with their classrooms, learners, quizzes and game modules.
     */
    #[Authorize('viewAny', Classroom::class)]
    public function index(Request $request): View
    {
        $teacher = $request->user();

        $subjects = $teacher->assignedSubjects()
            ->active()
            ->withCount([
                'classrooms as classrooms_count' => fn (Builder $query) => $query->where('classrooms.teacher_id', $teacher->id)->active(),
                'quizzes as quizzes_count' => fn (Builder $query) => $query->where('quizzes.teacher_id', $teacher->id),
                'quizzes as published_quizzes_count' => fn (Builder $query) => $query->where('quizzes.teacher_id', $teacher->id)->published(),
            ])
            ->orderBy('subjects.name')
            ->orderBy('subjects.id')
            ->get();

        $this->attachTeachingFigures($subjects, $teacher);

        return view('teacher.subjects.index', [
            'subjects' => $subjects,
            'inactiveSubjectsCount' => $teacher->assignedSubjects()->where('subjects.is_active', false)->count(),
        ]);
    }

    /**
     * Set learners_count, grade_levels and modules_count on every subject with three grouped queries.
     *
     * learners_count counts distinct learners in the teacher's active classrooms of the subject; modules_count counts
     * published game modules for the grades the teacher teaches in that subject (every grade when none yet).
     *
     * @param  Collection<int, Subject>  $subjects
     */
    private function attachTeachingFigures(Collection $subjects, User $teacher): void
    {
        $subjectIds = $subjects->modelKeys();

        $learnersBySubject = DB::table('classroom_student')
            ->join('classrooms', 'classrooms.id', '=', 'classroom_student.classroom_id')
            ->where('classrooms.teacher_id', $teacher->id)
            ->whereNull('classrooms.archived_at')
            ->whereIn('classrooms.subject_id', $subjectIds)
            ->groupBy('classrooms.subject_id')
            ->select('classrooms.subject_id', DB::raw('count(distinct classroom_student.student_id) as learners'))
            ->pluck('learners', 'subject_id');

        $gradesBySubject = Classroom::query()
            ->where('teacher_id', $teacher->id)
            ->active()
            ->whereIn('subject_id', $subjectIds)
            ->distinct()
            ->get(['subject_id', 'grade_level'])
            ->groupBy('subject_id')
            ->map(fn (Collection $classrooms): array => $classrooms->pluck('grade_level')->sortBy('value')->values()->all());

        $modulesBySubjectAndGrade = LearningModule::query()
            ->published()
            ->whereIn('subject_id', $subjectIds)
            ->groupBy('subject_id', 'grade_level')
            ->select('subject_id', 'grade_level', DB::raw('count(*) as modules'))
            ->toBase()
            ->get()
            ->mapWithKeys(fn (object $row): array => [$row->subject_id.'-'.$row->grade_level => (int) $row->modules]);

        $subjects->each(function (Subject $subject) use ($learnersBySubject, $gradesBySubject, $modulesBySubjectAndGrade): void {
            /** @var list<GradeLevel> $grades */
            $grades = $gradesBySubject->get($subject->id, []);
            $countedGrades = $grades === [] ? GradeLevel::cases() : $grades;

            $subject->setAttribute('learners_count', (int) ($learnersBySubject[$subject->id] ?? 0));
            $subject->setAttribute('grade_levels', $grades);
            $subject->setAttribute('modules_count', collect($countedGrades)->sum(
                fn (GradeLevel $grade): int => $modulesBySubjectAndGrade->get($subject->id.'-'.$grade->value, 0),
            ));
        });
    }
}
