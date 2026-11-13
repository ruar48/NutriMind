<?php

use App\Models\AcademicTerm;
use App\Models\SchoolYear;
use App\Services\Analytics\PeriodOptions;
use Carbon\CarbonImmutable;

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-13 10:00:00'));
});

/**
 * Create the active 2026–2027 school year with its four quarters, the 2nd one active.
 *
 * @return list<AcademicTerm>
 */
function insightsSchoolYearWithQuarters(): array
{
    $schoolYear = SchoolYear::factory()->active()->create(['name' => '2026–2027', 'starts_on' => '2026-06-16', 'ends_on' => '2027-03-31']);

    return [
        AcademicTerm::factory()->for($schoolYear)->create(['name' => '1st Quarter', 'sequence' => 1, 'starts_on' => '2026-06-16', 'ends_on' => '2026-08-29']),
        AcademicTerm::factory()->for($schoolYear)->active()->create(['name' => '2nd Quarter', 'sequence' => 2, 'starts_on' => '2026-09-01', 'ends_on' => '2026-11-20']),
        AcademicTerm::factory()->for($schoolYear)->create(['name' => '3rd Quarter', 'sequence' => 3, 'starts_on' => '2026-11-23', 'ends_on' => '2027-02-05']),
    ];
}

it('offers the rolling windows and a custom range without an active school year', function () {
    expect(app(PeriodOptions::class)->options())->toBe([
        '7d' => 'Last 7 days',
        '30d' => 'Last 30 days',
        '90d' => 'Last 90 days',
        'custom' => 'Custom range',
    ])->and(app(PeriodOptions::class)->currentTerm())->toBeNull();
});

it('offers the current quarter and the other quarters of the active school year', function () {
    [$first, $second, $third] = insightsSchoolYearWithQuarters();

    expect(app(PeriodOptions::class)->options())->toBe([
        '7d' => 'Last 7 days',
        '30d' => 'Last 30 days',
        '90d' => 'Last 90 days',
        'quarter' => 'Current quarter · 2nd Quarter',
        'term-'.$first->id => '1st Quarter · Jun 16 – Aug 29, 2026',
        'term-'.$third->id => '3rd Quarter · Nov 23, 2026 – Feb 5, 2027',
        'custom' => 'Custom range',
    ])->and(app(PeriodOptions::class)->currentTerm()?->is($second))->toBeTrue();
});

it('resolves each choice to its period', function () {
    [$first] = insightsSchoolYearWithQuarters();
    $periods = app(PeriodOptions::class);

    expect($periods->resolve(null)->key)->toBe('30d')
        ->and($periods->resolve('7d')->from->toDateString())->toBe('2026-11-07')
        ->and($periods->resolve('90d')->from->toDateString())->toBe('2026-08-16')
        ->and([$periods->resolve('quarter')->from->toDateString(), $periods->resolve('quarter')->to->toDateString(), $periods->resolve('quarter')->label])
        ->toBe(['2026-09-01', '2026-11-20', '2nd Quarter'])
        ->and([$periods->resolve('term-'.$first->id)->from->toDateString(), $periods->resolve('term-'.$first->id)->label])
        ->toBe(['2026-06-16', '1st Quarter'])
        ->and($periods->resolve('term-999999')->key)->toBe('30d');
});

it('fills a custom range that has no dates with the last 30 days', function () {
    $periods = app(PeriodOptions::class);

    $empty = $periods->resolve('custom');
    $startOnly = $periods->resolve('custom', '2026-10-01');
    $endOnly = $periods->resolve('custom', null, '2026-10-31');

    expect([$empty->from->toDateString(), $empty->to->toDateString(), $empty->key])->toBe(['2026-10-15', '2026-11-13', 'custom'])
        ->and([$startOnly->from->toDateString(), $startOnly->to->toDateString()])->toBe(['2026-10-01', '2026-11-13'])
        ->and([$endOnly->from->toDateString(), $endOnly->to->toDateString()])->toBe(['2026-10-02', '2026-10-31']);
});
