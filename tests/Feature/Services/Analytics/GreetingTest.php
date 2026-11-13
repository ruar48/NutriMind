<?php

use App\Services\Analytics\Greeting;
use Carbon\CarbonImmutable;

it('greets by the time of day', function (string $time, string $greeting) {
    expect(Greeting::for('Teresa Santos', CarbonImmutable::parse('2026-11-13 '.$time)))->toBe($greeting);
})->with([
    'midnight' => ['00:00:00', 'Good morning, Teresa Santos'],
    'just before noon' => ['11:59:59', 'Good morning, Teresa Santos'],
    'noon' => ['12:00:00', 'Good afternoon, Teresa Santos'],
    'just before six' => ['17:59:59', 'Good afternoon, Teresa Santos'],
    'six in the evening' => ['18:00:00', 'Good evening, Teresa Santos'],
]);

it('uses the current time in the app timezone and drops a blank name', function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-13 19:30:00'));

    expect(Greeting::for('  '))->toBe('Good evening');
});
