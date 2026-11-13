<?php

use App\Enums\UserRole;
use App\Http\Controllers\Admin\UserPasswordController;
use App\Models\User;
use App\Services\StudentAccounts;
use Illuminate\Support\Facades\Hash;

it('generates a readable game password for a learner and shows it once', function () {
    $learner = User::factory()->student()->create(['name' => 'Sofia Navarro', 'username' => 'sofia_navarro']);
    $oldHash = $learner->password;

    $this->actingAs(User::factory()->admin()->create())
        ->from(route('admin.students.show', $learner))
        ->put(route('admin.users.password.update', $learner), ['generate' => '1'])
        ->assertRedirect(route('admin.students.show', $learner))
        ->assertSessionHasNoErrors()
        ->assertSessionHas('status', 'A new password was generated for Sofia Navarro.');

    $credentials = session('credentials');
    $learner->refresh();
    expect($credentials)->toHaveCount(1)
        ->and($credentials[0]['name'])->toBe('Sofia Navarro')
        ->and($credentials[0]['username'])->toBe('sofia_navarro')
        ->and($credentials[0]['password'])->toMatch('/^('.implode('|', StudentAccounts::PASSWORD_WORDS).')-\d{4}$/')
        ->and(Hash::check($credentials[0]['password'], $learner->password))->toBeTrue()
        ->and($learner->password)->not->toBe($oldHash);
});

it('generates a strong password for a staff account and shows it with the email address', function () {
    $teacher = User::factory()->teacher()->create(['name' => 'Maricel Santos', 'email' => 'maricel@school.test']);
    $teacher->setRememberToken('old-remember-token');
    $teacher->save();

    $this->actingAs(User::factory()->admin()->create())
        ->put(route('admin.users.password.update', $teacher), ['generate' => '1'])
        ->assertRedirect(route('admin.teachers.show', $teacher))
        ->assertSessionHas('status', 'A new password was generated for Maricel Santos.');

    $credentials = session('credentials');
    $teacher->refresh();
    expect($credentials[0]['username'])->toBe('maricel@school.test')
        ->and($credentials[0]['password'])->toMatch('/^(?=.*[a-zA-Z])(?=.*\d)[a-zA-Z\d]{12}$/')
        ->and(Hash::check($credentials[0]['password'], $teacher->password))->toBeTrue()
        ->and($teacher->getRememberToken())->not->toBe('old-remember-token');
});

it('sets a typed password without showing credentials', function () {
    $parent = User::factory()->parent()->create(['name' => 'Ana Navarro']);
    $parent->setRememberToken('old-remember-token');
    $parent->save();

    $this->actingAs(User::factory()->admin()->create())
        ->from(route('admin.users.edit', $parent))
        ->put(route('admin.users.password.update', $parent), ['password' => 'garden2026', 'password_confirmation' => 'garden2026'])
        ->assertRedirect(route('admin.users.edit', $parent))
        ->assertSessionHasNoErrors()
        ->assertSessionMissing('credentials')
        ->assertSessionHas('status', "Ana Navarro's password was changed.");

    $parent->refresh();
    expect(Hash::check('garden2026', $parent->password))->toBeTrue()
        ->and($parent->getRememberToken())->not->toBe('old-remember-token');
});

it('sets a typed password for a learner', function () {
    $learner = User::factory()->student()->create();

    $this->actingAs(User::factory()->admin()->create())
        ->put(route('admin.users.password.update', $learner), ['password' => 'carrot2026', 'password_confirmation' => 'carrot2026'])
        ->assertSessionHasNoErrors();

    expect(Hash::check('carrot2026', $learner->refresh()->password))->toBeTrue();
});

it('refuses to reset the signed-in administrator’s password', function () {
    $admin = User::factory()->admin()->create(['password' => 'secret2026']);

    $this->actingAs($admin)
        ->from(route('admin.users.edit', $admin))
        ->put(route('admin.users.password.update', $admin), ['generate' => '1'])
        ->assertRedirect(route('admin.users.edit', $admin))
        ->assertSessionHas('error', UserPasswordController::SELF_RESET_MESSAGE)
        ->assertSessionMissing('credentials');

    expect(Hash::check('secret2026', $admin->refresh()->password))->toBeTrue();
});

it('rejects an invalid new password in its own error bag', function (array $payload, string $message) {
    $parent = User::factory()->parent()->create(['password' => 'secret2026']);

    $this->actingAs(User::factory()->admin()->create())
        ->put(route('admin.users.password.update', $parent), $payload)
        ->assertSessionHasErrorsIn('resetPassword', ['password' => $message]);

    expect(Hash::check('secret2026', $parent->refresh()->password))->toBeTrue();
})->with([
    'missing' => [[], 'The new password field is required.'],
    'not generating' => [['generate' => '0'], 'The new password field is required.'],
    'short' => [['password' => 'abc123', 'password_confirmation' => 'abc123'], 'The new password field must be at least 8 characters.'],
    'without letters' => [['password' => '12345678', 'password_confirmation' => '12345678'], 'The new password field must contain at least one letter.'],
    'without digits' => [['password' => 'gardening', 'password_confirmation' => 'gardening'], 'The new password field must contain at least one number.'],
    'unconfirmed' => [['password' => 'garden2026', 'password_confirmation' => 'garden2027'], 'The new password field confirmation does not match.'],
]);

it('rejects a generate flag that is not a boolean', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->put(route('admin.users.password.update', User::factory()->parent()->create()), ['generate' => 'yes please'])
        ->assertSessionHasErrorsIn('resetPassword', ['generate' => 'The generate field must be true or false.']);
});

it('reports an unknown account as missing', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->put(route('admin.users.password.update', 999999), ['generate' => '1'])
        ->assertNotFound();
});

it('sends guests to the sign-in page', function () {
    $this->put(route('admin.users.password.update', User::factory()->parent()->create()), ['generate' => '1'])
        ->assertRedirectToRoute('login');
});

it('forbids other roles', function (UserRole $role) {
    $parent = User::factory()->parent()->create(['password' => 'secret2026']);

    $this->actingAs(User::factory()->{$role->value}()->create())
        ->put(route('admin.users.password.update', $parent), ['generate' => '1'])
        ->assertForbidden();

    expect(Hash::check('secret2026', $parent->refresh()->password))->toBeTrue();
})->with([
    'teacher' => UserRole::Teacher,
    'parent' => UserRole::Parent,
    'learner' => UserRole::Student,
]);
