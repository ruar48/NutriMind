<?php

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Support\Facades\Route;

/**
 * Pages of each portal area and the roles the area admits.
 *
 * @return array<string, array{0: string, 1: list<UserRole>}>
 */
function roleMiddlewareAreas(): array
{
    return [
        'administrator dashboard' => ['/admin', [UserRole::Admin]],
        'administrator users' => ['/admin/users', [UserRole::Admin]],
        'teacher dashboard' => ['/teacher', [UserRole::Teacher]],
        'teacher quizzes' => ['/teacher/quizzes', [UserRole::Teacher]],
        'parent dashboard' => ['/parent', [UserRole::Parent]],
        'parent children' => ['/parent/children', [UserRole::Parent]],
        'forum' => ['/forum', [UserRole::Admin, UserRole::Teacher, UserRole::Parent]],
        'account settings' => ['/account', [UserRole::Admin, UserRole::Teacher, UserRole::Parent]],
    ];
}

dataset('roles outside each area', function (): array {
    $cases = [];

    foreach (roleMiddlewareAreas() as $area => [$path, $allowedRoles]) {
        foreach (UserRole::cases() as $role) {
            if (! in_array($role, $allowedRoles, true)) {
                $cases[$area.' as '.$role->value] = [$path, $role];
            }
        }
    }

    return $cases;
});

dataset('roles the middleware admits', [
    'admin only, administrator' => ['admin', UserRole::Admin],
    'teacher only, teacher' => ['teacher', UserRole::Teacher],
    'parent only, parent' => ['parent', UserRole::Parent],
    'web roles, administrator' => ['admin,teacher,parent', UserRole::Admin],
    'web roles, teacher' => ['admin,teacher,parent', UserRole::Teacher],
    'web roles, parent' => ['admin,teacher,parent', UserRole::Parent],
]);

dataset('roles the middleware refuses', [
    'admin only, teacher' => ['admin', UserRole::Teacher],
    'admin only, parent' => ['admin', UserRole::Parent],
    'admin only, learner' => ['admin', UserRole::Student],
    'teacher only, administrator' => ['teacher', UserRole::Admin],
    'parent only, teacher' => ['parent', UserRole::Teacher],
    'web roles, learner' => ['admin,teacher,parent', UserRole::Student],
]);

it('forbids every role outside the area of a portal', function (string $path, UserRole $role) {
    $this->actingAs(User::factory()->{$role->value}()->create());

    $this->get($path)
        ->assertForbidden()
        ->assertSeeText('Your account does not have access to this page.');
})->with('roles outside each area');

it('lets the listed roles through', function (string $roles, UserRole $role) {
    Route::middleware(['web', 'auth', 'role:'.$roles])->get('/_test/role-check', fn () => 'role check passed');
    $this->actingAs(User::factory()->{$role->value}()->create());

    $this->get('/_test/role-check')
        ->assertOk()
        ->assertSeeText('role check passed');
})->with('roles the middleware admits');

it('forbids the roles that are not listed', function (string $roles, UserRole $role) {
    Route::middleware(['web', 'auth', 'role:'.$roles])->get('/_test/role-check', fn () => 'role check passed');
    $this->actingAs(User::factory()->{$role->value}()->create());

    $this->get('/_test/role-check')
        ->assertForbidden()
        ->assertDontSeeText('role check passed');
})->with('roles the middleware refuses');

it('sends guests to the sign-in page and remembers the page they asked for', function (string $path) {
    $this->get($path)->assertRedirectToRoute('login');

    $this->assertGuest();
    expect(session('url.intended'))->toBe(url($path));
})->with(fn (): array => array_map(fn (array $area): string => $area[0], roleMiddlewareAreas()));

it('sends guests to the sign-in page from a role route without the auth middleware', function () {
    Route::middleware(['web', 'role:admin'])->get('/_test/role-without-auth', fn () => 'role check passed');

    $this->get('/_test/role-without-auth')->assertRedirectToRoute('login');
});

it('checks the role before resolving route models, so other portals cannot probe record IDs', function (UserRole $role, string $path) {
    $this->actingAs(User::factory()->{$role->value}()->create());

    $this->get($path)->assertForbidden();
})->with([
    'teacher on a missing learner of the administrator area' => [UserRole::Teacher, '/admin/students/999999'],
    'parent on a missing classroom of the teacher area' => [UserRole::Parent, '/teacher/classrooms/999999'],
    'administrator on a missing child of the parent area' => [UserRole::Admin, '/parent/children/999999'],
]);

it('signs out a user whose account was deactivated during the session', function () {
    $teacher = User::factory()->teacher()->create();
    $this->actingAs($teacher)->withSession(['draft_message' => 'Unsaved feedback']);
    $tokenBefore = session()->token();
    $teacher->is_active = false;
    $teacher->save();

    $response = $this->get(route('account.edit'));

    $response->assertRedirectToRoute('login')
        ->assertSessionHas('error', 'Your account has been deactivated. Please contact the school administrator.')
        ->assertSessionMissing('draft_message');
    $this->assertGuest();
    expect(session()->token())->not->toBe($tokenBefore);
});

it('signs out a deactivated user before checking the role of the area', function (string $path) {
    $parent = User::factory()->parent()->inactive()->create();

    $this->actingAs($parent)
        ->get($path)
        ->assertRedirectToRoute('login')
        ->assertSessionHas('error', 'Your account has been deactivated. Please contact the school administrator.');

    $this->assertGuest();
})->with(['/admin', '/teacher/quizzes', '/forum', '/account']);
