<?php

namespace App\Services\StudentImport;

use RuntimeException;

/**
 * Thrown when an uploaded learner file cannot be read as a CSV file with a heading row.
 *
 * The message is written for the person who uploaded the file.
 */
class InvalidImportFile extends RuntimeException
{
    /**
     * The file has no heading row.
     */
    public static function empty(): self
    {
        return new self('The file is empty. Add a heading row and one learner per row.');
    }
}
