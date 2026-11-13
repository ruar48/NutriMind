<?php

namespace App\Services\StudentImport;

use App\Enums\GradeLevel;
use App\Enums\GuardianRelationship;
use App\Enums\Sex;

/**
 * One CSV row after the per-row rules ran: the normalized values (null when blank or invalid) and the problems found.
 */
final readonly class ValidatedRow
{
    /**
     * @param  int  $number  Spreadsheet row number (the heading row is 1).
     * @param  array<string, string>  $raw  The trimmed cells by column, as uploaded.
     * @param  string|null  $name  The learner's full name, whitespace squished.
     * @param  string|null  $firstName  The given name (files with first_name and last_name columns).
     * @param  string|null  $lastName  The surname (files with first_name and last_name columns).
     * @param  string|null  $lrn  The 12-digit learner reference number.
     * @param  GradeLevel|null  $grade  The grade level (the classroom's grade when the cell is blank).
     * @param  string|null  $birthdate  The birthdate as Y-m-d.
     * @param  string|null  $guardianEmail  The guardian's email address, lowercased.
     * @param  list<string>  $errors  What keeps the row from being imported.
     */
    public function __construct(
        public int $number,
        public array $raw,
        public ?string $name,
        public ?string $firstName,
        public ?string $lastName,
        public ?string $lrn,
        public ?GradeLevel $grade,
        public ?Sex $sex,
        public ?string $birthdate,
        public ?string $username,
        public ?string $guardianName,
        public ?string $guardianEmail,
        public GuardianRelationship $guardianRelationship,
        public array $errors,
    ) {}

    /**
     * Get the uploaded cell of a column ('' when the column is missing or blank).
     */
    public function rawValue(string $column): string
    {
        return $this->raw[$column] ?? '';
    }

    /**
     * Get the best name to show for the row, even when the name cells are invalid.
     */
    public function displayName(): string
    {
        if ($this->name !== null) {
            return $this->name;
        }

        $uploaded = trim(preg_replace('/\s+/u', ' ', $this->rawValue('first_name').' '.$this->rawValue('last_name')) ?? '');

        return $uploaded !== '' ? $uploaded : $this->rawValue(ImportColumns::FULL_NAME);
    }
}
