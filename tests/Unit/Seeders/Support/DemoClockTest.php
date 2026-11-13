<?php

use Database\Seeders\Support\DemoClock;
use Database\Seeders\Support\DemoRandom;

it('picks play times within the bounds', function (string $from, string $until) {
    $random = DemoRandom::stream('play-time-bounds');
    $start = DemoClock::at($from);
    $end = DemoClock::at($until);

    for ($draw = 0; $draw < 300; $draw++) {
        expect(DemoClock::playTime($random, $start, $end, $random->between(-0.8, 0.8)))
            ->toBeGreaterThanOrEqual($start)
            ->toBeLessThanOrEqual($end);
    }
})->with([
    'a school week' => ['2026-11-02 07:00:00', '2026-11-08 23:59:00'],
    'one evening' => ['2026-11-12 20:00:00', '2026-11-12 23:59:00'],
    'a retake window starting at midnight' => ['2026-11-11 00:30:00', '2026-11-14 23:59:00'],
    'less than two hours' => ['2026-11-12 10:00:00', '2026-11-12 11:30:00'],
]);

it('picks play times between eight in the morning and nine in the evening when the window spans days', function () {
    $random = DemoRandom::stream('play-time-hours');
    $start = DemoClock::at('2026-10-01 00:00:00');
    $end = DemoClock::at('2026-10-21 23:59:59');

    for ($draw = 0; $draw < 500; $draw++) {
        expect((int) date('G', DemoClock::playTime($random, $start, $end)))->toBeGreaterThanOrEqual(8)->toBeLessThanOrEqual(21);
    }
});

it('favors school days over weekends', function () {
    $random = DemoRandom::stream('play-time-weekdays');
    $start = DemoClock::at('2026-09-07 00:00:00');
    $end = DemoClock::at('2026-10-04 23:59:59');
    $weekend = 0;

    for ($draw = 0; $draw < 2000; $draw++) {
        $weekend += DemoClock::weekday(DemoClock::playTime($random, $start, $end)) >= 6 ? 1 : 0;
    }

    expect($weekend / 2000)->toBeGreaterThan(0.08)->toBeLessThan(0.2);
});

it('favors the first days of the window for eager learners and the last days for late ones', function () {
    $start = DemoClock::at('2026-10-05 00:00:00');
    $end = DemoClock::at('2026-10-18 23:59:59');
    $middle = intdiv($start + $end, 2);
    $eager = DemoRandom::stream('play-time-eager');
    $late = DemoRandom::stream('play-time-late');
    $eagerEarly = 0;
    $lateEarly = 0;

    for ($draw = 0; $draw < 1000; $draw++) {
        $eagerEarly += DemoClock::playTime($eager, $start, $end, 0.8) < $middle ? 1 : 0;
        $lateEarly += DemoClock::playTime($late, $start, $end, -0.8) < $middle ? 1 : 0;
    }

    expect($eagerEarly)->toBeGreaterThan(600);
    expect($lateEarly)->toBeLessThan(400);
});

it('picks teachers\' work times during school hours or the early evening of the given day', function () {
    $random = DemoRandom::stream('work-time');
    $day = DemoClock::at('2026-11-10');

    for ($draw = 0; $draw < 300; $draw++) {
        $moment = DemoClock::workTime($random, $day);
        $minute = (int) date('G', $moment) * 60 + (int) date('i', $moment);

        expect(DemoClock::date($moment))->toBe('2026-11-10');
        expect(($minute >= 7 * 60 && $minute <= 17 * 60) || ($minute >= 19 * 60 && $minute <= 21 * 60 + 30))->toBeTrue();
    }
});

it('formats moments as database dates and datetimes', function () {
    $moment = DemoClock::at('2026-11-13 09:05:07');

    expect(DemoClock::format($moment))->toBe('2026-11-13 09:05:07');
    expect(DemoClock::date($moment))->toBe('2026-11-13');
    expect(DemoClock::format(DemoClock::startOfDay($moment)))->toBe('2026-11-13 00:00:00');
    expect(DemoClock::weekday($moment))->toBe(5);
});

it('counts days back from its anchor', function () {
    $clock = new DemoClock(DemoClock::at('2026-11-13 10:00:00'));

    expect(DemoClock::format($clock->daysAgo(1.5)))->toBe('2026-11-11 22:00:00');
    expect(DemoClock::format($clock->startOfToday()))->toBe('2026-11-13 00:00:00');
});

it('rejects an invalid date', function () {
    DemoClock::at('not a date');
})->throws(InvalidArgumentException::class, 'Invalid demo date [not a date].');
