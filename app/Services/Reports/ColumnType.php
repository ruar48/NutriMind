<?php

namespace App\Services\Reports;

use App\Models\Subject;
use BackedEnum;
use Carbon\CarbonInterface;

/**
 * How a report column's raw value is shown on screen, printed and written to CSV.
 *
 * Cell values by type: Text ?string · Number ?int · Score / Rate ?float (0–100) · Band PerformanceBand ·
 * Badge ?enum with label() and color() · Subject ?Subject · Level ?int · Date / DateTime ?CarbonInterface ·
 * DateRange ?array{0: CarbonInterface, 1: CarbonInterface} · List list<string> · Status bool (account is active).
 */
enum ColumnType: string
{
    case Text = 'text';
    case Number = 'number';
    case Score = 'score';
    case Rate = 'rate';
    case Band = 'band';
    case Badge = 'badge';
    case Subject = 'subject';
    case Level = 'level';
    case Date = 'date';
    case DateTime = 'datetime';
    case DateRange = 'date-range';
    case List = 'list';
    case Status = 'status';

    /**
     * Determine whether the column holds numbers that line up in a table column.
     */
    public function isNumeric(): bool
    {
        return in_array($this, [self::Number, self::Score, self::Rate, self::Level], true);
    }

    /**
     * Get the horizontal alignment of the column's cells.
     */
    public function align(): string
    {
        return $this->isNumeric() ? 'right' : 'left';
    }

    /**
     * Format a cell value as plain text (print view and accessible fallbacks).
     */
    public function display(mixed $value, string $empty = '—'): string
    {
        $text = match ($this) {
            self::Text => is_string($value) && trim($value) !== '' ? $value : null,
            self::Number, self::Level => is_int($value) || is_float($value) ? number_format($value) : null,
            self::Score, self::Rate => is_int($value) || is_float($value) ? self::percent((float) $value) : null,
            self::Band, self::Badge => $value instanceof BackedEnum && method_exists($value, 'label') ? $value->label() : null,
            self::Subject => $value instanceof Subject ? $value->name : null,
            self::Date => $value instanceof CarbonInterface ? $value->format('M j, Y') : null,
            self::DateTime => $value instanceof CarbonInterface ? $value->format('M j, Y g:i A') : null,
            self::DateRange => self::range($value, 'M j, Y', ' – '),
            self::List => is_array($value) && $value !== [] ? implode(', ', $value) : null,
            self::Status => $value ? 'Active' : 'Inactive',
        };

        return $text ?? $empty;
    }

    /**
     * Convert a cell value for the CSV export: numbers stay numeric (percentages with 1 decimal), dates are
     * ISO formatted, enums and subjects become their names and lists are joined with semicolons.
     */
    public function csv(mixed $value): string|int|float|null
    {
        return match ($this) {
            self::Text => is_string($value) && trim($value) !== '' ? $value : null,
            self::Number, self::Level => is_int($value) || is_float($value) ? $value : null,
            self::Score, self::Rate => is_int($value) || is_float($value) ? round((float) $value, 1) : null,
            self::Band, self::Badge => $value instanceof BackedEnum && method_exists($value, 'label') ? $value->label() : null,
            self::Subject => $value instanceof Subject ? $value->name : null,
            self::Date => $value instanceof CarbonInterface ? $value->format('Y-m-d') : null,
            self::DateTime => $value instanceof CarbonInterface ? $value->format('Y-m-d H:i:s') : null,
            self::DateRange => self::range($value, 'Y-m-d', ' to '),
            self::List => is_array($value) && $value !== [] ? implode('; ', $value) : null,
            self::Status => $value ? 'Active' : 'Inactive',
        };
    }

    /**
     * Format a percentage with at most one decimal ("86.4%", "75%").
     */
    public static function percent(float $value): string
    {
        $formatted = number_format($value, 1);

        return (str_ends_with($formatted, '.0') ? substr($formatted, 0, -2) : $formatted).'%';
    }

    /**
     * Format a pair of dates as one date when they fall on the same day, otherwise as "first – last".
     */
    private static function range(mixed $value, string $format, string $separator): ?string
    {
        if (! is_array($value) || ! ($value[0] ?? null) instanceof CarbonInterface || ! ($value[1] ?? null) instanceof CarbonInterface) {
            return null;
        }

        [$first, $last] = $value;

        return $first->isSameDay($last)
            ? $first->format($format)
            : $first->format($format).$separator.$last->format($format);
    }
}
