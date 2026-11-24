<?php

use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

it('keeps every game route except sign-in behind a token and the learner role', function () {
    $routes = collect(Route::getRoutes()->getRoutes())
        ->filter(fn (RoutingRoute $route): bool => Str::startsWith((string) $route->getName(), 'game.'))
        ->reject(fn (RoutingRoute $route): bool => $route->getName() === 'game.login');

    expect($routes)->toHaveCount(6);
    $routes->each(fn (RoutingRoute $route) => expect($route->middleware())->toContain('api', 'auth:sanctum', 'role:student'));
});

it('leaves the sign-in open to guests', function () {
    expect(Route::getRoutes()->getByName('game.login')->middleware())->toBe(['api']);
});
