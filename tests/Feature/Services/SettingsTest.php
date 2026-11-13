<?php

use App\Services\Settings;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

it('lists the configured defaults when nothing is stored', function () {
    expect(app(Settings::class)->all())->toBe([
        'school_name' => 'NutriMind Elementary School',
        'school_id' => null,
        'school_address' => null,
        'school_head' => null,
        'school_email' => null,
        'school_phone' => null,
        'default_passing_score' => 75,
        'default_max_attempts' => 2,
        'default_learning_mode' => 'automatic',
        'points_per_level' => 500,
        'max_level' => 50,
        'forum_enabled' => true,
        'forum_requires_approval' => false,
        'announcement' => null,
        'role_permissions' => null,
    ]);
});

it('returns the given default for a key that is neither stored nor configured', function () {
    expect(app(Settings::class)->get('unknown_setting', 'fallback'))->toBe('fallback');
});

it('returns a configured null value instead of the given default', function () {
    expect(app(Settings::class)->get('school_id', 'fallback'))->toBeNull();
});

it('overrides a configured default with the stored value', function () {
    $settings = app(Settings::class);

    $settings->set('school_name', 'Rizal Elementary School');

    expect($settings->get('school_name'))->toBe('Rizal Elementary School');
    expect($settings->all()['school_name'])->toBe('Rizal Elementary School');
});

it('keeps the type of a stored value', function (mixed $value) {
    app(Settings::class)->set('announcement', $value);
    app()->forgetScopedInstances();

    expect(app(Settings::class)->get('announcement'))->toBe($value);
})->with([
    'false' => false,
    'true' => true,
    'zero' => 0,
    'integer' => 85,
    'float' => 87.5,
    'string' => 'Quarterly exams start on Monday.',
    'null' => null,
    'list' => [['teacher', 'parent']],
    'map' => [['teacher' => ['create_quizzes', 'give_feedback'], 'parent' => []]],
]);

it('stores values as JSON', function () {
    app(Settings::class)->setMany(['forum_enabled' => false, 'max_level' => 40, 'school_head' => 'Ana Reyes']);

    expect(DB::table('settings')->pluck('value', 'key')->all())->toBe([
        'forum_enabled' => 'false',
        'max_level' => '40',
        'school_head' => '"Ana Reyes"',
    ]);
});

it('stores several values at once', function () {
    $settings = app(Settings::class);

    $settings->setMany(['default_passing_score' => 80, 'forum_requires_approval' => true]);

    expect($settings->get('default_passing_score'))->toBe(80);
    expect($settings->get('forum_requires_approval'))->toBeTrue();
    $this->assertDatabaseCount('settings', 2);
});

it('updates a stored value instead of storing it twice', function () {
    $settings = app(Settings::class);

    $settings->set('max_level', 40);
    $settings->set('max_level', 60);

    expect($settings->get('max_level'))->toBe(60);
    $this->assertDatabaseCount('settings', 1);
});

it('writes nothing when given no values', function () {
    DB::enableQueryLog();

    app(Settings::class)->setMany([]);

    expect(DB::getQueryLog())->toBeEmpty();
});

it('includes stored keys that have no configured default', function () {
    $settings = app(Settings::class);

    $settings->set('welcome_message', 'Mabuhay!');

    expect($settings->all())->toHaveKey('welcome_message', 'Mabuhay!');
});

it('caches the stored values under the settings cache key', function () {
    app(Settings::class)->set('school_name', 'Rizal Elementary School');
    app()->forgetScopedInstances();

    app(Settings::class)->all();

    expect(Cache::get(Settings::CACHE_KEY))->toBe(['school_name' => 'Rizal Elementary School']);
});

it('serves later lifecycles from the cache instead of the database', function () {
    app(Settings::class)->set('school_name', 'Rizal Elementary School');
    app()->forgetScopedInstances();
    app(Settings::class)->all();
    app()->forgetScopedInstances();

    DB::enableQueryLog();
    $schoolName = app(Settings::class)->get('school_name');

    expect($schoolName)->toBe('Rizal Elementary School');
    expect(DB::getQueryLog())->toBeEmpty();
});

it('clears the cache whenever a value is written', function () {
    $settings = app(Settings::class);
    $settings->all();

    $settings->set('forum_enabled', false);

    expect(Cache::has(Settings::CACHE_KEY))->toBeFalse();
});

it('reads the new value in the next lifecycle after a write', function () {
    app(Settings::class)->set('school_name', 'Rizal Elementary School');
    app()->forgetScopedInstances();
    app(Settings::class)->all();
    app()->forgetScopedInstances();

    app(Settings::class)->set('school_name', 'Mabini Elementary School');
    app()->forgetScopedInstances();

    expect(app(Settings::class)->get('school_name'))->toBe('Mabini Elementary School');
});

it('resolves one instance per request lifecycle', function () {
    $first = app(Settings::class);
    $second = app(Settings::class);
    app()->forgetScopedInstances();
    $nextLifecycle = app(Settings::class);

    expect($second)->toBe($first);
    expect($nextLifecycle)->not->toBe($first);
});
