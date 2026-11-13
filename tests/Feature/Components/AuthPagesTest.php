<?php

use App\Enums\UserRole;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
    $stubs = [
        'login' => ['get', '/login'],
        'login.portal' => ['get', '/login/{portal}'],
        'login.portal.store' => ['post', '/login/{portal}'],
    ];

    foreach ($stubs as $name => [$method, $uri]) {
        if (! Route::has($name)) {
            Route::{$method}($uri, fn () => '')->name($name);
        }
    }

    Route::getRoutes()->refreshNameLookups();
});

it('offers one large card per web portal', function () {
    $view = $this->view('auth.portals');

    foreach (UserRole::webPortals() as $role) {
        $view->assertSeeText($role->portalLabel())
            ->assertSee('href="'.route('login.portal', $role->value).'"', false)
            ->assertSee('data-portal="'.$role->value.'"', false);
    }

    $view->assertDontSee('data-portal="student"', false)
        ->assertSeeText('Learners play NutriMind in the game app');
});

it('shows the flashed messages on the portal chooser', function () {
    session()->flash('error', 'Your account has been deactivated. Please contact the school administrator.');
    session()->flash('status', 'You have been signed out.');

    $this->view('auth.portals')
        ->assertSeeText('Your account has been deactivated. Please contact the school administrator.')
        ->assertSeeText('You have been signed out.')
        ->assertSee('role="alert"', false);
});

it('posts the sign-in form to the portal it belongs to', function (UserRole $portal, string $accent) {
    $view = $this->view('auth.login', ['portal' => $portal]);

    $view->assertSee('action="'.route('login.portal.store', $portal->value).'"', false)
        ->assertSee('data-portal="'.$accent.'"', false)
        ->assertSeeText($portal->portalLabel())
        ->assertSee('name="email"', false)
        ->assertSee('type="password"', false)
        ->assertSee('name="remember"', false)
        ->assertSee('href="'.route('login').'"', false);
})->with([
    'administrator' => [UserRole::Admin, 'grape'],
    'teacher' => [UserRole::Teacher, 'leaf'],
    'parent' => [UserRole::Parent, 'carrot'],
]);

it('shows the credentials error on the email field', function () {
    $this->withViewErrors(['email' => 'These credentials do not match our records.']);

    $this->view('auth.login', ['portal' => UserRole::Teacher])
        ->assertSeeText('These credentials do not match our records.')
        ->assertSee('aria-describedby="email-error"', false);
});

it('keeps the typed email after a failed attempt', function () {
    $session = app('session')->driver();
    $session->start();
    $session->flashInput(['email' => 'teacher@school.test']);
    request()->setLaravelSession($session);

    $this->view('auth.login', ['portal' => UserRole::Teacher])
        ->assertSee('value="teacher@school.test"', false);
});

it('shows flashed status and error messages above the form', function () {
    session()->flash('status', 'Your password was updated. Please sign in again.');
    session()->flash('error', 'Your account has been deactivated. Please contact the school administrator.');

    $this->view('auth.login', ['portal' => UserRole::Parent])
        ->assertSeeText('Your password was updated. Please sign in again.')
        ->assertSeeText('Your account has been deactivated. Please contact the school administrator.');
});
