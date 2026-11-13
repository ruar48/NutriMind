<?php

namespace App\Http\Controllers\Admin;

use App\Enums\GradeLevel;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\IndexStudentRequest;
use App\Http\Requests\Admin\StoreStudentRequest;
use App\Http\Requests\Admin\UpdateStudentRequest;
use App\Models\Classroom;
use App\Models\User;
use App\Services\CreatedStudentAccount;
use App\Services\LearnerProgressService;
use App\Services\LevelCalculator;
use App\Services\StudentAccounts;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Learner accounts: list with filters, add with optional classroom enrollment, profile with game progress, edit
 * (classroom enrollment of the active school year) and delete.
 */
class StudentController extends Controller
{
    /**
     * Learners per page.
     */
    private const PER_PAGE = 20;

    /**
     * List the learners with filters (search, grade, classroom, status, guardian) and sort.
     */
    public function index(IndexStudentRequest $request, LevelCalculator $levels): View
    {
        $students = $request->students()
            ->with([
                'studentProfile',
                'enrolledClassrooms' => fn (BelongsToMany $classrooms): BelongsToMany => $classrooms
                    ->active()
                    ->with('subject')
                    ->orderBy('name')
                    ->orderBy('classrooms.id'),
            ])
            ->withCount('guardians')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        return view('admin.students.index', [
            'students' => $students,
            'levels' => $students->getCollection()
                ->mapWithKeys(fn (User $student): array => [$student->id => $levels->levelFor((int) ($student->studentProfile?->total_points ?? 0))])
                ->all(),
            'classroomOptions' => $this->classroomOptions(StoreStudentRequest::enrollableClassrooms()),
            'isFiltered' => $request->search() !== null
                || $request->status() !== null
                || $request->grade() !== null
                || $request->classroom() !== null
                || $request->guardian() !== null,
        ]);
    }

    /**
     * Show the form for a new learner.
     */
    public function create(): View
    {
        return view('admin.students.create', [
            'classrooms' => StoreStudentRequest::enrollableClassrooms(),
        ]);
    }

    /**
     * Create the learner account (generated username and password when left blank) and enroll it in the chosen
     * classrooms, all or nothing.
     */
    public function store(StoreStudentRequest $request, StudentAccounts $accounts): RedirectResponse
    {
        $attributes = $request->safe()->only(['name', 'username', 'lrn', 'grade_level', 'sex', 'birthdate', 'password']);
        $classrooms = $request->selectedClassrooms();

        $account = DB::transaction(function () use ($accounts, $attributes, $classrooms): CreatedStudentAccount {
            $account = $accounts->create($attributes, $classrooms->first());

            foreach ($classrooms->skip(1) as $classroom) {
                $accounts->enroll($account->user, $classroom);
            }

            return $account;
        });

        $student = $account->user;
        $grade = GradeLevel::from((int) $attributes['grade_level']);

        $redirect = to_route('admin.students.show', $student)
            ->with('status', $student->name.' was added as a '.$grade->label().' learner.');

        if (blank($attributes['username'] ?? null) || blank($attributes['password'] ?? null)) {
            $redirect->with('credentials', [['name' => $student->name, 'username' => $student->username, 'password' => $account->plainPassword]]);
        }

        return $redirect;
    }

    /**
     * Show the learner's profile and game progress across every subject.
     */
    public function show(User $student, LearnerProgressService $progress): View
    {
        $student->load([
            'guardians' => fn (BelongsToMany $guardians): BelongsToMany => $guardians->orderBy('name')->orderBy('users.id'),
            'enrolledClassrooms' => fn (BelongsToMany $classrooms): BelongsToMany => $classrooms
                ->with(['subject', 'teacher', 'schoolYear'])
                ->orderByRaw('CASE WHEN classrooms.archived_at IS NULL THEN 0 ELSE 1 END')
                ->orderBy('name')
                ->orderBy('classrooms.id'),
        ]);

        return view('admin.students.show', [
            'student' => $student,
            'summary' => $progress->summarize($student),
        ]);
    }

    /**
     * Show the edit form of the learner.
     */
    public function edit(User $student): View
    {
        $student->load(['studentProfile', 'enrolledClassrooms']);

        return view('admin.students.edit', [
            'student' => $student,
            'classrooms' => UpdateStudentRequest::enrollableClassrooms(),
            'enrolledClassroomIds' => $student->enrolledClassrooms->modelKeys(),
        ]);
    }

    /**
     * Update the learner's account and profile, and sync the enrollments of the active school year's classrooms
     * (archived and past classrooms are never touched).
     */
    public function update(UpdateStudentRequest $request, User $student): RedirectResponse
    {
        $selectedIds = $request->selectedClassrooms()->modelKeys();
        $enrollableIds = $request->enrollable()->modelKeys();

        DB::transaction(function () use ($request, $student, $selectedIds, $enrollableIds): void {
            $student->update($request->safe()->only(['name', 'username']));
            $student->studentProfile()->updateOrCreate([], $request->safe()->only(['lrn', 'grade_level', 'sex', 'birthdate']));

            $currentIds = $student->enrolledClassrooms()->whereIn('classrooms.id', $enrollableIds)->pluck('classrooms.id')->all();

            $student->enrolledClassrooms()->detach(array_values(array_diff($currentIds, $selectedIds)));

            foreach (array_diff($selectedIds, $currentIds) as $classroomId) {
                $student->enrolledClassrooms()->attach($classroomId, ['enrolled_at' => now()]);
            }
        });

        return to_route('admin.students.show', $student)->with('status', 'Changes to '.$student->name."'s profile were saved.");
    }

    /**
     * Delete the learner with every enrollment, guardian link and game record (database cascades).
     */
    public function destroy(User $student): RedirectResponse
    {
        $student->delete();

        return to_route('admin.students.index')->with('status', $student->name.' was deleted.');
    }

    /**
     * Group classrooms by grade for a select: ['Grade 5' => [id => name], …].
     *
     * @param  EloquentCollection<int, Classroom>  $classrooms
     * @return array<string, array<int, string>>
     */
    private function classroomOptions(EloquentCollection $classrooms): array
    {
        return $classrooms
            ->groupBy(fn (Classroom $classroom): string => $classroom->grade_level->label())
            ->map(fn (EloquentCollection $group): array => $group->pluck('name', 'id')->all())
            ->all();
    }
}
