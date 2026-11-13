<?php

namespace App\Services\Analytics;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Carbon\WeekDay;

/**
 * A closed range of whole days (app timezone) that analytics figures are measured over.
 *
 * Every period knows how far back its comparison period lies: rolling windows, quarters and custom ranges compare
 * with the same number of days right before them, a week-to-date compares with the same weekdays of last week.
 */
final readonly class AnalyticsPeriod
{
    /**
     * @param  CarbonImmutable  $from  The first moment of the period (start of its first day).
     * @param  CarbonImmutable  $to  The last moment of the period (end of its last day).
     * @param  string  $key  The filter value that selects the period ("30d", "quarter", "custom"…).
     * @param  string  $label  The human-readable name ("Last 30 days", "2nd Quarter"…).
     * @param  int  $comparisonShiftDays  How many days earlier the comparison period starts and ends.
     */
    private function __construct(
        public CarbonImmutable $from,
        public CarbonImmutable $to,
        public string $key,
        public string $label,
        private int $comparisonShiftDays,
    ) {}

    /**
     * Today and the days before it, e.g. the last 30 days.
     */
    public static function lastDays(int $days, ?string $key = null): self
    {
        $days = max(1, $days);
        $today = now()->toImmutable();

        return new self(
            $today->subDays($days - 1)->startOfDay(),
            $today->endOfDay(),
            $key ?? $days.'d',
            $days === 1 ? 'Today' : 'Last '.$days.' days',
            $days,
        );
    }

    /**
     * The current ISO week (Monday start) up to today, compared with the same weekdays of last week.
     */
    public static function thisWeek(): self
    {
        $today = now()->toImmutable();

        return new self($today->startOfWeek(WeekDay::Monday), $today->endOfDay(), 'week', 'This week', 7);
    }

    /**
     * The current ISO week and the full weeks before it, e.g. the last 8 weeks.
     */
    public static function lastWeeks(int $weeks): self
    {
        $weeks = max(1, $weeks);
        $today = now()->toImmutable();

        return new self(
            $today->startOfWeek(WeekDay::Monday)->subWeeks($weeks - 1),
            $today->endOfDay(),
            $weeks.'w',
            'Last '.$weeks.' weeks',
            $weeks * 7,
        );
    }

    /**
     * The given days, e.g. a custom date range or an academic quarter (dates in either order).
     */
    public static function between(CarbonInterface $from, CarbonInterface $to, string $key, ?string $label = null): self
    {
        $start = $from->toImmutable()->startOfDay();
        $end = $to->toImmutable()->endOfDay();

        if ($end->lessThan($start)) {
            [$start, $end] = [$end->startOfDay(), $start->endOfDay()];
        }

        return new self($start, $end, $key, $label ?? self::formatRange($start, $end), self::countDays($start, $end));
    }

    /**
     * Get the period the figures are compared with (deltas).
     */
    public function previous(): self
    {
        return new self(
            $this->from->subDays($this->comparisonShiftDays),
            $this->to->subDays($this->comparisonShiftDays),
            $this->key.'-previous',
            'Before '.lcfirst($this->label),
            $this->comparisonShiftDays,
        );
    }

    /**
     * Get the number of calendar days the period covers.
     */
    public function days(): int
    {
        return self::countDays($this->from, $this->to);
    }

    /**
     * Get the latest moment of the period that has already happened: now while it runs, its end once it is over,
     * its start while it lies in the future.
     */
    public function elapsedEnd(): CarbonImmutable
    {
        $now = now()->toImmutable();

        return match (true) {
            $now->lessThan($this->from) => $this->from,
            $now->lessThan($this->to) => $now,
            default => $this->to,
        };
    }

    /**
     * Get the dates as text, e.g. "Oct 15 – Nov 13, 2026" or "Dec 1, 2025 – Jan 15, 2026".
     */
    public function rangeText(): string
    {
        return self::formatRange($this->from, $this->to);
    }

    /**
     * Get the wording that goes with deltas, e.g. "vs previous 30 days" or "vs same days last week".
     */
    public function comparisonLabel(): string
    {
        if ($this->key === 'week') {
            return 'vs same days last week';
        }

        if (preg_match('/^\d+w$/', $this->key) === 1) {
            return 'vs previous '.intdiv($this->comparisonShiftDays, 7).' weeks';
        }

        return $this->comparisonShiftDays === 1 ? 'vs the day before' : 'vs previous '.$this->comparisonShiftDays.' days';
    }

    /**
     * Get a string that identifies the period's dates (used to memoize figures).
     */
    public function cacheKey(): string
    {
        return $this->from->toDateTimeString().'|'.$this->to->toDateTimeString();
    }

    /**
     * Count the calendar days from the start's day to the end's day, both included.
     */
    private static function countDays(CarbonImmutable $from, CarbonImmutable $to): int
    {
        return (int) round($from->startOfDay()->diffInDays($to->startOfDay())) + 1;
    }

    /**
     * Format two moments as a date range.
     */
    private static function formatRange(CarbonImmutable $from, CarbonImmutable $to): string
    {
        if ($from->isSameDay($to)) {
            return $from->format('M j, Y');
        }

        return $from->isSameYear($to)
            ? $from->format('M j').' – '.$to->format('M j, Y')
            : $from->format('M j, Y').' – '.$to->format('M j, Y');
    }
}
