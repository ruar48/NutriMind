<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;

it('changes the password', function () {
    $user = User::factory()->teacher()->create();

    $response = $this->actingAs($user)->put(route('account.password.update'), [
        'current_password' => 'password',
        'password' => 'new-secret-2026',
        'password_confirmation' => 'new-secret-2026',
    ]);

    $response->assertRedirectToRoute('account.edit')
        ->assertSessionHasNoErrors()
        ->assertSessionHas('status', 'Your password was updated.');
    expect(Hash::check('new-secret-2026', $user->refresh()->password))->toBeTrue();
});

it('rejects a wrong current password in the updatePassword error bag', function () {
    $user = User::factory()->parent()->create();

    $response = $this->actingAs($user)->put(route('account.password.update'), [
        'current_password' => 'not-my-password',
        'password' => 'new-secret-2026',
        'password_confirmation' => 'new-secret-2026',
    ]);

    $response->assertSessionHasErrorsIn('updatePassword', ['current_password' => 'The password is incorrect.']);
    $response->assertSessionDoesntHaveErrors(['current_password']);
    expect(Hash::check('password', $user->refresh()->password))->toBeTrue();
});

it('requires the new password to be confirmed', function () {
    $user = User::factory()->admin()->create();

    $this->actingAs($user)
        ->put(route('account.password.update'), [
            'current_password' => 'password',
            'password' => 'new-secret-2026',
            'password_confirmation' => 'something-else-1',
        ])
        ->assertSessionHasErrorsIn('updatePassword', ['password' => 'The new password field confirmation does not match.']);

    expect(Hash::check('password', $user->refresh()->password))->toBeTrue();
});

it('requires both passwords', function () {
    $this->actingAs(User::factory()->teacher()->create())
        ->put(route('account.password.update'), [])
        ->assertSessionHasErrorsIn('updatePassword', [
            'current_password' => 'The current password field is required.',
            'password' => 'The new password field is required.',
        ]);
});

it('applies the default password rules', function (string $password, string $message) {
    $this->actingAs(User::factory()->teacher()->create())
        ->put(route('account.password.update'), [
            'current_password' => 'password',
            'password' => $password,
            'password_confirmation' => $password,
        ])
        ->assertSessionHasErrorsIn('updatePassword', ['password' => $message]);
})->with([
    'too short' => ['abc1234', 'The new password field must be at least 8 characters.'],
    'no letters' => ['12345678', 'The new password field must contain at least one letter.'],
    'no numbers' => ['abcdefgh', 'The new password field must contain at least one number.'],
]);

it('sends guests to the sign-in page', function () {
    $this->put(route('account.password.update'), [])->assertRedirectToRoute('login');
});
