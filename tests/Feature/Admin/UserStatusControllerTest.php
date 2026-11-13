<?php

use App\Enums\UserRole;
use App\Http\Controllers\Admin\UserStatusController;
use App\Models\User;

it('deactivates an account and goes back to the page it came from', function () {
    $teacher = User::factory()->teacher()->create(['name' => 'Ramon Gutierrez']);

    $this->actingAs(User::factory()->admin()->create())
        ->from(route('admin.teachers.index'))
        ->put(route('admin.users.status.update', $teacher), ['is_active' => '0'])
        ->assertRedirect(route('admin.teachers.index'))
        ->assertSessionHas('status', 'Ramon Gutierrez was deactivated and can no longer sign in.');

    expect($teacher->refresh()->is_active)->toBeFalse();
});

it('reactivates an account', function () {
    $parent = User::factory()->parent()->inactive()->create(['name' => 'Rodel Javier']);

    $this->actingAs(User::factory()->admin()->create())
        ->from(route('admin.parents.show', $parent))
        ->put(route('admin.users.status.update', $parent), ['is_active' => '1'])
        ->assertRedirect(route('admin.parents.show', $parent))
        ->assertSessionHas('status', 'Rodel Javier was reactivated and can sign in again.');

    expect($parent->refresh()->is_active)->toBeTrue();
});

it('deactivates learner accounts', function () {
    $learner = User::factory()->student()->create();

    $this->actingAs(User::factory()->admin()->create())
        ->put(route('admin.users.status.update', $learner), ['is_active' => '0'])
        ->assertRedirect(route('admin.students.show', $learner));

    expect($learner->refresh()->is_active)->toBeFalse();
});

it('deactivates another administrator while one stays active', function () {
    $other = User::factory()->admin()->create();

    $this->actingAs(User::factory()->admin()->create())
        ->put(route('admin.users.status.update', $other), ['is_active' => '0'])
        ->assertSessionHasNoErrors()
        ->assertSessionMissing('error');

    expect($other->refresh()->is_active)->toBeFalse();
});

it('signs a deactivated account out on its next request', function () {
    $teacher = User::factory()->teacher()->create();

    $this->actingAs(User::factory()->admin()->create())
        ->put(route('admin.users.status.update', $teacher), ['is_active' => '0']);

    $this->actingAs($teacher->refresh())
        ->get(route('account.edit'))
        ->assertRedirectToRoute('login');
    $this->assertGuest();
});

it('changes nothing when the account already has the status', function (bool $isActive, string $message) {
    $teacher = User::factory()->teacher()->create(['name' => 'Maricel Santos']);
    $teacher->is_active = $isActive;
    $teacher->save();

    $this->actingAs(User::factory()->admin()->create())
        ->put(route('admin.users.status.update', $teacher), ['is_active' => $isActive ? '1' : '0'])
        ->assertSessionHas('status', $message);

    expect($teacher->refresh()->is_active)->toBe($isActive);
})->with([
    'already active' => [true, 'Maricel Santos is already active.'],
    'already deactivated' => [false, 'Maricel Santos is already deactivated.'],
]);

it('refuses to deactivate the signed-in administrator', function () {
    $admin = User::factory()->admin()->create();
    User::factory()->admin()->create();

    $this->actingAs($admin)
        ->from(route('admin.users.index'))
        ->put(route('admin.users.status.update', $admin), ['is_active' => '0'])
        ->assertRedirect(route('admin.users.index'))
        ->assertSessionHas('error', UserStatusController::SELF_DEACTIVATION_MESSAGE);

    expect(UserStatusController::SELF_DEACTIVATION_MESSAGE)->toBe("You can't deactivate your own account.")
        ->and($admin->refresh()->is_active)->toBeTrue();
});

it('refuses to deactivate the last active administrator', function () {
    $admin = User::factory()->admin()->create();
    $lastActive = User::factory()->admin()->create();
    User::query()->whereKey($admin->id)->update(['is_active' => false]);

    $this->actingAs($admin)
        ->from(route('admin.users.index'))
        ->put(route('admin.users.status.update', $lastActive), ['is_active' => '0'])
        ->assertRedirect(route('admin.users.index'))
        ->assertSessionHas('error', 'Keep at least one active administrator. Add or reactivate another administrator first.');

    expect($lastActive->refresh()->is_active)->toBeTrue();
});

it('falls back to the account page without a previous page', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->put(route('admin.users.status.update', $admin), ['is_active' => '0'])
        ->assertRedirect(route('admin.users.edit', $admin));
});

it('requires the wanted status', function (array $payload) {
    $teacher = User::factory()->teacher()->create();

    $this->actingAs(User::factory()->admin()->create())
        ->put(route('admin.users.status.update', $teacher), $payload)
        ->assertSessionHasErrors(['is_active' => 'Choose whether the account should be active.']);

    expect($teacher->refresh()->is_active)->toBeTrue();
})->with([
    'missing' => [[]],
    'not a boolean' => [['is_active' => 'maybe']],
]);

it('reports an unknown account as missing', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->put(route('admin.users.status.update', 999999), ['is_active' => '0'])
        ->assertNotFound();
});

it('sends guests to the sign-in page', function () {
    $teacher = User::factory()->teacher()->create();

    $this->put(route('admin.users.status.update', $teacher), ['is_active' => '0'])->assertRedirectToRoute('login');

    expect($teacher->refresh()->is_active)->toBeTrue();
});

it('forbids other roles', function (UserRole $role) {
    $teacher = User::factory()->teacher()->create();

    $this->actingAs(User::factory()->{$role->value}()->create())
        ->put(route('admin.users.status.update', $teacher), ['is_active' => '0'])
        ->assertForbidden();

    expect($teacher->refresh()->is_active)->toBeTrue();
})->with([
    'teacher' => UserRole::Teacher,
    'parent' => UserRole::Parent,
    'learner' => UserRole::Student,
]);
