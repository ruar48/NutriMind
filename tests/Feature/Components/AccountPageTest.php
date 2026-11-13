<?php

use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
    $stubs = [
        'dashboard' => ['get', '/dashboard'],
        'account.update' => ['put', '/account'],
        'account.password.update' => ['put', '/account/password'],
    ];

    foreach ($stubs as $name => [$method, $uri]) {
        if (! Route::has($name)) {
            Route::{$method}($uri, fn () => '')->name($name);
        }
    }

    Route::getRoutes()->refreshNameLookups();
});

it('prefills the profile form and spoofs PUT for both forms', function () {
    $user = User::factory()->teacher()->make(['name' => 'Jose Dizon', 'email' => 'jose@school.test', 'phone' => '09171234567']);
    $this->actingAs($user);

    $html = (string) $this->view('account.edit', ['user' => $user]);

    expect($html)->toContain('action="'.route('account.update').'"')
        ->toContain('action="'.route('account.password.update').'"')
        ->and(substr_count($html, 'name="_method" value="PUT"'))->toBe(2)
        ->and($html)->toContain('value="Jose Dizon"')
        ->toContain('value="jose@school.test"')
        ->toContain('value="09171234567"')
        ->toContain('name="current_password"')
        ->toContain('name="password_confirmation"');
});

it('shows password errors from the updatePassword bag only on the password form', function () {
    $user = User::factory()->make();
    $this->actingAs($user);
    $this->withViewErrors(['current_password' => 'The password is incorrect.', 'password' => 'The password field confirmation does not match.'], 'updatePassword');

    $html = (string) $this->view('account.edit', ['user' => $user]);

    expect($html)->toContain('The password is incorrect.')
        ->toContain('The password field confirmation does not match.')
        ->toContain('id="current_password-error"')
        ->not->toContain('id="name-error"');
});

it('shows profile errors from the default bag', function () {
    $user = User::factory()->make();
    $this->actingAs($user);
    $this->withViewErrors(['email' => 'The email has already been taken.']);

    $this->view('account.edit', ['user' => $user])
        ->assertSeeText('The email has already been taken.')
        ->assertSee('id="email-error"', false)
        ->assertDontSee('id="current_password-error"', false);
});

it('shows the role and the last sign-in', function () {
    $this->travelTo(Carbon::parse('2026-11-13 10:00:00'));
    $user = User::factory()->parent()->make(['last_login_at' => Carbon::parse('2026-11-11 10:00:00')]);
    $this->actingAs($user);

    $this->view('account.edit', ['user' => $user])
        ->assertSeeText('Parent / Guardian')
        ->assertSeeText('Last signed in 2 days ago');
});
