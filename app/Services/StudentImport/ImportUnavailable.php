<?php

namespace App\Services\StudentImport;

use RuntimeException;

/**
 * Thrown when a pending import can no longer be previewed or committed; the upload has to start over.
 *
 * The message is written for the person who uploaded the file.
 */
class ImportUnavailable extends RuntimeException
{
    /**
     * The uploaded file was deleted (e.g. it was older than a day).
     */
    public static function fileMissing(): self
    {
        return new self('The uploaded file is no longer available. Upload it again.');
    }

    /**
     * The stored file no longer passes the upload checks.
     */
    public static function unreadable(): self
    {
        return new self('The uploaded file can no longer be read. Upload it again.');
    }

    /**
     * The classroom chosen for the import was archived or deleted after the upload.
     */
    public static function classroomUnavailable(): self
    {
        return new self('The classroom chosen for this import was archived or deleted. Upload the file again.');
    }
}
