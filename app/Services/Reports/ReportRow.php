<?php

namespace App\Services\Reports;

/**
 * One row of a generated report.
 */
final readonly class ReportRow
{
    /**
     * @param  array<string, mixed>  $cells  Raw cell values keyed by column key (see ColumnType for the value types).
     * @param  list<array{label: string, color: string, icon: string}>  $tags  Badges shown next to the first cell in the preview (e.g. "Archived").
     */
    public function __construct(
        public array $cells,
        /** The page the first cell links to in the preview, or null for no link. */
        public ?string $url = null,
        public array $tags = [],
    ) {}

    /**
     * Get the raw value of a column.
     */
    public function value(string $key): mixed
    {
        return $this->cells[$key] ?? null;
    }
}
