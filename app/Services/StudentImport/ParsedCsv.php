<?php

namespace App\Services\StudentImport;

/**
 * The header and data rows read from a learner CSV file.
 */
final readonly class ParsedCsv
{
    /**
     * @param  list<string>  $headers  Normalized column names in file order (blank headings left out, duplicates kept).
     * @param  list<array{number: int, values: array<string, string>}>  $rows  Non-blank data rows: the spreadsheet row
     *                                                                         number (the heading row is 1) and the trimmed cell of each column (the first one
     *                                                                         when a column name repeats; '' for a missing cell).
     * @param  string  $delimiter  The detected delimiter (',' or ';').
     */
    public function __construct(
        public array $headers,
        public array $rows,
        public string $delimiter,
    ) {}

    /**
     * Determine whether the file has the column.
     */
    public function hasColumn(string $column): bool
    {
        return in_array($column, $this->headers, true);
    }

    /**
     * Get the column names that appear more than once.
     *
     * @return list<string>
     */
    public function duplicateColumns(): array
    {
        return array_values(array_unique(array_diff_assoc($this->headers, array_unique($this->headers))));
    }
}
