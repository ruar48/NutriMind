<?php

use App\Services\LevelCalculator;
use App\Services\Settings;

it('starts learners at level 1 and levels them up every 500 points by default', function (int $points, int $level) {
    expect(app(LevelCalculator::class)->levelFor($points))->toBe($level);
})->with([
    'no points' => [0, 1],
    'one point short of level 2' => [499, 1],
    'exactly the level 2 threshold' => [500, 2],
    'one point short of level 3' => [999, 2],
    'exactly the level 3 threshold' => [1000, 3],
    'one point short of the maximum level' => [24499, 49],
]);

it('caps the level at the maximum level', function (int $points) {
    expect(app(LevelCalculator::class)->levelFor($points))->toBe(50);
})->with([
    'exactly the maximum level threshold' => 24500,
    'far beyond the maximum level threshold' => 1000000,
]);

it('treats negative points as zero', function () {
    $calculator = app(LevelCalculator::class);

    expect($calculator->levelFor(-40))->toBe(1);
    expect($calculator->progress(-40))->toBe(['level' => 1, 'into' => 0, 'needed' => 500, 'percent' => 0.0]);
});

it('reports the points earned inside the current level', function () {
    expect(app(LevelCalculator::class)->progress(1240))
        ->toBe(['level' => 3, 'into' => 240, 'needed' => 500, 'percent' => 48.0]);
});

it('rounds the share of the level to one decimal', function () {
    app(Settings::class)->set('points_per_level', 300);

    expect(app(LevelCalculator::class)->progress(100))
        ->toBe(['level' => 1, 'into' => 100, 'needed' => 300, 'percent' => 33.3]);
});

it('reports a full level without a next level at the maximum level', function (int $points) {
    expect(app(LevelCalculator::class)->progress($points))
        ->toBe(['level' => 50, 'into' => 0, 'needed' => 0, 'percent' => 100.0]);
})->with([
    'exactly the maximum level threshold' => 24500,
    'beyond the maximum level threshold' => 30000,
]);

it('applies gamification settings saved after it was resolved', function () {
    $calculator = app(LevelCalculator::class);

    app(Settings::class)->setMany(['points_per_level' => 100, 'max_level' => 10]);

    expect($calculator->pointsPerLevel())->toBe(100);
    expect($calculator->maxLevel())->toBe(10);
    expect($calculator->levelFor(250))->toBe(3);
    expect($calculator->levelFor(5000))->toBe(10);
    expect($calculator->progress(250))->toBe(['level' => 3, 'into' => 50, 'needed' => 100, 'percent' => 50.0]);
});

it('keeps at least one point per level and level 1 as the cap when the settings are invalid', function () {
    app(Settings::class)->setMany(['points_per_level' => 0, 'max_level' => 0]);

    $calculator = app(LevelCalculator::class);

    expect($calculator->pointsPerLevel())->toBe(1);
    expect($calculator->maxLevel())->toBe(1);
    expect($calculator->levelFor(999))->toBe(1);
    expect($calculator->progress(999))->toBe(['level' => 1, 'into' => 0, 'needed' => 0, 'percent' => 100.0]);
});
