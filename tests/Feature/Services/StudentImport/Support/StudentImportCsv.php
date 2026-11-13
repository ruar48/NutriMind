<?php

namespace Tests\Feature\Services\StudentImport\Support;

use App\Enums\GradeLevel;
use App\Models\Classroom;
use App\Models\StudentProfile;
use App\Models\User;
use App\Services\StudentImport\CsvParser;
use App\Services\StudentImport\ImportColumns;
use App\Services\StudentImport\ImportPreview;
use App\Services\StudentImport\PendingImport;
use App\Services\StudentImport\PendingImports;
use App\Services\StudentImport\StudentImportPreviewer;
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
     * Parse and preview the rows (each keyed by column) for the classroom.
     *
     * @param  list<array<string, string>>  $rows
     * @param  list<string>|null  $headers
     */
    public static function preview(array $rows, ?Classroom $classroom = null, ?array $headers = null): ImportPreview
    {
        return app(StudentImportPreviewer::class)->preview(app(CsvParser::class)->parse(self::make($rows, $headers)), $classroom);
    }

    /**
     * Create an active learner with the LRN in the grade.
     *
     * @param  array<string, mixed>  $attributes
     */
    public static function learner(string $lrn, GradeLevel $grade = GradeLevel::Five, array $attributes = []): User
    {
        return User::factory()
            ->studentWithoutProfile()
            ->has(StudentProfile::factory()->state(['lrn' => $lrn, 'grade_level' => $grade]), 'studentProfile')
            ->create($attributes);
    }

    /**
     * Store the rows as the user's pending import (as an upload would), on the faked private disk.
     *
     * @param  list<array<string, string>>  $rows
     * @param  list<string>|null  $headers
     */
    public static function pending(User $user, array $rows, ?Classroom $classroom = null, ?array $headers = null): PendingImport
    {
        return app(PendingImports::class)->store(self::upload(self::make($rows, $headers)), $user, $classroom);
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
