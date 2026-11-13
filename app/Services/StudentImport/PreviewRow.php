<?php

namespace App\Services\StudentImport;

use App\Models\User;

/**
 * One previewed CSV row: its status, the problems that block it, notes about what will happen, and its matches.
 */
final readonly class PreviewRow
{
    /**
     * @param  list<string>  $errors  What keeps the row from being imported (Error rows only).
     * @param  list<string>  $notes  What the import will do or why it skips the row.
     * @param  User|null  $existingStudent  The learner whose LRN matches the row (studentProfile loaded).
     * @param  GuardianPlan|null  $guardian  The guardian link to make (Create and Enroll rows only).
     */
    public function __construct(
        public ValidatedRow $data,
        public ImportRowStatus $status,
        public array $errors = [],
        public array $notes = [],
        public ?User $existingStudent = null,
        public ?GuardianPlan $guardian = null,
    ) {}

    /**
     * Get the spreadsheet row number.
     */
    public function number(): int
    {
        return $this->data->number;
    }

    /**
     * Determine whether committing the import changes anything for this row.
     */
    public function isImportable(): bool
    {
        return $this->status->isImportable();
    }
}
