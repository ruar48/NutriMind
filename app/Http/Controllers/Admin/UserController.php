<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Admin\Concerns\ManagesPeopleAccounts;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\IndexUserRequest;
use App\Http\Requests\Admin\StoreUserRequest;
use App\Http\Requests\Admin\UpdateUserRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Every account of the school (administrators, teachers, parents and learners). Learners are created and edited
 * through the learner forms; everything else about them (status, password, deletion) works here too.
 */
class UserController extends Controller
{
    use ManagesPeopleAccounts;

    /**
     * Accounts per page.
     */
    private const PER_PAGE = 20;

    /**
     * Length of a generated staff password (letters and digits).
     */
    public const GENERATED_PASSWORD_LENGTH = 12;

    /**
     * List the accounts with role tabs (counts follow the search and status filters), search, status and sort.
     */
    public function index(IndexUserRequest $request): View
    {
        $role = $request->role();
        $sort = $request->sort();

        $filtered = $request->constrainStatus($request->constrainSearch(User::query(), includeLrn: true));

        $roleCounts = (clone $filtered)
            ->toBase()
            ->select('role')
            ->selectRaw('count(*) as aggregate')
            ->groupBy('role')
            ->pluck('aggregate', 'role');

        $counts = ['all' => (int) $roleCounts->sum()];

        foreach (UserRole::cases() as $case) {
            $counts[$case->value] = (int) ($roleCounts[$case->value] ?? 0);
        }

        $users = $filtered
            ->when($role, fn (Builder $query, UserRole $role): Builder => $query->ofRole($role))
            ->orderBy($sort, $request->direction($sort === 'name' ? 'asc' : 'desc'))
            ->when($sort !== 'name', fn (Builder $query): Builder => $query->orderBy('name'))
            ->orderBy('id')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        return view('admin.users.index', [
            'users' => $users,
            'role' => $role,
            'counts' => $counts,
            'isFiltered' => $request->search() !== null || $request->status() !== null,
        ]);
    }

    /**
     * Show the form for a new administrator, teacher or parent account (?role= preselects the type).
     */
    public function create(Request $request): View|RedirectResponse
    {
        $role = $request->query('role');

        if ($role === UserRole::Student->value) {
            return to_route('admin.students.create');
        }

        $role = is_string($role) ? UserRole::tryFrom($role) : null;

        return view('admin.users.create', [
            'role' => $role?->canAccessWeb() ? $role : null,
            'roles' => UserRole::webPortals(),
        ]);
    }

    /**
     * Create the account; without a typed password one is generated and shown once.
     */
    public function store(StoreUserRequest $request): RedirectResponse
    {
        $role = $request->role();
        $isGenerated = blank($request->validated('password'));
        $plainPassword = $isGenerated ? Str::password(self::GENERATED_PASSWORD_LENGTH, symbols: false) : $request->validated('password');

        $user = new User([...$request->safe()->only(['name', 'email', 'username', 'phone']), 'password' => $plainPassword]);
        $user->role = $role;
        $user->save();

        $redirect = redirect()->to($this->accountUrl($user))
            ->with('status', $role->label().' account created for '.$user->name.'.');

        if ($isGenerated) {
            $redirect->with('credentials', [['name' => $user->name, 'username' => $user->email, 'password' => $plainPassword]]);
        }

        return $redirect;
    }

    /**
     * Show the edit form of an administrator, teacher or parent account; learners use the learner form.
     */
    public function edit(User $user): View|RedirectResponse
    {
        if ($user->isStudent()) {
            return to_route('admin.students.edit', $user);
        }

        return view('admin.users.edit', [
            'user' => $user,
        ]);
    }

    /**
     * Update the profile of an administrator, teacher or parent account. The role never changes.
     */
    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        abort_if($user->isStudent(), Response::HTTP_NOT_FOUND);

        $user->update($request->safe()->only(['name', 'email', 'username', 'phone']));

        return redirect()->to($this->accountUrl($user))->with('status', 'Changes to '.$user->name."'s account were saved.");
    }

    /**
     * Delete the account, unless it is the signed-in administrator, the last active administrator, or a teacher who
     * still owns classrooms or quizzes (those must be deactivated instead).
     */
    public function destroy(Request $request, User $user): RedirectResponse
    {
        if ($user->is($request->user())) {
            return back(fallback: $this->accountUrl($user))->with('error', "You can't delete your own account.");
        }

        $user->loadCount(['classrooms', 'quizzes']);

        if ($user->classrooms_count > 0 || $user->quizzes_count > 0) {
            return back(fallback: $this->accountUrl($user))->with('error', $this->ownedRecordsMessage($user));
        }

        $name = $user->name;
        $role = $user->role;

        $isDeleted = DB::transaction(function () use ($user): bool {
            if ($this->isLastActiveAdministrator($user)) {
                return false;
            }

            return (bool) $user->delete();
        });

        if (! $isDeleted) {
            return back(fallback: $this->accountUrl($user))->with('error', self::LAST_ADMINISTRATOR_MESSAGE);
        }

        return redirect()->to($this->accountListUrl($role))->with('status', $name.' was deleted.');
    }

    /**
     * Explain why an account that owns classrooms or quizzes cannot be deleted.
     */
    private function ownedRecordsMessage(User $user): string
    {
        $owned = collect([
            $user->classrooms_count > 0 ? trans_choice(':count classroom|:count classrooms', $user->classrooms_count) : null,
            $user->quizzes_count > 0 ? trans_choice(':count quiz|:count quizzes', $user->quizzes_count) : null,
        ])->filter()->join(' and ');

        return ($user->isTeacher() ? 'Deactivate this teacher instead' : 'Deactivate this account instead').' — they still own '.$owned.'.';
    }
}
