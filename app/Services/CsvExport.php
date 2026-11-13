<?php

namespace App\Services;

use BackedEnum;
use DateTimeInterface;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Traversable;

/**
 * Streams spreadsheet-safe CSV downloads.
 *
 * The file starts with a UTF-8 byte order mark (so Excel reads accents correctly), uses RFC 4180 quoting with CRLF
 * line endings, and neutralizes spreadsheet formula injection: a text cell that starts with =, +, -, @, a tab or a
 * carriage return is prefixed with an apostrophe. Numbers (int/float) are written as they are.
 */
class CsvExport
{
    /**
     * The UTF-8 byte order mark written at the start of every file.
     */
    public const BYTE_ORDER_MARK = "\xEF\xBB\xBF";

    /**
     * The characters that make a spreadsheet treat a cell as a formula.
     *
     * @var list<string>
     */
    private const FORMULA_TRIGGERS = ['=', '+', '-', '@', "\t", "\r"];

    /**
     * The longest file name (without the .csv extension) a download gets.
     */
    private const MAX_FILENAME_LENGTH = 150;

    /**
     * Stream a CSV download of the headings followed by the rows.
     *
     * Rows may be an array, a Collection or a LazyCollection (rows are read while streaming, so a LazyCollection or
     * a lazy() query never loads everything at once). Each row is an array, an Arrayable (Collection, model), a
     * Traversable or a plain object such as a query builder row; its values must be in heading order (keys are
     * ignored). Cells: null → empty, bool → Yes/No, enums → label() (or their value), dates → Y-m-d H:i:s, other
     * values → their string form. Pass numbers as int/float so they stay numeric.
     *
     * @param  array<int|string, mixed>  $headings
     * @param  iterable<mixed>  $rows
     */
    public function download(string $filename, array $headings, iterable $rows): StreamedResponse
    {
        return response()->streamDownload(function () use ($headings, $rows): void {
            $output = fopen('php://output', 'w');

            fwrite($output, self::BYTE_ORDER_MARK);
            $this->writeRow($output, $headings);

            foreach ($rows as $row) {
                $this->writeRow($output, $row);
            }

            fclose($output);
        }, $this->filename($filename), [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * Turn any requested name into a safe download name: an ASCII slug ending in ".csv".
     */
    public function filename(string $name): string
    {
        $slug = Str::slug(preg_replace('/\.csv$/i', '', trim($name)));
        $slug = rtrim(substr($slug, 0, self::MAX_FILENAME_LENGTH), '-');

        return ($slug === '' ? 'export' : $slug).'.csv';
    }

    /**
     * Write one CSV line.
     *
     * @param  resource  $output
     */
    private function writeRow($output, mixed $row): void
    {
        $values = match (true) {
            $row instanceof Arrayable => $row->toArray(),
            $row instanceof Traversable => iterator_to_array($row, false),
            default => (array) $row,
        };

        fputcsv($output, array_map($this->cell(...), array_values($values)), ',', '"', '', "\r\n");
    }

    /**
     * Convert a value to the text of one cell, neutralizing formula triggers in text.
     */
    private function cell(mixed $value): string
    {
        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        $text = match (true) {
            $value === null => '',
            is_bool($value) => $value ? 'Yes' : 'No',
            $value instanceof BackedEnum => method_exists($value, 'label') ? $value->label() : (string) $value->value,
            $value instanceof DateTimeInterface => $value->format('Y-m-d H:i:s'),
            default => (string) $value,
        };

        return $text !== '' && in_array($text[0], self::FORMULA_TRIGGERS, true) ? "'".$text : $text;
    }
}
