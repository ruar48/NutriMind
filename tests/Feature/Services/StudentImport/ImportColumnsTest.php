<?php

use App\Services\StudentImport\CsvParser;
use App\Services\StudentImport\ImportColumns;
use Tests\Feature\Services\StudentImport\Support\StudentImportCsv;

it('accepts the template layout', function () {
    $csv = app(CsvParser::class)->parse(StudentImportCsv::make([StudentImportCsv::row()]));

    expect(ImportColumns::problems($csv, gradeFromClassroom: false))->toBe([])
        ->and(ImportColumns::usesNameParts($csv))->toBeTrue();
});

it('accepts a single name column instead of first_name and last_name', function () {
    $csv = app(CsvParser::class)->parse("name,grade_level\nJuan Dela Cruz,5\n");

    expect(ImportColumns::problems($csv, gradeFromClassroom: false))->toBe([])
        ->and(ImportColumns::usesNameParts($csv))->toBeFalse();
});

it('names the missing required columns', function (string $contents, bool $gradeFromClassroom, string $problem) {
    $csv = app(CsvParser::class)->parse($contents);

    expect(ImportColumns::problems($csv, $gradeFromClassroom))->toBe([$problem]);
})->with([
    'no name columns' => ["lrn,grade_level\n136742210001,5\n", false, 'The file is missing the first_name and last_name columns (or a name column).'],
    'no last name' => ["first_name,grade_level\nJuan,5\n", false, 'The file is missing the last_name column.'],
    'no first name' => ["last_name,grade_level\nDela Cruz,5\n", false, 'The file is missing the first_name column.'],
    'no grade without a classroom' => ["name,lrn\nJuan Dela Cruz,\n", false, 'The file is missing the grade_level column.'],
    'no names and no grade' => ["lrn,sex\n136742210001,male\n", false, 'The file is missing these columns: first_name and last_name (or name), grade_level.'],
]);

it('makes the grade_level column optional when a classroom supplies the grade', function () {
    $csv = app(CsvParser::class)->parse("first_name,last_name\nJuan,Dela Cruz\n");

    expect(ImportColumns::problems($csv, gradeFromClassroom: true))->toBe([]);
});

it('rejects repeated columns', function () {
    $csv = app(CsvParser::class)->parse("name,grade_level,lrn,LRN\nJuan Dela Cruz,5,,\n");

    expect(ImportColumns::problems($csv, gradeFromClassroom: false))->toBe(['The file has more than one lrn column. Keep one of each.']);
});

it('rejects a file without learner rows', function () {
    $csv = app(CsvParser::class)->parse("name,grade_level\n\n , \n");

    expect(ImportColumns::problems($csv, gradeFromClassroom: false))
        ->toBe(['The file has no learner rows. Add one learner per row below the heading row.']);
});

it('rejects more rows than the import limit', function () {
    config(['nutrimind.import.max_rows' => 3]);
    $csv = app(CsvParser::class)->parse("name,grade_level\nA One,5\nB Two,5\nC Three,5\nD Four,5\n");

    expect(ImportColumns::problems($csv, gradeFromClassroom: false))
        ->toBe(['The file has 4 learner rows. Import at most 3 learners at a time.']);
});

it('accepts exactly the import limit', function () {
    config(['nutrimind.import.max_rows' => 3]);
    $csv = app(CsvParser::class)->parse("name,grade_level\nA One,5\nB Two,5\nC Three,5\n");

    expect(ImportColumns::problems($csv, gradeFromClassroom: false))->toBe([]);
});

it('lists every problem, most important first', function () {
    $csv = app(CsvParser::class)->parse("lrn,lrn\n");

    expect(ImportColumns::problems($csv, gradeFromClassroom: false))->toBe([
        'The file is missing these columns: first_name and last_name (or name), grade_level.',
        'The file has more than one lrn column. Keep one of each.',
        'The file has no learner rows. Add one learner per row below the heading row.',
    ]);
});

it('describes the upload size limit', function (int $kilobytes, string $label) {
    config(['nutrimind.import.max_kb' => $kilobytes]);

    expect(ImportColumns::maxFileSizeLabel())->toBe($label);
})->with([
    'whole megabytes' => [2048, '2 MB'],
    'fractional megabytes' => [2560, '2.5 MB'],
    'ten megabytes' => [10240, '10 MB'],
    'below one megabyte' => [512, '512 KB'],
]);
