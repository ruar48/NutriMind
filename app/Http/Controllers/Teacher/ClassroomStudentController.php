<?php

namespace App\Http\Controllers\Teacher;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Teacher\StoreClassroomStudentRequest;
use App\Models\Classroom;
use App\Models\User;
use App\Services\StudentAccounts;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Attributes\Controllers\Authorize;
use Illuminate\View\View;

class ClassroomStudentController extends Controller
{
    /**
     * The most learners the enroll search lists.
     */
    public const SEARCH_LIMIT = 10;

    /**
     * The longest search term used by the enroll search.
     */
    private const SEARCH_MAX_LENGTH = 100;

    /**
     * Show the add-learner page: a new learner account form and a search for existing learners to enroll.
     */
    #[Authorize('manageStudents', 'classroom')]
    public function create(Request $request, Classroom $classroom): View
    {
        $classroom->load('subject');
        $search = $this->searchTerm($request);

        $matches = $search === null ? null : User::query()
            ->ofRole(UserRole::Student)
            ->active()
            ->whereHas('studentProfile', fn (Builder $profile) => $profile->where('grade_level', $classroom->grade_level))
            ->whereDoesntHave('enrolledClassrooms', fn (Builder $classrooms) => $classrooms->whereKey($classroom->id))
            ->where(function (Builder $query) use ($search): void {
                $query->search($search)
                    ->orWhereHas('studentProfile', fn (Builder $profile) => $profile->whereLike('student_profiles.lrn', '%'.$search.'%'));
            })
            ->with([
                'studentProfile',
                'enrolledClassrooms' => fn (BelongsToMany $classrooms) => $classrooms->active()->orderBy('classrooms.name')->orderBy('classrooms.id'),
            ])
            ->orderBy('users.name')
            ->orderBy('users.id')
            ->limit(self::SEARCH_LIMIT + 1)
            ->get();

        return view('teacher.classrooms.students.create', [
            'classroom' => $classroom,
            'search' => $search,
            'matches' => $matches?->take(self::SEARCH_LIMIT),
            'hasMoreMatches' => $matches !== null && $matches->count() > self::SEARCH_LIMIT,
        ]);
    }

    /**
     * Create a learner account in the classroom's grade, enroll it, and show its sign-in details once.
     */
    #[Authorize('manageStudents', 'classroom')]
    public function store(StoreClassroomStudentRequest $request, Classroom $classroom, StudentAccounts $accounts): RedirectResponse
    {
        $account = $accounts->create(
            $request->safe()->only(['name', 'lrn', 'sex', 'birthdate', 'username', 'password'])
            + ['grade_level' => $classroom->grade_level],
            $classroom,
        );

        return to_route('teacher.classrooms.show', $classroom)
            ->with('status', $account->user->name.' was added to '.$classroom->name.'. Save the sign-in details now — the password is shown only once.')
            ->with('credentials', [[
                'name' => $account->user->name,
                'username' => $account->user->username,
                'password' => $account->plainPassword,
            ]]);
    }

    /**
     * Remove a learner from the classroom; their attempts and progress stay in the records.
     */
    #[Authorize('manageStudents', 'classroom')]
    public function destroy(Classroom $classroom, User $student): RedirectResponse
    {
        abort_unless($classroom->students()->whereKey($student->id)->exists(), 404);

        $classroom->students()->detach($student->id);

        return to_route('teacher.classrooms.show', $classroom)
            ->with('status', $student->name.' was removed from '.$classroom->name.'. Their quiz results stay in your reports.');
    }

    /**
     * Get the trimmed search term (at most 100 characters), or null when there is none.
     */
    private function searchTerm(Request $request): ?string
    {
        $search = $request->query('search');

        if (! is_string($search)) {
            return null;
        }

        $search = mb_substr(trim($search), 0, self::SEARCH_MAX_LENGTH);

        return $search === '' ? null : $search;
    }
}
