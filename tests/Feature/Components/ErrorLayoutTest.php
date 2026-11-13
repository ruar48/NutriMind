<?php

use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Routing\RouteCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

it('renders the 404 page without touching the database', function () {
    $queries = 0;
    DB::listen(function () use (&$queries): void {
        $queries++;
    });

    $response = $this->get('/a-page-that-does-not-exist');

    $response->assertNotFound()
        ->assertSeeText('Error 404')
        ->assertSeeText('We couldn’t find that page')
        ->assertSee('data-illustration="lost-map"', false);
    expect($queries)->toBe(0);
});

it('sends guests back home', function () {
    $this->get('/a-page-that-does-not-exist')
        ->assertNotFound()
        ->assertSeeText('Back home')
        ->assertDontSeeText('Go to dashboard');
});

it('sends a signed-in user to the dashboard when the route exists', function () {
    if (! Route::has('dashboard')) {
        Route::get('/dashboard', fn () => 'dashboard')->name('dashboard');
        Route::getRoutes()->refreshNameLookups();
    }

    $this->actingAs(User::factory()->make());

    $this->get('/a-page-that-does-not-exist')
        ->assertNotFound()
        ->assertSeeText('Go to dashboard')
        ->assertSee('href="'.route('dashboard').'"', false);
});

it('falls back to the home link for a signed-in user when no dashboard route exists', function () {
    app('router')->setRoutes(new RouteCollection);
    $this->actingAs(User::factory()->make());

    $this->get('/anything')
        ->assertNotFound()
        ->assertSeeText('Back home')
        ->assertDontSeeText('Go to dashboard');
});

it('explains a policy denial with its own message', function () {
    Route::get('/_errors/revoked', fn () => abort(403, 'Your access to this subject was removed by the administrator.'));

    $this->get('/_errors/revoked')
        ->assertForbidden()
        ->assertSeeText('This area is locked')
        ->assertSeeText('Your access to this subject was removed by the administrator.');
});

it('shows friendly copy for a generic authorization failure', function () {
    Route::get('/_errors/unauthorized', fn () => throw new AuthorizationException);

    $this->get('/_errors/unauthorized')
        ->assertForbidden()
        ->assertSeeText('You don’t have permission to open this page.')
        ->assertDontSeeText('This action is unauthorized.');
});

it('offers a way back from an expired page', function () {
    Route::get('/_errors/expired', fn () => abort(419));

    $this->get('/_errors/expired')
        ->assertStatus(419)
        ->assertSeeText('Your session timed out')
        ->assertSeeText('Go back');
});

it('tells throttled users how long to wait', function (int $seconds, string $wait) {
    Route::get('/_errors/throttled', fn () => throw new ThrottleRequestsException('Too Many Attempts.', null, ['Retry-After' => $seconds]));

    $this->get('/_errors/throttled')
        ->assertTooManyRequests()
        ->assertSeeText('Please wait '.$wait.' and try again.');
})->with([
    'seconds' => [42, '42 seconds'],
    'minutes' => [150, '3 minutes'],
]);

it('hides exception details on the server error page', function () {
    config(['app.debug' => false]);
    Route::get('/_errors/broken', fn () => throw new RuntimeException('Secret stack detail'));

    $this->get('/_errors/broken')
        ->assertInternalServerError()
        ->assertSeeText('Something spilled on our side')
        ->assertDontSeeText('Secret stack detail');
});

it('renders the maintenance page', function () {
    Route::get('/_errors/maintenance', fn () => abort(503));

    $this->get('/_errors/maintenance')
        ->assertServiceUnavailable()
        ->assertSeeText('We’re tending the garden');
});
