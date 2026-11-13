<?php

namespace App\Services\Reports;

/**
 * One column of a generated report: the key of its cell values, its heading and how values are formatted.
 */
final readonly class ReportColumn
{
    public function __construct(
        /** The key of the column's value in every ReportRow::$cells array. */
        public string $key,
        /** The heading shown in the preview, the print view and the CSV file. */
        public string $label,
        /** How the column's values are formatted. */
        public ColumnType $type = ColumnType::Text,
        /** The text shown for an empty value. */
        public string $empty = '—',
    ) {}

    /**
     * Format the column's value of the row as plain text.
     */
    public function display(ReportRow $row): string
    {
        return $this->type->display($row->value($this->key), $this->empty);
    }

    /**
     * Get the column's value of the row as a CSV cell.
     */
    public function csv(ReportRow $row): string|int|float|null
    {
        return $this->type->csv($row->value($this->key));
    }

    /**
     * Get the horizontal alignment of the column's cells.
     */
    public function align(): string
    {
        return $this->type->align();
    }

    /**
     * Determine whether the column holds numbers.
     */
    public function isNumeric(): bool
    {
        return $this->type->isNumeric();
    }
}
