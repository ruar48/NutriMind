<?php

use App\Enums\UserRole;
use App\Http\Controllers\Admin\UserController;
use App\Models\Classroom;
use App\Models\Quiz;
use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Hash;

$otherRoles = [
    'teacher' => UserRole::Teacher,
    'parent' => UserRole::Parent,
    'learner' => UserRole::Student,
];

describe('index', function () use ($otherRoles) {
    it('lists every account with role counts', function () {
        $admin = User::factory()->admin()->create();
        User::factory()->teacher()->count(2)->create();
        User::factory()->parent()->create();
        User::factory()->student()->count(3)->create();

        $this->actingAs($admin)
            ->get(route('admin.users.index'))
            ->assertOk()
            ->assertViewIs('admin.users.index')
            ->assertViewHas('counts', ['all' => 7, 'admin' => 1, 'teacher' => 2, 'parent' => 1, 'student' => 3])
            ->assertViewHas('users', fn (LengthAwarePaginator $users): bool => $users->total() === 7);
    });

    it('limits the list to the role tab', function () {
        $admin = User::factory()->admin()->create();
        $teacher = User::factory()->teacher()->create();
        User::factory()->parent()->create();

        $this->actingAs($admin)
            ->get(route('admin.users.index', ['role' => 'teacher']))
            ->assertOk()
            ->assertViewHas('role', UserRole::Teacher)
            ->assertViewHas('users', fn (LengthAwarePaginator $users): bool => $users->pluck('id')->all() === [$teacher->id]);
    });

    it('ignores an unknown role tab', function (mixed $role) {
        $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.users.index', ['role' => $role]))
            ->assertOk()
            ->assertViewHas('role', null);
    })->with([
        'unknown role' => 'janitor',
        'array' => [['admin']],
    ]);

    it('searches by name, email, username and LRN', function (string $term) {
        $admin = User::factory()->admin()->create(['name' => 'Josefina Ramos']);
        $match = User::factory()->student()->create(['name' => 'Sofia Navarro', 'username' => 'sofia_navarro']);
        $match->studentProfile->update(['lrn' => '136742210077']);
        User::factory()->parent()->create(['name' => 'Ana Navarro', 'email' => 'ana@example.com']);

        $this->actingAs($admin)
            ->get(route('admin.users.index', ['search' => $term]))
            ->assertOk()
            ->assertViewHas('users', fn (LengthAwarePaginator $users): bool => $users->pluck('id')->all() === [$match->id]);
    })->with([
        'name' => 'Sofia',
        'username' => 'sofia_nav',
        'LRN' => '210077',
    ]);

    it('counts the tabs within the search and status filters', function () {
        $admin = User::factory()->admin()->create(['name' => 'Josefina Ramos']);
        User::factory()->teacher()->create(['name' => 'Maricel Santos']);
        User::factory()->teacher()->inactive()->create(['name' => 'Ramon Santos']);
        User::factory()->parent()->create(['name' => 'Lorna Santos']);

        $this->actingAs($admin)
            ->get(route('admin.users.index', ['search' => 'Santos', 'status' => 'active']))
            ->assertViewHas('counts', ['all' => 2, 'admin' => 0, 'teacher' => 1, 'parent' => 1, 'student' => 0]);
    });

    it('filters by account status', function (string $status, string $expected) {
        $admin = User::factory()->admin()->create(['name' => 'Active Admin']);
        $inactive = User::factory()->teacher()->inactive()->create();

        $this->actingAs($admin)
            ->get(route('admin.users.index', ['status' => $status]))
            ->assertViewHas('users', fn (LengthAwarePaginator $users): bool => $users->pluck('id')->all() === [$expected === 'admin' ? $admin->id : $inactive->id]);
    })->with([
        'active' => ['active', 'admin'],
        'inactive' => ['inactive', 'teacher'],
    ]);

    it('sorts by last sign-in, most recent first', function () {
        $this->freezeSecond();
        $admin = User::factory()->admin()->create(['name' => 'Admin', 'last_login_at' => now()->subDays(3)]);
        $recent = User::factory()->teacher()->create(['name' => 'Zed', 'last_login_at' => now()->subHour()]);
        $never = User::factory()->parent()->create(['name' => 'Abe', 'last_login_at' => null]);

        $this->actingAs($admin)
            ->get(route('admin.users.index', ['sort' => 'last_login_at', 'direction' => 'desc']))
            ->assertViewHas('users', fn (LengthAwarePaginator $users): bool => $users->pluck('id')->all() === [$recent->id, $admin->id, $never->id]);
    });

    it('orders by name by default and paginates 20 accounts per page', function () {
        $admin = User::factory()->admin()->create(['name' => 'Aaron Admin']);
        User::factory()->parent()->count(21)->sequence(fn ($sequence) => ['name' => 'Parent '.str_pad((string) $sequence->index, 2, '0', STR_PAD_LEFT)])->create();

        $this->actingAs($admin)
            ->get(route('admin.users.index', ['page' => 2]))
            ->assertViewHas('users', fn (LengthAwarePaginator $users): bool => $users->total() === 22
                && $users->perPage() === 20
                && $users->pluck('name')->all() === ['Parent 19', 'Parent 20']);
    });

    it('renders every kind of account with its actions', function () {
        $admin = User::factory()->admin()->create(['name' => 'Josefina Ramos']);
        User::factory()->teacher()->create(['name' => 'Maricel Santos', 'last_login_at' => null]);
        User::factory()->parent()->inactive()->create(['name' => 'Rodel Javier']);
        $learner = User::factory()->student()->create(['name' => 'Sofia Navarro']);

        $this->actingAs($admin)
            ->get(route('admin.users.index'))
            ->assertOk()
            ->assertSeeText(['Josefina Ramos', 'Maricel Santos', 'Rodel Javier', 'Sofia Navarro'])
            ->assertSeeText(['Never', 'Game account', 'Inactive'])
            ->assertSee('href="'.route('admin.students.edit', $learner).'"', false)
            ->assertSee('action="'.route('admin.users.destroy', $learner).'"', false)
            ->assertSee('Their classroom enrollments, quiz attempts, mission and activity progress, rewards, feedback and guardian links are deleted too.', false);
    });

    it('offers no status, password or delete actions on the signed-in administrator', function () {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get(route('admin.users.index'))
            ->assertOk()
            ->assertSeeText('This is your account')
            ->assertDontSee('action="'.route('admin.users.destroy', $admin).'"', false)
            ->assertDontSee('action="'.route('admin.users.status.update', $admin).'"', false);
    });

    it('shows an empty state when nothing matches the filters', function () {
        $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.users.index', ['search' => 'nobody-here']))
            ->assertOk()
            ->assertSeeText('No users match your filters');
    });

    it('shows an empty state for a role without accounts', function () {
        $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.users.index', ['role' => 'parent']))
            ->assertOk()
            ->assertSeeText('No parents yet');
    });

    it('sends guests to the sign-in page', function () {
        $this->get(route('admin.users.index'))->assertRedirectToRoute('login');
    });

    it('forbids other roles', function (UserRole $role) {
        $this->actingAs(User::factory()->{$role->value}()->create())
            ->get(route('admin.users.index'))
            ->assertForbidden();
    })->with($otherRoles);
});

describe('create', function () use ($otherRoles) {
    it('shows the account form with the role from the query preselected', function () {
        $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.users.create', ['role' => 'teacher']))
            ->assertOk()
            ->assertViewIs('admin.users.create')
            ->assertViewHas('role', UserRole::Teacher)
            ->assertSee('action="'.route('admin.users.store').'"', false)
            ->assertSeeHtmlInOrder(['value="teacher"', 'checked']);
    });

    it('preselects nothing for an unknown role', function () {
        $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.users.create', ['role' => 'janitor']))
            ->assertOk()
            ->assertViewHas('role', null);
    });

    it('sends learner accounts to the learner form', function () {
        $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.users.create', ['role' => 'student']))
            ->assertRedirectToRoute('admin.students.create');
    });

    it('sends guests to the sign-in page', function () {
        $this->get(route('admin.users.create'))->assertRedirectToRoute('login');
    });

    it('forbids other roles', function (UserRole $role) {
        $this->actingAs(User::factory()->{$role->value}()->create())
            ->get(route('admin.users.create'))
            ->assertForbidden();
    })->with($otherRoles);
});

describe('store', function () use ($otherRoles) {
    $validPayload = fn (array $overrides = []): array => [
        'role' => 'teacher',
        'name' => 'Maricel Santos',
        'email' => 'maricel@school.test',
        'username' => 'msantos',
        'phone' => '09171234567',
        ...$overrides,
    ];

    it('creates an active teacher with a generated password shown once', function () use ($validPayload) {
        $response = $this->actingAs(User::factory()->admin()->create())
            ->post(route('admin.users.store'), $validPayload());

        $teacher = User::query()->where('email', 'maricel@school.test')->firstOrFail();
        $response->assertRedirect(route('admin.teachers.show', $teacher))
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status', 'Teacher account created for Maricel Santos.');
        $credentials = session('credentials');
        expect($teacher)
            ->role->toBe(UserRole::Teacher)
            ->is_active->toBeTrue()
            ->name->toBe('Maricel Santos')
            ->username->toBe('msantos')
            ->phone->toBe('09171234567')
            ->and($credentials)->toHaveCount(1)
            ->and($credentials[0]['name'])->toBe('Maricel Santos')
            ->and($credentials[0]['username'])->toBe('maricel@school.test')
            ->and($credentials[0]['password'])->toMatch('/^(?=.*[a-zA-Z])(?=.*\d)[a-zA-Z\d]{12}$/')
            ->and(Hash::check($credentials[0]['password'], $teacher->password))->toBeTrue();
    });

    it('keeps a typed password and shows no credentials', function () use ($validPayload) {
        $this->actingAs(User::factory()->admin()->create())
            ->post(route('admin.users.store'), $validPayload(['password' => 'garden2026', 'password_confirmation' => 'garden2026']))
            ->assertSessionHasNoErrors()
            ->assertSessionMissing('credentials');

        expect(Hash::check('garden2026', User::query()->where('email', 'maricel@school.test')->value('password')))->toBeTrue();
    });

    it('sends each account type to its page', function (string $role, Closure $expectedUrl) use ($validPayload) {
        $response = $this->actingAs(User::factory()->admin()->create())
            ->post(route('admin.users.store'), $validPayload(['role' => $role]));

        $response->assertRedirect($expectedUrl(User::query()->where('email', 'maricel@school.test')->firstOrFail()));
    })->with([
        'administrator' => ['admin', fn (User $user): string => route('admin.users.edit', $user)],
        'teacher' => ['teacher', fn (User $user): string => route('admin.teachers.show', $user)],
        'parent' => ['parent', fn (User $user): string => route('admin.parents.show', $user)],
    ]);

    it('never mass assigns the account status', function () use ($validPayload) {
        $this->actingAs(User::factory()->admin()->create())
            ->post(route('admin.users.store'), $validPayload(['is_active' => '0', 'email_verified_at' => '2020-01-01']));

        expect(User::query()->where('email', 'maricel@school.test')->firstOrFail())
            ->is_active->toBeTrue()
            ->email_verified_at->toBeNull();
    });

    it('requires the account type, name and email address', function () {
        $this->actingAs(User::factory()->admin()->create())
            ->post(route('admin.users.store'), [])
            ->assertSessionHasErrors([
                'role' => 'Choose the type of account.',
                'name' => 'The full name field is required.',
                'email' => 'The email address field is required.',
            ]);

        expect(User::query()->count())->toBe(1);
    });

    it('rejects invalid account details', function (array $overrides, string $field, string $message) use ($validPayload) {
        User::factory()->parent()->create(['email' => 'taken@school.test', 'username' => 'TakenName']);

        $this->actingAs(User::factory()->admin()->create())
            ->post(route('admin.users.store'), $validPayload($overrides))
            ->assertSessionHasErrors([$field => $message]);

        $this->assertDatabaseMissing('users', ['name' => 'Maricel Santos']);
    })->with([
        'learner role' => [['role' => 'student'], 'role', 'Choose an administrator, teacher or parent account. Learners are added under Learners.'],
        'unknown role' => [['role' => 'janitor'], 'role', 'Choose an administrator, teacher or parent account. Learners are added under Learners.'],
        'long name' => [['name' => str_repeat('a', 256)], 'name', 'The full name field must not be greater than 255 characters.'],
        'invalid email' => [['email' => 'not-an-email'], 'email', 'The email address field must be a valid email address.'],
        'email taken in another case' => [['email' => 'TAKEN@school.test'], 'email', 'The email address has already been taken.'],
        'username taken in another case' => [['username' => 'takenname'], 'username', 'The username has already been taken.'],
        'username with spaces' => [['username' => 'm santos'], 'username', 'The username field must only contain letters, numbers, dashes, and underscores.'],
        'username with accents' => [['username' => 'josé'], 'username', 'The username field must only contain letters, numbers, dashes, and underscores.'],
        'long username' => [['username' => str_repeat('a', 31)], 'username', 'The username field must not be greater than 30 characters.'],
        'long phone' => [['phone' => str_repeat('9', 31)], 'phone', 'The mobile number field must not be greater than 30 characters.'],
        'short password' => [['password' => 'abc123', 'password_confirmation' => 'abc123'], 'password', 'The password field must be at least 8 characters.'],
        'password without digits' => [['password' => 'gardening', 'password_confirmation' => 'gardening'], 'password', 'The password field must contain at least one number.'],
        'unconfirmed password' => [['password' => 'garden2026', 'password_confirmation' => 'garden2027'], 'password', 'The password field confirmation does not match.'],
    ]);

    it('sends guests to the sign-in page', function () use ($validPayload) {
        $this->post(route('admin.users.store'), $validPayload())->assertRedirectToRoute('login');

        $this->assertDatabaseMissing('users', ['email' => 'maricel@school.test']);
    });

    it('forbids other roles', function (UserRole $role) use ($validPayload) {
        $this->actingAs(User::factory()->{$role->value}()->create())
            ->post(route('admin.users.store'), $validPayload())
            ->assertForbidden();

        $this->assertDatabaseMissing('users', ['email' => 'maricel@school.test']);
    })->with($otherRoles);
});

describe('edit', function () use ($otherRoles) {
    it('shows the profile form of a staff account', function () {
        $teacher = User::factory()->teacher()->create(['name' => 'Maricel Santos', 'email' => 'maricel@school.test']);

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.users.edit', $teacher))
            ->assertOk()
            ->assertViewIs('admin.users.edit')
            ->assertSee('value="maricel@school.test"', false)
            ->assertSee('action="'.route('admin.users.update', $teacher).'"', false)
            ->assertSee('action="'.route('admin.users.password.update', $teacher).'"', false)
            ->assertSeeText('The account type cannot be changed.');
    });

    it('points administrators to Account settings for their own password', function () {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get(route('admin.users.edit', $admin))
            ->assertOk()
            ->assertSeeText('Change your own password in')
            ->assertDontSee('action="'.route('admin.users.password.update', $admin).'"', false);
    });

    it('sends learners to the learner form', function () {
        $learner = User::factory()->student()->create();

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.users.edit', $learner))
            ->assertRedirectToRoute('admin.students.edit', $learner);
    });

    it('reports an unknown account as missing', function () {
        $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.users.edit', 999999))
            ->assertNotFound();
    });

    it('sends guests to the sign-in page', function () {
        $this->get(route('admin.users.edit', User::factory()->teacher()->create()))->assertRedirectToRoute('login');
    });

    it('forbids other roles', function (UserRole $role) {
        $this->actingAs(User::factory()->{$role->value}()->create())
            ->get(route('admin.users.edit', User::factory()->teacher()->create()))
            ->assertForbidden();
    })->with($otherRoles);
});

describe('update', function () use ($otherRoles) {
    it('updates the profile and keeps the role', function () {
        $parent = User::factory()->parent()->create(['email' => 'ana@example.com']);

        $response = $this->actingAs(User::factory()->admin()->create())
            ->put(route('admin.users.update', $parent), [
                'name' => '  Ana   Navarro ',
                'email' => 'ana.navarro@example.com',
                'username' => 'anavarro',
                'phone' => '09181234567',
                'role' => 'admin',
            ]);

        $response->assertRedirect(route('admin.parents.show', $parent))
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status', "Changes to Ana Navarro's account were saved.");
        expect($parent->refresh())
            ->name->toBe('Ana Navarro')
            ->email->toBe('ana.navarro@example.com')
            ->username->toBe('anavarro')
            ->phone->toBe('09181234567')
            ->role->toBe(UserRole::Parent);
    });

    it('keeps the account’s own email address and username', function () {
        $teacher = User::factory()->teacher()->create(['email' => 'maricel@school.test', 'username' => 'msantos']);

        $this->actingAs(User::factory()->admin()->create())
            ->put(route('admin.users.update', $teacher), ['name' => 'Maricel Santos', 'email' => 'Maricel@school.test', 'username' => 'MSantos'])
            ->assertRedirect(route('admin.teachers.show', $teacher))
            ->assertSessionHasNoErrors();

        expect($teacher->refresh())->email->toBe('Maricel@school.test')->username->toBe('MSantos');
    });

    it('sends administrators back to their edit page', function () {
        $other = User::factory()->admin()->create();

        $this->actingAs(User::factory()->admin()->create())
            ->put(route('admin.users.update', $other), ['name' => 'Rowena Cruz', 'email' => 'rowena@school.test'])
            ->assertRedirect(route('admin.users.edit', $other));
    });

    it('rejects details another account uses', function (string $field, string $value, string $message) {
        User::factory()->teacher()->create(['email' => 'taken@school.test', 'username' => 'taken']);
        $parent = User::factory()->parent()->create(['email' => 'ana@example.com']);

        $this->actingAs(User::factory()->admin()->create())
            ->put(route('admin.users.update', $parent), [...['name' => 'Ana Navarro', 'email' => 'ana@example.com'], $field => $value])
            ->assertSessionHasErrors([$field => $message]);

        expect($parent->refresh()->email)->toBe('ana@example.com');
    })->with([
        'email' => ['email', 'taken@school.test', 'The email address has already been taken.'],
        'username' => ['username', 'TAKEN', 'The username has already been taken.'],
    ]);

    it('requires a name and an email address', function () {
        $this->actingAs(User::factory()->admin()->create())
            ->put(route('admin.users.update', User::factory()->parent()->create()), [])
            ->assertSessionHasErrors([
                'name' => 'The full name field is required.',
                'email' => 'The email address field is required.',
            ]);
    });

    it('reports learners as missing', function () {
        $learner = User::factory()->student()->create(['name' => 'Sofia Navarro']);

        $this->actingAs(User::factory()->admin()->create())
            ->put(route('admin.users.update', $learner), ['name' => 'Changed', 'email' => 'sofia@example.com'])
            ->assertNotFound();

        expect($learner->refresh())->name->toBe('Sofia Navarro')->email->toBeNull();
    });

    it('sends guests to the sign-in page', function () {
        $parent = User::factory()->parent()->create();

        $this->put(route('admin.users.update', $parent), ['name' => 'Changed', 'email' => 'changed@example.com'])
            ->assertRedirectToRoute('login');
    });

    it('forbids other roles', function (UserRole $role) {
        $parent = User::factory()->parent()->create(['name' => 'Ana Navarro']);

        $this->actingAs(User::factory()->{$role->value}()->create())
            ->put(route('admin.users.update', $parent), ['name' => 'Changed', 'email' => 'changed@example.com'])
            ->assertForbidden();

        expect($parent->refresh()->name)->toBe('Ana Navarro');
    })->with($otherRoles);
});

describe('destroy', function () use ($otherRoles) {
    it('deletes a parent account', function () {
        $parent = User::factory()->parent()->create(['name' => 'Rodel Javier']);

        $this->actingAs(User::factory()->admin()->create())
            ->delete(route('admin.users.destroy', $parent))
            ->assertRedirect(route('admin.parents.index'))
            ->assertSessionHas('status', 'Rodel Javier was deleted.');

        $this->assertModelMissing($parent);
    });

    it('deletes a learner with the learner profile', function () {
        $learner = User::factory()->student()->create();
        $profile = $learner->studentProfile;

        $this->actingAs(User::factory()->admin()->create())
            ->delete(route('admin.users.destroy', $learner))
            ->assertRedirect(route('admin.students.index'));

        $this->assertModelMissing($learner);
        expect(StudentProfile::query()->whereKey($profile->id)->exists())->toBeFalse();
    });

    it('deletes a teacher who owns no classrooms or quizzes', function () {
        $teacher = User::factory()->teaching()->create();

        $this->actingAs(User::factory()->admin()->create())
            ->delete(route('admin.users.destroy', $teacher))
            ->assertRedirect(route('admin.teachers.index'));

        $this->assertModelMissing($teacher);
    });

    it('deletes another administrator while one stays active', function () {
        $other = User::factory()->admin()->create();

        $this->actingAs(User::factory()->admin()->create())
            ->delete(route('admin.users.destroy', $other))
            ->assertRedirect(route('admin.users.index', ['role' => 'admin']));

        $this->assertModelMissing($other);
    });

    it('refuses to delete the signed-in administrator', function () {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->from(route('admin.users.index'))
            ->delete(route('admin.users.destroy', $admin))
            ->assertRedirect(route('admin.users.index'))
            ->assertSessionHas('error', "You can't delete your own account.");

        $this->assertModelExists($admin);
    });

    it('refuses to delete the last active administrator', function () {
        $admin = User::factory()->admin()->create();
        $lastActive = User::factory()->admin()->create();
        User::query()->whereKey($admin->id)->update(['is_active' => false]);

        $this->actingAs($admin)
            ->from(route('admin.users.index'))
            ->delete(route('admin.users.destroy', $lastActive))
            ->assertRedirect(route('admin.users.index'))
            ->assertSessionHas('error', UserController::LAST_ADMINISTRATOR_MESSAGE);

        $this->assertModelExists($lastActive);
    });

    it('refuses to delete a teacher who still owns records', function (int $classrooms, int $quizzes, string $message) {
        $teacher = User::factory()->teaching()->create();
        Classroom::factory()->count($classrooms)->for($teacher, 'teacher')->create();
        Quiz::factory()->count($quizzes)->for($teacher, 'teacher')->create();

        $this->actingAs(User::factory()->admin()->create())
            ->from(route('admin.teachers.show', $teacher))
            ->delete(route('admin.users.destroy', $teacher))
            ->assertRedirect(route('admin.teachers.show', $teacher))
            ->assertSessionHas('error', $message);

        $this->assertModelExists($teacher);
    })->with([
        'classrooms' => [2, 0, 'Deactivate this teacher instead — they still own 2 classrooms.'],
        'one quiz' => [0, 1, 'Deactivate this teacher instead — they still own 1 quiz.'],
        'both' => [1, 3, 'Deactivate this teacher instead — they still own 1 classroom and 3 quizzes.'],
    ]);

    it('counts archived classrooms as owned', function () {
        $teacher = User::factory()->teaching()->create();
        Classroom::factory()->archived()->for($teacher, 'teacher')->create();

        $this->actingAs(User::factory()->admin()->create())
            ->delete(route('admin.users.destroy', $teacher))
            ->assertSessionHas('error', 'Deactivate this teacher instead — they still own 1 classroom.');

        $this->assertModelExists($teacher);
    });

    it('sends guests to the sign-in page', function () {
        $parent = User::factory()->parent()->create();

        $this->delete(route('admin.users.destroy', $parent))->assertRedirectToRoute('login');

        $this->assertModelExists($parent);
    });

    it('forbids other roles', function (UserRole $role) {
        $parent = User::factory()->parent()->create();

        $this->actingAs(User::factory()->{$role->value}()->create())
            ->delete(route('admin.users.destroy', $parent))
            ->assertForbidden();

        $this->assertModelExists($parent);
    })->with($otherRoles);
});
