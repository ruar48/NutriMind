<?php

namespace App\Services\StudentImport;

/**
 * What the importer does with one row of a learner CSV file.
 */
enum ImportRowStatus: string
{
    case Create = 'create';
    case Enroll = 'enroll';
    case Skip = 'skip';
    case Error = 'error';

    /**
     * Get the human-readable label of one row's status.
     */
    public function label(): string
    {
        return match ($this) {
            self::Create => 'New learner',
            self::Enroll => 'Enroll existing',
            self::Skip => 'Skip',
            self::Error => 'Error',
        };
    }

    /**
     * Get the label of a group of rows with this status (preview tabs).
     */
    public function pluralLabel(): string
    {
        return match ($this) {
            self::Create => 'New learners',
            self::Enroll => 'Enroll existing',
            self::Skip => 'Skipped',
            self::Error => 'Errors',
        };
    }

    /**
     * Get one sentence that explains the status.
     */
    public function description(): string
    {
        return match ($this) {
            self::Create => 'A new learner account is created.',
            self::Enroll => 'The learner already has an account and is enrolled in the classroom.',
            self::Skip => 'Nothing changes for this learner.',
            self::Error => 'The row is not imported until its problems are fixed.',
        };
    }

    /**
     * Get the palette key that represents the status.
     */
    public function color(): string
    {
        return match ($this) {
            self::Create => 'leaf',
            self::Enroll => 'sky',
            self::Skip => 'slate',
            self::Error => 'berry',
        };
    }

    /**
     * Get the icon name that represents the status.
     */
    public function icon(): string
    {
        return match ($this) {
            self::Create => 'user-plus',
            self::Enroll => 'user-check',
            self::Skip => 'circle-dashed',
            self::Error => 'circle-x',
        };
    }

    /**
     * Determine whether rows with this status change anything when the import is committed.
     */
    public function isImportable(): bool
    {
        return $this === self::Create || $this === self::Enroll;
    }
}
