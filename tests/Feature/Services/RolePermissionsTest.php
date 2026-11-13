<?php

use App\Enums\Permission;
use App\Enums\UserRole;
use App\Models\User;
use App\Services\RolePermissions;
use App\Services\Settings;
use Illuminate\Support\Facades\Gate;

it('grants every permission its enum default roles while nothing is stored', function () {
    expect(app(RolePermissions::class)->matrix())->toBe([
        'teacher' => [
            'manage_classrooms', 'manage_students', 'import_students', 'create_quizzes', 'manage_question_bank',
            'share_questions', 'manage_lesson_plans', 'give_feedback', 'view_analytics', 'export_reports',
            'access_forum', 'post_in_forum',
        ],
        'parent' => ['view_child_progress', 'view_quiz_details', 'view_teacher_feedback', 'access_forum', 'post_in_forum'],
        'student' => ['play_game', 'use_offline_mode', 'choose_learning_mode', 'view_leaderboard'],
    ]);
});

it('grants administrators every permission', function () {
    expect(app(RolePermissions::class)->granted(UserRole::Admin))->toBe(Permission::cases());
});

it('lists the granted permissions of a role as enum cases', function () {
    expect(app(RolePermissions::class)->granted(UserRole::Parent))->toBe([
        Permission::ViewChildProgress,
        Permission::ViewQuizDetails,
        Permission::ViewTeacherFeedback,
        Permission::AccessForum,
        Permission::PostInForum,
    ]);
});

it('persists an updated matrix in the role_permissions setting', function () {
    app(RolePermissions::class)->update([
        'teacher' => ['give_feedback', 'create_quizzes'],
        'parent' => ['access_forum'],
        'student' => ['play_game'],
    ]);

    app()->forgetScopedInstances();

    expect(app(Settings::class)->get('role_permissions'))->toBe([
        'teacher' => ['create_quizzes', 'give_feedback'],
        'parent' => ['access_forum'],
        'student' => ['play_game'],
    ]);
    expect(app(RolePermissions::class)->granted(UserRole::Teacher))->toBe([Permission::CreateQuizzes, Permission::GiveFeedback]);
});

it('changes the permission gates when the matrix is updated', function () {
    $teacher = User::factory()->teacher()->create();
    $permissions = app(RolePermissions::class);
    $allowedBefore = Gate::forUser($teacher)->allows('create_quizzes');

    $permissions->update([...$permissions->matrix(), 'teacher' => ['give_feedback']]);

    expect($allowedBefore)->toBeTrue();
    expect(Gate::forUser($teacher)->allows('create_quizzes'))->toBeFalse();
    expect(Gate::forUser($teacher)->allows('give_feedback'))->toBeTrue();
});

it('drops permissions that do not apply to a role and values that are not permissions', function () {
    app(RolePermissions::class)->update([
        'teacher' => ['create_quizzes', 'view_child_progress', 'play_game', 'not_a_permission', ['nested']],
        'parent' => ['manage_classrooms', 'access_forum'],
        'student' => ['give_feedback'],
    ]);

    expect(app(RolePermissions::class)->matrix())->toBe([
        'teacher' => ['create_quizzes'],
        'parent' => ['access_forum'],
        'student' => [],
    ]);
});

it('grants nothing to a role missing from the updated matrix and ignores unknown and administrator keys', function () {
    app(RolePermissions::class)->update([
        'teacher' => ['create_quizzes'],
        'admin' => ['create_quizzes'],
        'principal' => ['create_quizzes'],
    ]);

    expect(app(Settings::class)->get('role_permissions'))->toBe([
        'teacher' => ['create_quizzes'],
        'parent' => [],
        'student' => [],
    ]);
});

it('falls back to the defaults of a role the stored matrix does not mention', function () {
    app(Settings::class)->set('role_permissions', ['teacher' => ['give_feedback']]);

    expect(app(RolePermissions::class)->granted(UserRole::Teacher))->toBe([Permission::GiveFeedback]);
    expect(app(RolePermissions::class)->granted(UserRole::Parent))->toBe([
        Permission::ViewChildProgress,
        Permission::ViewQuizDetails,
        Permission::ViewTeacherFeedback,
        Permission::AccessForum,
        Permission::PostInForum,
    ]);
});

it('never grants a stored permission that does not apply to the role', function () {
    app(Settings::class)->set('role_permissions', ['parent' => ['manage_classrooms', 'view_quiz_details']]);

    expect(app(RolePermissions::class)->granted(UserRole::Parent))->toBe([Permission::ViewQuizDetails]);
});

it('grants permissions to one role and keeps every other grant', function () {
    $permissions = app(RolePermissions::class);
    $permissions->update(['teacher' => ['give_feedback'], 'parent' => ['access_forum'], 'student' => ['play_game']]);

    $permissions->grant(UserRole::Teacher, Permission::CreateQuizzes, Permission::ViewChildProgress);

    expect($permissions->matrix())->toBe([
        'teacher' => ['create_quizzes', 'give_feedback'],
        'parent' => ['access_forum'],
        'student' => ['play_game'],
    ]);
});

it('revokes permissions from one role and keeps every other grant', function () {
    $permissions = app(RolePermissions::class);

    $permissions->revoke(UserRole::Parent, Permission::AccessForum, Permission::PostInForum);

    expect($permissions->granted(UserRole::Parent))->toBe([
        Permission::ViewChildProgress,
        Permission::ViewQuizDetails,
        Permission::ViewTeacherFeedback,
    ]);
    expect($permissions->granted(UserRole::Teacher))->toContain(Permission::AccessForum, Permission::PostInForum);
});

it('refuses to grant or revoke administrator permissions', function (string $method) {
    app(RolePermissions::class)->{$method}(UserRole::Admin, Permission::CreateQuizzes);
})->with(['grant', 'revoke'])->throws(InvalidArgumentException::class, 'Administrators always hold every permission.');

it('restores the defaults on reset', function () {
    $permissions = app(RolePermissions::class);
    $permissions->update(['teacher' => [], 'parent' => [], 'student' => []]);

    $permissions->reset();

    expect(app(Settings::class)->get('role_permissions'))->toBeNull();
    expect($permissions->granted(UserRole::Teacher))->toContain(Permission::CreateQuizzes);
});

it('allows administrators every permission even when no role holds it', function () {
    $admin = User::factory()->admin()->create();
    app(RolePermissions::class)->update(['teacher' => [], 'parent' => [], 'student' => []]);

    expect(app(RolePermissions::class)->allows($admin, Permission::ViewChildProgress))->toBeTrue();
    expect(app(RolePermissions::class)->allows($admin, Permission::CreateQuizzes))->toBeTrue();
});

it('denies every permission to inactive accounts, administrators included', function (User $user) {
    $allowed = array_filter(
        Permission::cases(),
        fn (Permission $permission): bool => app(RolePermissions::class)->allows($user, $permission),
    );

    expect($allowed)->toBe([]);
})->with([
    'administrator' => fn () => User::factory()->admin()->inactive()->create(),
    'teacher' => fn () => User::factory()->teacher()->inactive()->create(),
    'parent' => fn () => User::factory()->parent()->inactive()->create(),
]);

it('allows a role only the permissions it is granted', function () {
    $parent = User::factory()->parent()->create();

    expect($parent->hasPermission(Permission::ViewChildProgress))->toBeTrue();
    expect($parent->hasPermission(Permission::CreateQuizzes))->toBeFalse();
});

it('builds the permission denied message from the label', function () {
    expect(RolePermissions::deniedMessage(Permission::ManageClassrooms))->toBe('You do not have permission to manage classrooms.')
        ->and(RolePermissions::deniedMessage(Permission::AccessForum))->toBe('You do not have permission to access the parent forum.');
});

it('lists every role except administrators as configurable', function () {
    expect(RolePermissions::configurableRoles())->toBe([UserRole::Teacher, UserRole::Parent, UserRole::Student]);
});
