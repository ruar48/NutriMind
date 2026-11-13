<?php

use App\Enums\Permission;
use App\Enums\UserRole;
use App\Models\User;
use App\Services\RolePermissions;
use App\Services\Settings;
use App\View\Composers\NavigationComposer;
use Illuminate\Support\Facades\Gate;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
});

/**
 * The default matrix as the form submits it, without the given permissions of one role.
 *
 * @param  list<Permission>  $without
 * @return array<string, list<string>>
 */
function setupRolesPayload(UserRole $role, array $without = []): array
{
    $matrix = [];

    foreach (RolePermissions::configurableRoles() as $configurableRole) {
        $matrix[$configurableRole->value] = collect(Permission::cases())
            ->filter(fn (Permission $permission): bool => in_array($configurableRole, $permission->defaultRoles(), true))
            ->reject(fn (Permission $permission): bool => $configurableRole === $role && in_array($permission, $without, true))
            ->map(fn (Permission $permission): string => $permission->value)
            ->values()
            ->all();
    }

    return array_filter($matrix);
}

/**
 * The labels of the sidebar items a user sees.
 *
 * @return list<string>
 */
function setupNavigationLabels(User $user): array
{
    test()->actingAs($user);
    $view = view('components.layouts.app');
    app(NavigationComposer::class)->compose($view);

    return collect($view->getData()['navigation'])->pluck('items')->flatten(1)->pluck('label')->all();
}

describe('index', function () {
    it('sends guests to the sign-in page', function () {
        $this->get(route('admin.roles.index'))->assertRedirectToRoute('login');
    });

    it('forbids every other role', function (string $role) {
        $this->actingAs(User::factory()->{$role}()->create())
            ->get(route('admin.roles.index'))
            ->assertForbidden();
    })->with(['teacher', 'parent', 'student']);

    it('shows the matrix grouped by permission group with only applicable cells editable', function () {
        $response = $this->actingAs($this->admin)->get(route('admin.roles.index'));

        $response->assertOk()
            ->assertViewIs('admin.roles.index')
            ->assertSeeInOrder(['Classrooms &amp; learners', 'Assessments', 'Teaching', 'Insights', 'Parent portal', 'Parent forum', 'Learning game'], false)
            ->assertSeeInOrder(['Administrator', 'Teacher', 'Parent / Guardian', 'Learner'])
            ->assertSeeText('Enforced by the NutriMind game')
            ->assertSeeText('Using the defaults')
            ->assertDontSee('name="permissions[admin][]"', false)
            ->assertSee('data-permission-locked', false)
            ->assertSeeText('Not available to Parent / Guardian');
        expect($response->getContent())
            ->toMatch('#name="permissions\[teacher\]\[\]"\s+value="create_quizzes"#')
            ->toMatch('#name="permissions\[parent\]\[\]"\s+value="access_forum"#')
            ->toMatch('#name="permissions\[student\]\[\]"\s+value="play_game"#')
            ->not->toMatch('#name="permissions\[parent\]\[\]"\s+value="create_quizzes"#')
            ->not->toMatch('#name="permissions\[student\]\[\]"\s+value="access_forum"#')
            ->and(substr_count($response->getContent(), 'data-permission-locked'))->toBe(count(Permission::cases()))
            ->and(substr_count($response->getContent(), 'name="permissions['))->toBe(21)
            ->and($response->viewData('roleSummaries')->map(fn (array $summary): array => [$summary['role'], $summary['granted'], $summary['applicable'], $summary['changed']])->all())
            ->toBe([[UserRole::Teacher, 12, 12, 0], [UserRole::Parent, 5, 5, 0], [UserRole::Student, 4, 4, 0]]);
    });

    it('marks the granted permissions as checked and flags changes from the defaults', function () {
        app(RolePermissions::class)->revoke(UserRole::Teacher, Permission::ShareQuestions);

        $response = $this->actingAs($this->admin)->get(route('admin.roles.index'));

        $response->assertOk()->assertSeeText('Customized')->assertSeeText('1 change from the defaults');
        expect($response->getContent())
            ->toMatch('#<input[^>]*name="permissions\[teacher\]\[\]"\s+value="create_quizzes"[^>]*\schecked(?=[\s/>])#')
            ->not->toMatch('#<input[^>]*name="permissions\[teacher\]\[\]"\s+value="share_questions"[^>]*\schecked(?=[\s/>])#');
    });
});

describe('update', function () {
    it('saves the matrix and the gates and navigation follow it', function () {
        $teacher = User::factory()->teacher()->create();
        expect(setupNavigationLabels($teacher))->toContain('Question Bank');

        $this->actingAs($this->admin)
            ->put(route('admin.roles.update'), ['permissions' => setupRolesPayload(UserRole::Teacher, [Permission::ManageQuestionBank, Permission::ExportReports])])
            ->assertRedirectToRoute('admin.roles.index')
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status', 'Role permissions saved: 0 permissions granted, 2 revoked.');

        expect(Gate::forUser($teacher)->allows(Permission::ManageQuestionBank->value))->toBeFalse()
            ->and(Gate::forUser($teacher)->allows(Permission::ExportReports->value))->toBeFalse()
            ->and(Gate::forUser($teacher)->allows(Permission::CreateQuizzes->value))->toBeTrue()
            ->and(setupNavigationLabels($teacher))->not->toContain('Question Bank')->toContain('Quizzes');
    });

    it('grants a permission back', function () {
        app(RolePermissions::class)->revoke(UserRole::Parent, Permission::ViewQuizDetails);
        $parent = User::factory()->parent()->create();

        $this->actingAs($this->admin)
            ->put(route('admin.roles.update'), ['permissions' => setupRolesPayload(UserRole::Parent)])
            ->assertSessionHas('status', 'Role permissions saved: 1 permission granted, 0 revoked.');

        expect($parent->hasPermission(Permission::ViewQuizDetails))->toBeTrue();
    });

    it('grants nothing to a role whose boxes are all unchecked', function () {
        $parent = User::factory()->parent()->create();
        $payload = setupRolesPayload(UserRole::Parent);
        unset($payload['parent']);

        $this->actingAs($this->admin)
            ->put(route('admin.roles.update'), ['permissions' => $payload])
            ->assertSessionHas('status', 'Role permissions saved: 0 permissions granted, 5 revoked.');

        expect(app(RolePermissions::class)->matrix()['parent'])->toBe([])
            ->and(setupNavigationLabels($parent))->not->toContain('Parent Forum')
            ->and(User::factory()->teacher()->create()->hasPermission(Permission::AccessForum))->toBeTrue();
    });

    it('says so when nothing changed', function () {
        $this->actingAs($this->admin)
            ->put(route('admin.roles.update'), ['permissions' => setupRolesPayload(UserRole::Teacher)])
            ->assertSessionHas('status', 'Role permissions saved. Nothing changed.');
    });

    it('resets every role to the defaults', function () {
        $permissions = app(RolePermissions::class);
        $permissions->revoke(UserRole::Teacher, Permission::CreateQuizzes, Permission::GiveFeedback);
        $permissions->revoke(UserRole::Student, Permission::UseOfflineMode);

        $this->actingAs($this->admin)
            ->put(route('admin.roles.update'), ['reset' => '1', 'permissions' => ['teacher' => []]])
            ->assertRedirectToRoute('admin.roles.index')
            ->assertSessionHas('status', 'Role permissions were reset to the defaults.');

        expect(app(Settings::class)->get(RolePermissions::SETTING_KEY))->toBeNull()
            ->and(User::factory()->teacher()->create()->hasPermission(Permission::CreateQuizzes))->toBeTrue()
            ->and(User::factory()->student()->create()->hasPermission(Permission::UseOfflineMode))->toBeTrue();
    });

    it('rejects a matrix that is not valid', function (array $payload, string $field, string $message) {
        $this->actingAs($this->admin)
            ->put(route('admin.roles.update'), $payload)
            ->assertSessionHasErrors([$field => $message]);

        expect(app(Settings::class)->get(RolePermissions::SETTING_KEY))->toBeNull();
    })->with([
        'administrator column' => [['permissions' => ['admin' => ['create_quizzes']]], 'permissions', 'Permissions can only be changed for teachers, parents and learners.'],
        'unknown role' => [['permissions' => ['principal' => ['create_quizzes']]], 'permissions', 'Permissions can only be changed for teachers, parents and learners.'],
        'role value is not a list' => [['permissions' => ['teacher' => 'create_quizzes']], 'permissions.teacher', 'The permissions of a role must be a list.'],
        'unknown permission' => [['permissions' => ['teacher' => ['fly_rockets']]], 'permissions.teacher.0', 'One of the permissions does not exist. Reload the page and try again.'],
        'permission of another role' => [['permissions' => ['parent' => ['view_child_progress', 'create_quizzes']]], 'permissions.parent', 'Create and manage quizzes cannot be granted to Parent / Guardian accounts.'],
        'non-boolean reset' => [['reset' => 'please'], 'reset', 'The reset field must be true or false.'],
    ]);

    it('forbids every other role before validating', function (string $role) {
        $this->actingAs(User::factory()->{$role}()->create())
            ->put(route('admin.roles.update'), ['permissions' => 'invalid'])
            ->assertForbidden()
            ->assertSessionHasNoErrors();

        expect(app(Settings::class)->get(RolePermissions::SETTING_KEY))->toBeNull();
    })->with(['teacher', 'parent']);

    it('sends guests to the sign-in page', function () {
        $this->put(route('admin.roles.update'), ['reset' => '1'])->assertRedirectToRoute('login');
    });
});
