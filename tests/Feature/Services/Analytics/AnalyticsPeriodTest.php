<?php

use App\Services\Analytics\AnalyticsPeriod;
use Carbon\CarbonImmutable;

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-13 10:00:00'));
});

it('covers today and the days before it, compared with the same number of days before', function () {
    $period = AnalyticsPeriod::lastDays(30);
    $previous = $period->previous();

    expect([$period->from->toDateTimeString(), $period->to->toDateTimeString(), $period->key, $period->label, $period->days()])
        ->toBe(['2026-10-15 00:00:00', '2026-11-13 23:59:59', '30d', 'Last 30 days', 30])
        ->and([$previous->from->toDateTimeString(), $previous->to->toDateTimeString(), $previous->days()])
        ->toBe(['2026-09-15 00:00:00', '2026-10-14 23:59:59', 30])
        ->and($period->comparisonLabel())->toBe('vs previous 30 days')
        ->and($period->rangeText())->toBe('Oct 15 – Nov 13, 2026');
});

it('compares this week so far with the same weekdays of last week', function () {
    $period = AnalyticsPeriod::thisWeek();
    $previous = $period->previous();

    expect([$period->from->toDateTimeString(), $period->to->toDateTimeString()])->toBe(['2026-11-09 00:00:00', '2026-11-13 23:59:59'])
        ->and([$previous->from->toDateTimeString(), $previous->to->toDateTimeString()])->toBe(['2026-11-02 00:00:00', '2026-11-06 23:59:59'])
        ->and($period->comparisonLabel())->toBe('vs same days last week');
});

it('starts a run of weeks on the Monday of the first week', function () {
    $period = AnalyticsPeriod::lastWeeks(8);

    expect($period->from->toDateTimeString())->toBe('2026-09-21 00:00:00')
        ->and($period->previous()->from->toDateTimeString())->toBe('2026-07-27 00:00:00')
        ->and($period->comparisonLabel())->toBe('vs previous 8 weeks');
});

it('builds a range from dates in either order and compares it with the days right before', function () {
    $period = AnalyticsPeriod::between(CarbonImmutable::parse('2026-11-10'), CarbonImmutable::parse('2026-11-01'), 'custom');

    expect([$period->from->toDateTimeString(), $period->to->toDateTimeString(), $period->days(), $period->label])
        ->toBe(['2026-11-01 00:00:00', '2026-11-10 23:59:59', 10, 'Nov 1 – Nov 10, 2026'])
        ->and($period->previous()->from->toDateTimeString())->toBe('2026-10-22 00:00:00')
        ->and($period->previous()->to->toDateTimeString())->toBe('2026-10-31 23:59:59');
});

it('writes ranges across years and single days in full', function () {
    expect(AnalyticsPeriod::between(CarbonImmutable::parse('2025-12-20'), CarbonImmutable::parse('2026-01-05'), 'custom')->rangeText())
        ->toBe('Dec 20, 2025 – Jan 5, 2026')
        ->and(AnalyticsPeriod::between(CarbonImmutable::parse('2026-11-13'), CarbonImmutable::parse('2026-11-13'), 'custom')->rangeText())
        ->toBe('Nov 13, 2026');
});

it('knows how much of the period has already happened', function () {
    expect(AnalyticsPeriod::lastDays(7)->elapsedEnd()->toDateTimeString())->toBe('2026-11-13 10:00:00')
        ->and(AnalyticsPeriod::between(CarbonImmutable::parse('2026-10-01'), CarbonImmutable::parse('2026-10-31'), 'custom')->elapsedEnd()->toDateTimeString())
        ->toBe('2026-10-31 23:59:59')
        ->and(AnalyticsPeriod::between(CarbonImmutable::parse('2026-12-01'), CarbonImmutable::parse('2026-12-31'), 'custom')->elapsedEnd()->toDateTimeString())
        ->toBe('2026-12-01 00:00:00');
});
