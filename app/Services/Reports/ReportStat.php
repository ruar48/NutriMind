<?php

namespace App\Services\Reports;

/**
 * One summary tile of a generated report.
 */
final readonly class ReportStat
{
    public function __construct(
        /** The tile label, e.g. "Average score". */
        public string $label,
        /** The formatted value, e.g. "86.4%" or "—". */
        public string $value,
        /** The icon name of the tile. */
        public string $icon,
        /** The palette key of the tile. */
        public string $color = 'leaf',
        /** A short explanation under the value. */
        public ?string $hint = null,
        /** The number the value counts up to on screen (null for no animation). */
        public int|float|null $countUp = null,
        /** The decimals shown while counting up. */
        public int $countDecimals = 0,
        /** The suffix shown while counting up, e.g. "%". */
        public string $countSuffix = '',
    ) {}

    /**
     * Build a tile that shows a whole number.
     */
    public static function count(string $label, int $value, string $icon, string $color = 'leaf', ?string $hint = null): self
    {
        return new self($label, number_format($value), $icon, $color, $hint, $value);
    }

    /**
     * Build a tile that shows a percentage with at most one decimal ("—" when there is no value).
     */
    public static function percent(string $label, ?float $value, string $icon, string $color = 'leaf', ?string $hint = null): self
    {
        return $value === null
            ? new self($label, '—', $icon, $color, $hint)
            : new self($label, ColumnType::percent($value), $icon, $color, $hint, round($value, 1), 1, '%');
    }
}
