<?php

namespace Database\Seeders\Support;

use InvalidArgumentException;

/**
 * Time helpers for the demo data.
 *
 * Moments are Unix timestamps anchored to the moment seeding started, so every date is relative to "now" while
 * the distributions stay deterministic. Timestamps are formatted in the application timezone, which Laravel sets
 * as PHP's default timezone.
 */
final class DemoClock
{
    /**
     * The number of seconds in a day.
     */
    public const DAY = 86400;

    /**
     * The number of seconds in an hour.
     */
    public const HOUR = 3600;

    /**
     * How likely learners are to play on each ISO weekday (1 = Monday … 7 = Sunday).
     */
    private const PLAY_DAY_WEIGHTS = [1 => 1.0, 2 => 1.0, 3 => 0.95, 4 => 0.95, 5 => 0.8, 6 => 0.45, 7 => 0.35];

    /**
     * Create a clock anchored to the given moment.
     */
    public function __construct(public readonly int $now) {}

    /**
     * Create a clock anchored to the current moment.
     */
    public static function start(): self
    {
        return new self(now()->getTimestamp());
    }

    /**
     * Format a moment as a database datetime.
     */
    public static function format(int $timestamp): string
    {
        return date('Y-m-d H:i:s', $timestamp);
    }

    /**
     * Format a moment as a database date.
     */
    public static function date(int $timestamp): string
    {
        return date('Y-m-d', $timestamp);
    }

    /**
     * Get the moment of a date and time written as "Y-m-d" or "Y-m-d H:i:s".
     */
    public static function at(string $dateTime): int
    {
        $timestamp = strtotime($dateTime);

        if ($timestamp === false) {
            throw new InvalidArgumentException("Invalid demo date [{$dateTime}].");
        }

        return $timestamp;
    }

    /**
     * Get the first second of the day the moment falls on.
     */
    public static function startOfDay(int $timestamp): int
    {
        return self::at(date('Y-m-d', $timestamp));
    }

    /**
     * Get the moment the given number of days before now.
     */
    public function daysAgo(float $days): int
    {
        return $this->now - (int) round($days * self::DAY);
    }

    /**
     * Get the first second of today.
     */
    public function startOfToday(): int
    {
        return self::startOfDay($this->now);
    }

    /**
     * Pick a moment between the bounds when a learner would plausibly play: mostly on school days, during class or
     * in the evening.
     *
     * A positive lean favors the first days of the window (eager learners), a negative lean its last days.
     */
    public static function playTime(DemoRandom $random, int $from, int $until, float $lean = 0.0): int
    {
        if ($until - $from < 2 * self::HOUR) {
            return $random->int($from, max($from, $until));
        }

        $firstDay = self::startOfDay($from);
        $days = intdiv($until - $firstDay, self::DAY) + 1;
        $weights = [];

        for ($day = 0; $day < $days; $day++) {
            $position = $days === 1 ? 0.5 : $day / ($days - 1);
            $weights[$day] = self::PLAY_DAY_WEIGHTS[self::weekday($firstDay + $day * self::DAY)]
                * max(0.05, 1 + $lean * (1 - 2 * $position));
        }

        $dayStart = $firstDay + $random->weighted($weights) * self::DAY;
        $moment = $dayStart + self::playMinute($random, self::weekday($dayStart)) * 60 + $random->int(0, 59);

        return self::within($random, $moment, $from, $until);
    }

    /**
     * Pick a moment on the given day when a teacher would plausibly work: school hours or early evening.
     */
    public static function workTime(DemoRandom $random, int $dayStart): int
    {
        $minute = $random->chance(0.72) ? $random->int(7 * 60, 17 * 60) : $random->int(19 * 60, 21 * 60 + 30);

        return $dayStart + $minute * 60 + $random->int(0, 59);
    }

    /**
     * Get the ISO weekday (1 = Monday … 7 = Sunday) of a moment.
     */
    public static function weekday(int $timestamp): int
    {
        return (int) date('N', $timestamp + 12 * self::HOUR);
    }

    /**
     * Pick the minute of the day a learner plays on a weekday or weekend.
     */
    private static function playMinute(DemoRandom $random, int $weekday): int
    {
        if ($weekday >= 6) {
            return $random->int(9 * 60, 20 * 60);
        }

        return match ($random->weighted(['class' => 0.5, 'afternoon' => 0.15, 'evening' => 0.35])) {
            'class' => $random->int(8 * 60, 15 * 60 + 30),
            'afternoon' => $random->int(15 * 60 + 30, 17 * 60 + 30),
            default => $random->int(18 * 60, 21 * 60),
        };
    }

    /**
     * Move a moment that fell outside the bounds back inside them, without piling moments up on the bounds.
     */
    private static function within(DemoRandom $random, int $moment, int $from, int $until): int
    {
        $slack = min(3 * self::HOUR, intdiv($until - $from, 2));

        return match (true) {
            $moment < $from => $from + $random->int(0, $slack),
            $moment > $until => $until - $random->int(0, $slack),
            default => $moment,
        };
    }
}
