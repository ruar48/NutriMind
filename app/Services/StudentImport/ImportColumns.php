<?php

namespace App\Services\StudentImport;

/**
 * The columns of a learner CSV file: the downloadable template and the checks every uploaded file must pass.
 */
class ImportColumns
{
    /**
     * The template's columns, in order.
     *
     * @var list<string>
     */
    public const TEMPLATE_HEADERS = [
        'first_name',
        'last_name',
        'lrn',
        'grade_level',
        'sex',
        'birthdate',
        'username',
        'guardian_name',
        'guardian_email',
        'guardian_relationship',
    ];

    /**
     * The template's example rows, showing the accepted formats.
     *
     * @var list<list<string>>
     */
    public const TEMPLATE_EXAMPLE_ROWS = [
        ['Juan', 'Dela Cruz', '136742210001', '5', 'male', '2015-06-15', '', 'Maria Dela Cruz', 'maria.delacruz@example.com', 'mother'],
        ['Ana Marie', 'Santos', '136742200002', 'Grade 6', 'F', '03/21/2014', 'anasantos', 'Roberto Santos', 'roberto.santos@example.com', 'father'],
    ];

    /**
     * The single column a file may use instead of first_name and last_name.
     */
    public const FULL_NAME = 'name';

    /**
     * How a missing set of name columns is described.
     */
    private const NAME_COLUMNS = 'first_name and last_name (or name)';

    /**
     * Determine whether the file names learners with first_name and last_name columns (otherwise with a name column).
     */
    public static function usesNameParts(ParsedCsv $csv): bool
    {
        return $csv->hasColumn('first_name') && $csv->hasColumn('last_name');
    }

    /**
     * Get what keeps the file from being imported, most important first; empty when the file can be previewed.
     *
     * @param  bool  $gradeFromClassroom  Whether a classroom supplies the grade level, so the grade_level column is optional.
     * @return list<string>
     */
    public static function problems(ParsedCsv $csv, bool $gradeFromClassroom): array
    {
        $problems = [];
        $missing = self::missingColumns($csv, $gradeFromClassroom);

        if (count($missing) > 1) {
            $problems[] = 'The file is missing these columns: '.implode(', ', $missing).'.';
        } elseif ($missing === [self::NAME_COLUMNS]) {
            $problems[] = 'The file is missing the first_name and last_name columns (or a name column).';
        } elseif ($missing !== []) {
            $problems[] = 'The file is missing the '.$missing[0].' column.';
        }

        $duplicates = $csv->duplicateColumns();

        if ($duplicates !== []) {
            $problems[] = 'The file has more than one '.implode(', ', $duplicates).' column. Keep one of each.';
        }

        $rowCount = count($csv->rows);
        $maxRows = self::maxRows();

        if ($rowCount === 0) {
            $problems[] = 'The file has no learner rows. Add one learner per row below the heading row.';
        } elseif ($rowCount > $maxRows) {
            $problems[] = 'The file has '.number_format($rowCount).' learner rows. Import at most '.number_format($maxRows).' learners at a time.';
        }

        return $problems;
    }

    /**
     * Get the largest number of learner rows one file may contain.
     */
    public static function maxRows(): int
    {
        return max(1, (int) config('nutrimind.import.max_rows'));
    }

    /**
     * Get the largest file, in kilobytes, that may be uploaded.
     */
    public static function maxKilobytes(): int
    {
        return max(1, (int) config('nutrimind.import.max_kb'));
    }

    /**
     * Get the largest file size as people read it: "2 MB" (or "512 KB" below one megabyte).
     */
    public static function maxFileSizeLabel(): string
    {
        $kilobytes = self::maxKilobytes();

        return $kilobytes >= 1024
            ? rtrim(rtrim(number_format($kilobytes / 1024, 1), '0'), '.').' MB'
            : $kilobytes.' KB';
    }

    /**
     * Get the validation rules of the uploaded file (shared by both portals' upload forms).
     *
     * @return list<string>
     */
    public static function fileRules(): array
    {
        return ['required', 'file', 'mimes:csv,txt', 'extensions:csv,txt', 'max:'.self::maxKilobytes()];
    }

    /**
     * Get the messages of the uploaded file's rules.
     *
     * @return array<string, string>
     */
    public static function fileMessages(): array
    {
        $notCsv = 'Upload a CSV file (.csv). In Excel, choose File › Save As › CSV UTF-8.';

        return [
            'file.required' => 'Choose a CSV file to upload.',
            'file.file' => 'The file could not be uploaded. Try again.',
            'file.uploaded' => 'The file could not be uploaded. Make sure it is smaller than '.self::maxFileSizeLabel().'.',
            'file.mimes' => $notCsv,
            'file.extensions' => $notCsv,
            'file.max' => 'The file must not be larger than '.self::maxFileSizeLabel().'.',
        ];
    }

    /**
     * Get the required columns the file lacks, described the way the template names them.
     *
     * @return list<string>
     */
    private static function missingColumns(ParsedCsv $csv, bool $gradeFromClassroom): array
    {
        $missing = [];
        $hasFirstName = $csv->hasColumn('first_name');
        $hasLastName = $csv->hasColumn('last_name');

        if (! $csv->hasColumn(self::FULL_NAME) && ! ($hasFirstName && $hasLastName)) {
            $missing[] = match (true) {
                $hasFirstName => 'last_name',
                $hasLastName => 'first_name',
                default => self::NAME_COLUMNS,
            };
        }

        if (! $gradeFromClassroom && ! $csv->hasColumn('grade_level')) {
            $missing[] = 'grade_level';
        }

        return $missing;
    }
}
