<?php

use App\Enums\GradeLevel;
use App\Services\CsvExport;
use Illuminate\Support\Carbon;
use Illuminate\Support\LazyCollection;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

enum CsvExportTestUnlabeledStatus: string
{
    case Archived = 'archived';
}

/**
 * Read the body a CSV download streams.
 */
function csvExportBody(StreamedResponse $response): string
{
    return TestResponse::fromBaseResponse($response)->streamedContent();
}

it('streams a UTF-8 CSV attachment under a safe file name', function () {
    $response = TestResponse::fromBaseResponse(
        app(CsvExport::class)->download('Learners 2026-11-13.csv', ['Name'], [['Ana']]),
    );

    $response->assertOk()
        ->assertHeader('Content-Type', 'text/csv; charset=UTF-8')
        ->assertDownload('learners-2026-11-13.csv');
});

it('writes a byte order mark, the headings and every row with RFC 4180 quoting', function () {
    $response = app(CsvExport::class)->download('learners', ['Name', 'Score', 'Note'], [
        ['Ana', 90, 'Great job'],
        ['Cruz, Juan', 85.5, 'Said "wow"'],
        ['Liza', 70, "Line one\nLine two"],
    ]);

    expect(csvExportBody($response))->toBe(
        "\xEF\xBB\xBF"
        ."Name,Score,Note\r\n"
        ."Ana,90,\"Great job\"\r\n"
        ."\"Cruz, Juan\",85.5,\"Said \"\"wow\"\"\"\r\n"
        ."Liza,70,\"Line one\nLine two\"\r\n"
    );
});

it('prefixes text cells that a spreadsheet would run as a formula', function (string $cell, string $line) {
    $response = app(CsvExport::class)->download('report', ['Value'], [[$cell]]);

    expect(csvExportBody($response))->toBe("\xEF\xBB\xBFValue\r\n".$line."\r\n");
})->with([
    'equals sign' => ['=HYPERLINK("http://evil.test","Click")', '"\'=HYPERLINK(""http://evil.test"",""Click"")"'],
    'plus sign' => ['+639171234567', "'+639171234567"],
    'minus sign' => ['-2+3', "'-2+3"],
    'at sign' => ['@SUM(A1:A2)', "'@SUM(A1:A2)"],
    'tab' => ["\t=1+1", "\"'\t=1+1\""],
    'carriage return' => ["\r=1+1", "\"'\r=1+1\""],
]);

it('leaves text that only contains formula characters later on alone', function () {
    $response = app(CsvExport::class)->download('report', ['Value'], [['Rice = Go food'], ['ana@example.com']]);

    expect(csvExportBody($response))->toBe("\xEF\xBB\xBFValue\r\n\"Rice = Go food\"\r\nana@example.com\r\n");
});

it('neutralizes formula triggers in the headings', function () {
    $response = app(CsvExport::class)->download('report', ['=Name'], []);

    expect(csvExportBody($response))->toBe("\xEF\xBB\xBF'=Name\r\n");
});

it('keeps negative numbers numeric', function () {
    $response = app(CsvExport::class)->download('report', ['Change', 'Delta'], [[-5, -1.5]]);

    expect(csvExportBody($response))->toBe("\xEF\xBB\xBFChange,Delta\r\n-5,-1.5\r\n");
});

it('writes nulls, booleans, enums and dates as readable text', function () {
    $response = app(CsvExport::class)->download('report', ['Values'], [[
        null,
        true,
        false,
        GradeLevel::Five,
        CsvExportTestUnlabeledStatus::Archived,
        Carbon::create(2026, 11, 13, 8, 30, 5),
        new DateTimeImmutable('2026-01-02 03:04:05'),
        Str::of('Rizal'),
    ]]);

    expect(csvExportBody($response))->toBe(
        "\xEF\xBB\xBFValues\r\n"
        .",Yes,No,\"Grade 5\",archived,\"2026-11-13 08:30:05\",\"2026-01-02 03:04:05\",Rizal\r\n"
    );
});

it('accepts rows from arrays, collections and lazy collections', function (iterable $rows) {
    $response = app(CsvExport::class)->download('report', ['Name', 'Grade'], $rows);

    expect(csvExportBody($response))->toBe("\xEF\xBB\xBFName,Grade\r\nAna,5\r\nBen,6\r\n");
})->with([
    'an array of lists' => fn () => [['Ana', 5], ['Ben', 6]],
    'an array of keyed rows' => fn () => [['name' => 'Ana', 'grade' => 5], ['name' => 'Ben', 'grade' => 6]],
    'a collection of collections' => fn () => collect([collect(['Ana', 5]), collect(['Ben', 6])]),
    'a collection of plain objects' => fn () => collect([(object) ['name' => 'Ana', 'grade' => 5], (object) ['name' => 'Ben', 'grade' => 6]]),
    'a lazy collection of generators' => fn () => LazyCollection::make(function () {
        yield (function () {
            yield 'Ana';
            yield 5;
        })();
        yield (function () {
            yield 'Ben';
            yield 6;
        })();
    }),
]);

it('reads lazy rows only while the response streams', function () {
    $rowsRead = 0;
    $rows = LazyCollection::make(function () use (&$rowsRead) {
        foreach (['Ana', 'Ben'] as $name) {
            $rowsRead++;

            yield [$name];
        }
    });

    $response = app(CsvExport::class)->download('report', ['Name'], $rows);

    expect($rowsRead)->toBe(0);

    csvExportBody($response);

    expect($rowsRead)->toBe(2);
});

it('turns the requested name into an ASCII slug ending in .csv', function (string $requested, string $filename) {
    expect(app(CsvExport::class)->filename($requested))->toBe($filename);
})->with([
    'a title with spaces and an extension' => ['Learner Performance 2026-11-13.csv', 'learner-performance-2026-11-13.csv'],
    'a slug without an extension' => ['quiz-results', 'quiz-results.csv'],
    'accents and an uppercase extension' => ['Niño Peña Report.CSV', 'nino-pena-report.csv'],
    'path traversal' => ['../../etc/passwd', 'etcpasswd.csv'],
    'surrounding whitespace' => ['  roster.csv  ', 'roster.csv'],
    'an empty name' => ['', 'export.csv'],
    'only punctuation' => ['***', 'export.csv'],
]);

it('shortens long file names to 150 characters without a trailing dash', function () {
    $csv = app(CsvExport::class);

    expect($csv->filename(str_repeat('a', 200)))->toBe(str_repeat('a', 150).'.csv');
    expect($csv->filename(str_repeat('a', 149).' b'))->toBe(str_repeat('a', 149).'.csv');
});
