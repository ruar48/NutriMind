<?php

use App\Enums\UserRole;
use App\Models\User;

describe('edit', function () {
    it('shows the account settings of each web role', function (UserRole $role) {
        $user = User::factory()->{$role->value}()->create(['name' => 'Ana Reyes', 'email' => 'ana@school.test']);

        $this->actingAs($user)
            ->get(route('account.edit'))
            ->assertOk()
            ->assertViewIs('account.edit')
            ->assertViewHas('user', fn (User $viewUser): bool => $viewUser->is($user))
            ->assertSee('value="ana@school.test"', false);
    })->with([
        'administrator' => UserRole::Admin,
        'teacher' => UserRole::Teacher,
        'parent' => UserRole::Parent,
    ]);

    it('sends guests to the sign-in page', function () {
        $this->get(route('account.edit'))->assertRedirectToRoute('login');
    });

    it('forbids learner accounts', function () {
        $this->actingAs(User::factory()->student()->create())
            ->get(route('account.edit'))
            ->assertForbidden();
    });
});

describe('update', function () {
    it('updates the profile details', function () {
        $user = User::factory()->teacher()->create(['name' => 'Ana Reyes', 'email' => 'ana@school.test', 'phone' => null]);

        $response = $this->actingAs($user)->put(route('account.update'), [
            'name' => 'Ana Santos-Reyes',
            'email' => 'ana.santos@school.test',
            'phone' => '09171234567',
        ]);

        $response->assertRedirectToRoute('account.edit')
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status', 'Your profile was updated.');
        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'Ana Santos-Reyes',
            'email' => 'ana.santos@school.test',
            'phone' => '09171234567',
        ]);
    });

    it('keeps the current email address', function () {
        $user = User::factory()->parent()->create(['email' => 'lorna@school.test']);

        $this->actingAs($user)
            ->put(route('account.update'), ['name' => 'Lorna Cruz', 'email' => 'lorna@school.test', 'phone' => ''])
            ->assertSessionHasNoErrors();

        expect($user->refresh())->name->toBe('Lorna Cruz')->phone->toBeNull();
    });

    it('rejects an email address another account uses', function () {
        User::factory()->teacher()->create(['email' => 'taken@school.test']);
        $user = User::factory()->teacher()->create(['email' => 'ana@school.test']);

        $this->actingAs($user)
            ->put(route('account.update'), ['name' => 'Ana Reyes', 'email' => 'taken@school.test'])
            ->assertSessionHasErrors(['email' => 'The email address has already been taken.']);

        expect($user->refresh()->email)->toBe('ana@school.test');
    });

    it('requires a name and an email address', function () {
        $this->actingAs(User::factory()->admin()->create())
            ->put(route('account.update'), [])
            ->assertSessionHasErrors([
                'name' => 'The full name field is required.',
                'email' => 'The email address field is required.',
            ]);
    });

    it('rejects invalid profile details', function (array $payload, string $field, string $message) {
        $this->actingAs(User::factory()->teacher()->create())
            ->put(route('account.update'), [...['name' => 'Ana Reyes', 'email' => 'ana@school.test'], ...$payload])
            ->assertSessionHasErrors([$field => $message]);
    })->with([
        'long name' => [['name' => str_repeat('a', 256)], 'name', 'The full name field must not be greater than 255 characters.'],
        'invalid email' => [['email' => 'not-an-email'], 'email', 'The email address field must be a valid email address.'],
        'long email' => [['email' => str_repeat('a', 250).'@school.test'], 'email', 'The email address field must not be greater than 255 characters.'],
        'long phone' => [['phone' => str_repeat('9', 31)], 'phone', 'The mobile number field must not be greater than 30 characters.'],
    ]);

    it('never changes the role or the account status', function () {
        $user = User::factory()->parent()->create();

        $this->actingAs($user)->put(route('account.update'), [
            'name' => 'Lorna Cruz',
            'email' => 'lorna@school.test',
            'role' => 'admin',
            'is_active' => false,
        ]);

        expect($user->refresh())->role->toBe(UserRole::Parent)->is_active->toBeTrue();
    });

    it('sends guests to the sign-in page', function () {
        $this->put(route('account.update'), ['name' => 'Ana Reyes', 'email' => 'ana@school.test'])
            ->assertRedirectToRoute('login');
    });
});
