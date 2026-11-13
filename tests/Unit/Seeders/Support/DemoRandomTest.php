<?php

use Database\Seeders\Support\DemoRandom;

/**
 * Draw a few values of every kind from a stream.
 *
 * @return list<mixed>
 */
function demoRandomSample(DemoRandom $random): array
{
    return [
        $random->int(1, 1000),
        $random->float(),
        $random->between(5.0, 6.0),
        $random->chance(0.5),
        $random->pick(['a', 'b', 'c', 'd']),
        $random->weighted(['x' => 1, 'y' => 2]),
        $random->shuffle([1, 2, 3, 4, 5, 6]),
        $random->normal(10.0, 2.0),
    ];
}

it('draws the same values from a stream on every run', function () {
    expect(demoRandomSample(DemoRandom::stream('quizzes')))->toBe(demoRandomSample(DemoRandom::stream('quizzes')));
});

it('draws different values from differently named streams', function () {
    expect(demoRandomSample(DemoRandom::stream('quizzes')))->not->toBe(demoRandomSample(DemoRandom::stream('forum')));
});

it('is not affected by the global random generator', function () {
    mt_srand(1);
    $first = demoRandomSample(DemoRandom::stream('learners'));

    mt_srand(2);
    $second = demoRandomSample(DemoRandom::stream('learners'));

    mt_srand();

    expect($first)->toBe($second);
});

it('keeps draws within the requested bounds', function () {
    $random = DemoRandom::stream('bounds');

    for ($draw = 0; $draw < 500; $draw++) {
        expect($random->int(3, 7))->toBeGreaterThanOrEqual(3)->toBeLessThanOrEqual(7);
        expect($random->float())->toBeGreaterThanOrEqual(0.0)->toBeLessThan(1.0);
        expect($random->between(-2.0, 2.0))->toBeGreaterThanOrEqual(-2.0)->toBeLessThanOrEqual(2.0);
    }
});

it('picks weighted keys in proportion to their weights and never picks a zero weight', function () {
    $random = DemoRandom::stream('weights');
    $counts = ['rare' => 0, 'common' => 0, 'never' => 0];

    for ($draw = 0; $draw < 4000; $draw++) {
        $counts[$random->weighted(['rare' => 1, 'common' => 3, 'never' => 0])]++;
    }

    expect($counts['never'])->toBe(0);
    expect($counts['common'] / $counts['rare'])->toBeGreaterThan(2.6)->toBeLessThan(3.4);
});

it('shuffles every item exactly once into a list', function () {
    $shuffled = DemoRandom::stream('shuffle')->shuffle(['b' => 2, 'a' => 1, 'c' => 3]);

    expect(array_is_list($shuffled))->toBeTrue();
    expect(collect($shuffled)->sort()->values()->all())->toBe([1, 2, 3]);
});

it('draws normally distributed values around the mean', function () {
    $random = DemoRandom::stream('normal');
    $values = array_map(fn (): float => $random->normal(80.0, 5.0), range(1, 4000));
    $mean = array_sum($values) / count($values);
    $deviation = sqrt(array_sum(array_map(fn (float $value): float => ($value - $mean) ** 2, $values)) / count($values));

    expect($mean)->toBeGreaterThan(79.6)->toBeLessThan(80.4);
    expect($deviation)->toBeGreaterThan(4.7)->toBeLessThan(5.3);
});
