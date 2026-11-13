<?php

use App\Models\AcademicTerm;
use App\Models\SchoolYear;
use App\Models\User;
use Illuminate\Routing\RouteCollection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\View;

/**
 * Register the named routes the layout links to when the platform routes are not loaded.
 */
function registerAppLayoutRoutes(): void
{
    $stubs = [
        'dashboard' => ['get', '/dashboard'],
        'account.edit' => ['get', '/account'],
        'logout' => ['post', '/logout'],
    ];

    foreach ($stubs as $name => [$method, $uri]) {
        if (! Route::has($name)) {
            Route::{$method}($uri, fn () => '')->name($name);
        }
    }

    Route::getRoutes()->refreshNameLookups();
}

it('renders with safe fallbacks when no layout data is shared', function () {
    app('router')->setRoutes(new RouteCollection);
    $this->actingAs(User::factory()->teacher()->make(['name' => 'Maria Santos']));

    $html = (string) $this->blade('<x-layouts.app title="Dashboard"><p>Page body</p></x-layouts.app>');

    expect($html)->toContain('<title>Dashboard · Teacher Portal · NutriMind</title>')
        ->toContain('Teacher Portal')
        ->toContain(config('nutrimind.settings.school_name'))
        ->toContain('<main id="content"')
        ->toContain('<p>Page body</p>')
        ->toContain('href="#content"')
        ->toContain('Maria Santos')
        ->not->toContain('role="search"')
        ->not->toContain('data-announcement=')
        ->not->toContain('Account settings')
        ->not->toContain('Sign out');
});

it('renders the navigation, portal, search, school year and announcement it is given', function () {
    $schoolYear = SchoolYear::factory()->make(['name' => '2026–2027']);
    $term = AcademicTerm::factory()->make(['name' => '2nd Quarter']);
    View::composer('components.layouts.app', fn ($view) => $view->with([
        'navigation' => [
            ['label' => 'Overview', 'items' => [
                ['label' => 'Dashboard', 'url' => '/teacher', 'icon' => 'layout-dashboard', 'active' => true, 'badge' => null],
                ['label' => 'Learning Analytics', 'url' => '/teacher/analytics', 'icon' => 'chart-line', 'active' => false, 'badge' => 3],
            ]],
            ['label' => 'Empty section', 'items' => []],
        ],
        'portal' => ['key' => 'admin', 'label' => 'Administrator Portal', 'color' => 'grape'],
        'schoolName' => 'Sampaguita Elementary School',
        'activeSchoolYear' => $schoolYear,
        'activeTerm' => $term,
        'announcement' => 'Quarterly assessments start on Monday.',
        'searchUrl' => '/admin/users',
        'searchPlaceholder' => 'Search people',
    ]));
    $this->actingAs(User::factory()->admin()->make());

    $html = (string) $this->blade('<x-layouts.app title="Dashboard">Body</x-layouts.app>');

    expect($html)->toMatch('/<a\s+href="\/teacher"\s+aria-current="page"/')
        ->not->toMatch('/href="\/teacher\/analytics"\s+aria-current/')
        ->toMatch('/tabular-nums">3<\/span>/')
        ->not->toContain('Empty section')
        ->toContain('Administrator Portal')
        ->toContain('bg-grape-400/20 text-grape-100')
        ->toContain('Sampaguita Elementary School')
        ->toContain('action="/admin/users"')
        ->toContain('placeholder="Search people"')
        ->toContain('SY 2026–2027')
        ->toContain('2nd Quarter')
        ->toContain('Quarterly assessments start on Monday.')
        ->toContain('data-announcement-id="'.substr(hash('sha256', 'Quarterly assessments start on Monday.'), 0, 16).'"');
});

it('links the account settings and sign-out actions when the routes exist', function () {
    registerAppLayoutRoutes();
    $this->actingAs(User::factory()->parent()->make(['email' => 'lorna@example.test']));

    $html = (string) $this->blade('<x-layouts.app>Body</x-layouts.app>');

    expect($html)->toContain('href="'.route('account.edit').'"')
        ->toContain('Account settings')
        ->toMatch('/<form method="POST" action="'.preg_quote(route('logout'), '/').'">/')
        ->toContain('Sign out')
        ->toContain('lorna@example.test')
        ->toContain('Parent / Guardian');
});

it('shows flashed status and error messages as toasts', function () {
    $this->actingAs(User::factory()->make());
    session()->flash('status', 'Classroom created.');
    session()->flash('error', 'The quiz could not be published.');

    $html = (string) $this->blade('<x-layouts.app>Body</x-layouts.app>');

    expect($html)->toMatch('/data-toast\s+role="status"/')
        ->toContain('Classroom created.')
        ->toMatch('/data-toast\s+role="alert"/')
        ->toContain('The quiz could not be published.');
});

it('escapes the announcement text', function () {
    View::composer('components.layouts.app', fn ($view) => $view->with('announcement', '<img src=x onerror=alert(1)>'));
    $this->actingAs(User::factory()->make());

    $html = (string) $this->blade('<x-layouts.app>Body</x-layouts.app>');

    expect($html)->not->toContain('<img src=x onerror=alert(1)>')
        ->toContain('&lt;img src=x onerror=alert(1)&gt;');
});

it('puts the guest layout in the accent of the portal', function () {
    $html = (string) $this->blade('<x-layouts.guest title="Sign in" portal="carrot">Form</x-layouts.guest>');

    expect($html)->toContain('data-portal="carrot"')
        ->toContain('bg-carrot-900')
        ->toContain('<title>Sign in · NutriMind</title>')
        ->toContain(config('nutrimind.settings.school_name'))
        ->toContain('Form');
});

it('prints a report with the school header block and stays in light mode', function () {
    $html = (string) $this->blade(
        '<x-layouts.print title="Student performance" school-name="Sampaguita ES" school-address="Quezon City" school-id="136512" :generated-at="$at" :meta="[\'Quarter\' => \'1st Quarter\']" back-url="/teacher/reports">Rows</x-layouts.print>',
        ['at' => Carbon::parse('2026-11-13 14:05:00')],
    );

    expect($html)->toContain('data-theme-locked="light"')
        ->toContain('Sampaguita ES')
        ->toContain('Quezon City')
        ->toContain('School ID: 136512')
        ->toContain('Nov 13, 2026 2:05 PM')
        ->toContain('1st Quarter')
        ->toContain('data-print')
        ->toContain('href="/teacher/reports"')
        ->not->toContain("localStorage.getItem('nutrimind.theme')");
});
