<?php

namespace App\Services\Reports;

use App\Enums\ReportType;
use Carbon\CarbonInterface;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/**
 * A generated report: what ReportBuilder::build() returns for every ReportType.
 *
 * The preview paginates the rows, the CSV export writes all of them and the print view lists all of them.
 */
final readonly class Report
{
    /**
     * The number of rows per preview page.
     */
    public const PER_PAGE = 25;

    /**
     * @param  array<string, string>  $filters  The scope and the filters that shaped the report, label => value (e.g. "Classroom" => "Health 5 – Sampaguita").
     * @param  list<ReportStat>  $summary
     * @param  list<ReportColumn>  $columns
     * @param  Collection<int, ReportRow>  $rows
     * @param  list<string>  $notes  Sentences that explain how the figures are computed.
     * @param  array{title: string, description: string, segments: list<array{label: string, value: int, color?: string, slot?: int}>}|null  $breakdown  A distribution drawn as a stacked bar.
     */
    public function __construct(
        public ReportType $type,
        public string $title,
        public string $description,
        public array $filters,
        public array $summary,
        public array $columns,
        public Collection $rows,
        public array $notes = [],
        public ?array $breakdown = null,
    ) {}

    /**
     * Determine whether the report has no rows.
     */
    public function isEmpty(): bool
    {
        return $this->rows->isEmpty();
    }

    /**
     * Get the CSV headings (the column labels).
     *
     * @return list<string>
     */
    public function csvHeadings(): array
    {
        return array_map(fn (ReportColumn $column): string => $column->label, $this->columns);
    }

    /**
     * Get every row as a list of CSV cells in column order.
     *
     * @return Collection<int, list<string|int|float|null>>
     */
    public function csvRows(): Collection
    {
        return $this->rows->map(fn (ReportRow $row): array => array_map(
            fn (ReportColumn $column): string|int|float|null => $column->csv($row),
            $this->columns,
        ));
    }

    /**
     * Get the CSV file name, e.g. "student-performance-2026-11-13" (CsvExport adds ".csv").
     */
    public function filename(CarbonInterface $date): string
    {
        return $this->type->value.'-'.$date->format('Y-m-d');
    }

    /**
     * Paginate the rows for the preview, keeping the query string. A page past the end shows the last page.
     *
     * @return LengthAwarePaginator<int, ReportRow>
     */
    public function paginate(int $perPage = self::PER_PAGE): LengthAwarePaginator
    {
        $lastPage = max(1, (int) ceil($this->rows->count() / $perPage));
        $page = min(max(1, LengthAwarePaginator::resolveCurrentPage()), $lastPage);

        return (new LengthAwarePaginator(
            $this->rows->forPage($page, $perPage)->values(),
            $this->rows->count(),
            $perPage,
            $page,
            ['path' => LengthAwarePaginator::resolveCurrentPath()],
        ))->withQueryString();
    }
}
