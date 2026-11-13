<?php

use App\Services\StudentImport\CsvParser;
use App\Services\StudentImport\InvalidImportFile;

it('reads the headings and the rows with their spreadsheet row numbers', function () {
    $csv = app(CsvParser::class)->parse("first_name,last_name,lrn\r\nJuan,Dela Cruz,136742210001\r\nAna,Santos,\r\n");

    expect($csv->headers)->toBe(['first_name', 'last_name', 'lrn'])
        ->and($csv->delimiter)->toBe(',')
        ->and($csv->rows)->toBe([
            ['number' => 2, 'values' => ['first_name' => 'Juan', 'last_name' => 'Dela Cruz', 'lrn' => '136742210001']],
            ['number' => 3, 'values' => ['first_name' => 'Ana', 'last_name' => 'Santos', 'lrn' => '']],
        ]);
});

it('strips the UTF-8 byte order mark from the first heading', function () {
    $csv = app(CsvParser::class)->parse("\xEF\xBB\xBFname,grade_level\nJosé Rizal,5\n");

    expect($csv->headers)->toBe(['name', 'grade_level'])
        ->and($csv->rows[0]['values'])->toBe(['name' => 'José Rizal', 'grade_level' => '5']);
});

it('detects semicolon-separated files', function () {
    $csv = app(CsvParser::class)->parse("name;grade_level;birthdate\nDela Cruz, Juan;5;06/15/2015\n");

    expect($csv->delimiter)->toBe(';')
        ->and($csv->rows[0]['values'])->toBe(['name' => 'Dela Cruz, Juan', 'grade_level' => '5', 'birthdate' => '06/15/2015']);
});

it('normalizes headings to lowercase with underscores', function () {
    $csv = app(CsvParser::class)->parse("  First Name ,LAST NAME,Guardian   Email,lrn\nJuan,Dela Cruz,maria@example.com,\n");

    expect($csv->headers)->toBe(['first_name', 'last_name', 'guardian_email', 'lrn']);
});

it('skips blank rows but keeps counting spreadsheet rows', function () {
    $csv = app(CsvParser::class)->parse("\n\nname,grade_level\n\nJuan Dela Cruz,5\n , \n,,\nAna Santos,6\n\n");

    expect(array_column($csv->rows, 'number'))->toBe([5, 8]);
});

it('reads quoted cells that contain delimiters, quotes and line breaks', function () {
    $csv = app(CsvParser::class)->parse("name,guardian_name\n\"Dela Cruz, Juan\",\"Maria \"\"Ria\"\"\nDela Cruz\"\nAna Santos,\n");

    expect($csv->rows)->toHaveCount(2)
        ->and($csv->rows[0]['values'])->toBe(['name' => 'Dela Cruz, Juan', 'guardian_name' => "Maria \"Ria\"\nDela Cruz"])
        ->and($csv->rows[1]['number'])->toBe(3);
});

it('converts Windows-1252 files to UTF-8', function () {
    $csv = app(CsvParser::class)->parse("name,grade_level\r\nNi\xF1o Pe\xF1aflor,5\r\n");

    expect($csv->rows[0]['values']['name'])->toBe('Niño Peñaflor');
});

it('reads files with old Mac line endings', function () {
    $csv = app(CsvParser::class)->parse("name,grade_level\rJuan Dela Cruz,5\rAna Santos,6\r");

    expect(array_column(array_column($csv->rows, 'values'), 'name'))->toBe(['Juan Dela Cruz', 'Ana Santos']);
});

it('fills missing cells with empty strings and ignores extra cells', function () {
    $csv = app(CsvParser::class)->parse("name,grade_level,lrn\nJuan Dela Cruz\nAna Santos,6,136742200002,extra,cells\n");

    expect($csv->rows[0]['values'])->toBe(['name' => 'Juan Dela Cruz', 'grade_level' => '', 'lrn' => ''])
        ->and($csv->rows[1]['values'])->toBe(['name' => 'Ana Santos', 'grade_level' => '6', 'lrn' => '136742200002']);
});

it('trims spaces, tabs and non-breaking spaces around cells', function () {
    $csv = app(CsvParser::class)->parse("name,lrn\n\t Juan Dela Cruz\u{00A0} ,  136742210001 \n");

    expect($csv->rows[0]['values'])->toBe(['name' => 'Juan Dela Cruz', 'lrn' => '136742210001']);
});

it('ignores columns without a heading', function () {
    $csv = app(CsvParser::class)->parse("name,,grade_level,\nJuan Dela Cruz,note,5,more\n");

    expect($csv->headers)->toBe(['name', 'grade_level'])
        ->and($csv->rows[0]['values'])->toBe(['name' => 'Juan Dela Cruz', 'grade_level' => '5']);
});

it('keeps the first of repeated columns and reports the repeats', function () {
    $csv = app(CsvParser::class)->parse("name,lrn,LRN,grade_level\nJuan Dela Cruz,136742210001,999999999999,5\n");

    expect($csv->duplicateColumns())->toBe(['lrn'])
        ->and($csv->rows[0]['values']['lrn'])->toBe('136742210001');
});

it('rejects a file without a heading row', function (string $contents) {
    app(CsvParser::class)->parse($contents);
})->with([
    'empty file' => [''],
    'byte order mark only' => ["\xEF\xBB\xBF"],
    'blank lines only' => ["\r\n\r\n , \r\n"],
])->throws(InvalidImportFile::class, 'The file is empty. Add a heading row and one learner per row.');
