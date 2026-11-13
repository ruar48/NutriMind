<?php

namespace App\Services\StudentImport;

use RuntimeException;

/**
 * Thrown when a commit changes nothing; the preview stays available so the person can review it again.
 *
 * The message is written for the person who uploaded the file.
 */
class ImportNotCommitted extends RuntimeException
{
    /**
     * Every row is skipped or has errors.
     */
    public static function nothingToImport(): self
    {
        return new self('Nothing was imported: every row is skipped or has errors. Fix the file and upload it again.');
    }

    /**
     * Another change took a username, LRN or email address of the file while the import ran.
     */
    public static function conflict(): self
    {
        return new self('Nothing was imported because some learners or parents changed in the meantime. Review the preview and import again.');
    }
}
