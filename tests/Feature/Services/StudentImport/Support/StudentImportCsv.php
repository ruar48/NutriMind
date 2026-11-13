<?php

namespace Tests\Feature\Services\StudentImport\Support;

use App\Services\StudentImport\ImportColumns;
use Illuminate\Http\UploadedFile;

/**
 * Builds learner CSV files for the import tests.
 */
final class StudentImportCsv
{
    /**
     * A complete, valid learner row in the template layout; override any column.
     *
     * @param  array<string, string>  $overrides
     * @return array<string, string>
     */
    public static function row(array $overrides = []): array
    {
        return [
            'first_name' => 'Juan',
            'last_name' => 'Dela Cruz',
            'lrn' => '',
            'grade_level' => '5',
            'sex' => 'male',
            'birthdate' => '2015-06-15',
            'username' => '',
            'guardian_name' => '',
            'guardian_email' => '',
            'guardian_relationship' => '',
            ...$overrides,
        ];
    }

    /**
     * Build the CSV text of the rows (each keyed by column) under the headers.
     *
     * @param  list<array<string, string>>  $rows
     * @param  list<string>|null  $headers  The template headers when null.
     */
    public static function make(array $rows, ?array $headers = null, string $delimiter = ','): string
    {
        $headers ??= ImportColumns::TEMPLATE_HEADERS;
        $lines = [implode($delimiter, $headers)];

        foreach ($rows as $row) {
            $lines[] = implode($delimiter, array_map(
                fn (string $header): string => self::cell($row[$header] ?? '', $delimiter),
                $headers,
            ));
        }

        return implode("\r\n", $lines)."\r\n";
    }

    /**
     * Wrap CSV text in an uploaded file.
     */
    public static function upload(string $contents, string $name = 'grade-5-sampaguita.csv'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, $contents);
    }

    /**
     * Quote a cell when it contains the delimiter, a quote or a line break.
     */
    private static function cell(string $value, string $delimiter): string
    {
        if (str_contains($value, $delimiter) || str_contains($value, '"') || str_contains($value, "\n") || str_contains($value, "\r")) {
            return '"'.str_replace('"', '""', $value).'"';
        }

        return $value;
    }
}
