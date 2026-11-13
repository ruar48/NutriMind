<?php

namespace App\Services\StudentImport;

use Illuminate\Support\Str;

/**
 * Reads learner CSV files the way spreadsheets save them.
 *
 * Accepts a UTF-8 byte order mark, UTF-16 text with a byte order mark, Windows-1252 text (Excel's "CSV (Comma delimited)"), comma or semicolon
 * delimiters (detected from the heading row), quoted cells with delimiters or line breaks, and CRLF, LF or CR line
 * endings. Headings are trimmed, lowercased and have spaces turned into underscores ("First Name" → first_name).
 * Fully blank rows are skipped but still count toward the spreadsheet row numbers.
 */
class CsvParser
{
    /**
     * The UTF-8 byte order mark that Excel writes at the start of "CSV UTF-8" files.
     */
    private const BYTE_ORDER_MARK = "\xEF\xBB\xBF";

    /**
     * The byte order mark of UTF-16 little-endian text (Excel's "Unicode Text").
     */
    private const UTF16_LITTLE_ENDIAN_MARK = "\xFF\xFE";

    /**
     * The byte order mark of UTF-16 big-endian text.
     */
    private const UTF16_BIG_ENDIAN_MARK = "\xFE\xFF";

    /**
     * Parse the file contents.
     *
     * @throws InvalidImportFile when the file has no heading row.
     */
    public function parse(string $contents): ParsedCsv
    {
        $contents = $this->toUtf8($contents);
        $delimiter = $this->detectDelimiter($contents);

        $stream = fopen('php://temp', 'r+');
        fwrite($stream, $contents);
        rewind($stream);

        $headers = null;
        $positions = [];
        $rows = [];
        $number = 0;

        while (($cells = fgetcsv($stream, null, $delimiter, '"', '')) !== false) {
            $number++;
            $cells = array_map(fn (?string $cell): string => Str::trim((string) $cell), $cells);

            if ($this->isBlank($cells)) {
                continue;
            }

            if ($headers === null) {
                $headers = [];

                foreach ($cells as $position => $cell) {
                    $header = $this->normalizeHeader($cell);

                    if ($header === '') {
                        continue;
                    }

                    $headers[] = $header;
                    $positions[$header] ??= $position;
                }

                continue;
            }

            $values = [];

            foreach ($positions as $header => $position) {
                $values[$header] = $cells[$position] ?? '';
            }

            $rows[] = ['number' => $number, 'values' => $values];
        }

        fclose($stream);

        if ($headers === null || $headers === []) {
            throw InvalidImportFile::empty();
        }

        return new ParsedCsv($headers, $rows, $delimiter);
    }

    /**
     * Normalize a heading: trim, lowercase, and replace runs of spaces with one underscore.
     */
    public function normalizeHeader(string $heading): string
    {
        return (string) preg_replace('/\s+/u', '_', mb_strtolower(trim($heading)));
    }

    /**
     * Drop the byte order mark, convert Windows-1252 text to UTF-8, and turn old Mac line endings into line feeds.
     */
    private function toUtf8(string $contents): string
    {
        if (str_starts_with($contents, self::BYTE_ORDER_MARK)) {
            $contents = substr($contents, strlen(self::BYTE_ORDER_MARK));
        } elseif (str_starts_with($contents, self::UTF16_LITTLE_ENDIAN_MARK)) {
            $contents = mb_convert_encoding(substr($contents, 2), 'UTF-8', 'UTF-16LE');
        } elseif (str_starts_with($contents, self::UTF16_BIG_ENDIAN_MARK)) {
            $contents = mb_convert_encoding(substr($contents, 2), 'UTF-8', 'UTF-16BE');
        }

        if (! mb_check_encoding($contents, 'UTF-8')) {
            $contents = mb_convert_encoding($contents, 'UTF-8', 'Windows-1252');
        }

        if (! str_contains($contents, "\n")) {
            $contents = str_replace("\r", "\n", $contents);
        }

        return $contents;
    }

    /**
     * Use a semicolon when the first non-blank line has more semicolons than commas, as European Excel saves CSV files.
     */
    private function detectDelimiter(string $contents): string
    {
        foreach (preg_split('/\r\n|\n/', $contents) ?: [] as $line) {
            if (trim($line) === '') {
                continue;
            }

            return substr_count($line, ';') > substr_count($line, ',') ? ';' : ',';
        }

        return ',';
    }

    /**
     * Determine whether every cell of a row is empty.
     *
     * @param  list<string>  $cells
     */
    private function isBlank(array $cells): bool
    {
        foreach ($cells as $cell) {
            if ($cell !== '') {
                return false;
            }
        }

        return true;
    }
}
