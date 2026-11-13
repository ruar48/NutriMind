<?php

namespace App\Services\StudentImport;

use App\Enums\GradeLevel;
use App\Enums\GuardianRelationship;
use App\Enums\Sex;
use App\Models\Classroom;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

/**
 * Applies the per-row rules of the learner CSV import (format rules only; database checks belong to the previewer).
 */
class RowValidator
{
    /**
     * The longest first or last name.
     */
    public const NAME_PART_MAX_LENGTH = 60;

    /**
     * The longest full name (files with a single name column).
     */
    public const NAME_MAX_LENGTH = 120;

    /**
     * The longest username.
     */
    public const USERNAME_MAX_LENGTH = 30;

    /**
     * The longest guardian name.
     */
    public const GUARDIAN_NAME_MAX_LENGTH = 120;

    /**
     * The longest guardian email address (the users.email column).
     */
    public const GUARDIAN_EMAIL_MAX_LENGTH = 255;

    /**
     * Validate and normalize one data row.
     *
     * @param  array<string, string>  $values  The trimmed cells by column.
     * @param  Classroom|null  $classroom  The classroom being imported into; it supplies the grade of rows without one
     *                                     and every row's grade must match it.
     * @param  bool  $usesNameParts  Whether the file names learners with first_name and last_name (else with name).
     */
    public function validate(int $number, array $values, ?Classroom $classroom, bool $usesNameParts): ValidatedRow
    {
        $errors = [];

        [$name, $firstName, $lastName] = $usesNameParts
            ? $this->nameParts($values, $errors)
            : [$this->fullName($values, $errors), null, null];

        return new ValidatedRow(
            number: $number,
            raw: $values,
            name: $name,
            firstName: $firstName,
            lastName: $lastName,
            lrn: $this->lrn($values['lrn'] ?? '', $errors),
            grade: $this->grade($values['grade_level'] ?? '', $classroom, $errors),
            sex: $this->sex($values['sex'] ?? '', $errors),
            birthdate: $this->birthdate($values['birthdate'] ?? '', $errors),
            username: $this->username($values['username'] ?? '', $errors),
            guardianName: $this->guardianName($values['guardian_name'] ?? '', $errors),
            guardianEmail: $this->guardianEmail($values['guardian_email'] ?? '', $errors),
            guardianRelationship: $this->guardianRelationship($values['guardian_relationship'] ?? '', $errors),
            errors: $errors,
        );
    }

    /**
     * Validate first_name and last_name; returns [full name, first name, last name] (nulls when invalid).
     *
     * @param  array<string, string>  $values
     * @param  list<string>  $errors
     * @return array{0: string|null, 1: string|null, 2: string|null}
     */
    private function nameParts(array $values, array &$errors): array
    {
        $firstName = $this->namePart(Str::squish($values['first_name'] ?? ''), 'First name', $errors);
        $lastName = $this->namePart(Str::squish($values['last_name'] ?? ''), 'Last name', $errors);

        if ($firstName === null || $lastName === null) {
            return [null, null, null];
        }

        return [$firstName.' '.$lastName, $firstName, $lastName];
    }

    /**
     * Validate one name part.
     *
     * @param  list<string>  $errors
     */
    private function namePart(string $value, string $label, array &$errors): ?string
    {
        if ($value === '') {
            $errors[] = $label.' is required.';

            return null;
        }

        if (mb_strlen($value) > self::NAME_PART_MAX_LENGTH) {
            $errors[] = $label.' must not be longer than '.self::NAME_PART_MAX_LENGTH.' characters.';

            return null;
        }

        return $value;
    }

    /**
     * Validate the single name column.
     *
     * @param  array<string, string>  $values
     * @param  list<string>  $errors
     */
    private function fullName(array $values, array &$errors): ?string
    {
        $name = Str::squish($values[ImportColumns::FULL_NAME] ?? '');

        if ($name === '') {
            $errors[] = 'Name is required.';

            return null;
        }

        if (mb_strlen($name) > self::NAME_MAX_LENGTH) {
            $errors[] = 'Name must not be longer than '.self::NAME_MAX_LENGTH.' characters.';

            return null;
        }

        return $name;
    }

    /**
     * Validate the LRN: blank, or exactly 12 digits.
     *
     * @param  list<string>  $errors
     */
    private function lrn(string $value, array &$errors): ?string
    {
        if ($value === '') {
            return null;
        }

        if (preg_match('/^\d{12}$/', $value) === 1) {
            return $value;
        }

        $errors[] = preg_match('/^\d+(\.\d+)?e\+?\d+$/i', $value) === 1
            ? 'LRN must be exactly 12 digits. Format the LRN column as Text in your spreadsheet so it keeps every digit.'
            : 'LRN must be exactly 12 digits.';

        return null;
    }

    /**
     * Validate the grade level ("5", "6", "Grade 5", "grade 6"); the classroom's grade fills a blank cell.
     *
     * @param  list<string>  $errors
     */
    private function grade(string $value, ?Classroom $classroom, array &$errors): ?GradeLevel
    {
        $classroomGrade = $classroom?->grade_level;

        if ($value === '') {
            if ($classroomGrade === null) {
                $errors[] = 'Grade level is required.';
            }

            return $classroomGrade;
        }

        if (preg_match('/^(?:grade\s*)?(\d+)$/i', $value, $match) !== 1 || ($grade = GradeLevel::tryFrom((int) $match[1])) === null) {
            $errors[] = 'Grade level must be 5 or 6.';

            return null;
        }

        if ($classroomGrade !== null && $grade !== $classroomGrade) {
            $errors[] = 'Grade level does not match this classroom ('.$classroomGrade->label().').';

            return null;
        }

        return $grade;
    }

    /**
     * Validate the sex: male, female, M or F in any letter case.
     *
     * @param  list<string>  $errors
     */
    private function sex(string $value, array &$errors): ?Sex
    {
        if ($value === '') {
            return null;
        }

        $sex = match (Str::lower($value)) {
            'm', 'male' => Sex::Male,
            'f', 'female' => Sex::Female,
            default => null,
        };

        if ($sex === null) {
            $errors[] = 'Sex must be male or female (M or F).';
        }

        return $sex;
    }

    /**
     * Validate the birthdate (Y-m-d or m/d/Y, a real date before today); returns it as Y-m-d.
     *
     * @param  list<string>  $errors
     */
    private function birthdate(string $value, array &$errors): ?string
    {
        if ($value === '') {
            return null;
        }

        $parts = match (true) {
            preg_match('/^(\d{4})-(\d{1,2})-(\d{1,2})$/', $value, $match) === 1 => [(int) $match[1], (int) $match[2], (int) $match[3]],
            preg_match('#^(\d{1,2})/(\d{1,2})/(\d{4})$#', $value, $match) === 1 => [(int) $match[3], (int) $match[1], (int) $match[2]],
            default => null,
        };

        if ($parts === null || ! checkdate($parts[1], $parts[2], $parts[0])) {
            $errors[] = 'Birthdate must be a date like 2015-06-15 or 06/15/2015.';

            return null;
        }

        $birthdate = CarbonImmutable::create($parts[0], $parts[1], $parts[2]);

        if (! $birthdate->lt(CarbonImmutable::today())) {
            $errors[] = 'Birthdate must be before today.';

            return null;
        }

        return $birthdate->toDateString();
    }

    /**
     * Validate the username: letters, numbers, dashes and underscores, at most 30 characters.
     *
     * @param  list<string>  $errors
     */
    private function username(string $value, array &$errors): ?string
    {
        if ($value === '') {
            return null;
        }

        if (preg_match('/^[A-Za-z0-9_-]+$/', $value) !== 1) {
            $errors[] = 'Username may only contain letters, numbers, dashes and underscores.';

            return null;
        }

        if (strlen($value) > self::USERNAME_MAX_LENGTH) {
            $errors[] = 'Username must not be longer than '.self::USERNAME_MAX_LENGTH.' characters.';

            return null;
        }

        return $value;
    }

    /**
     * Validate the guardian's name.
     *
     * @param  list<string>  $errors
     */
    private function guardianName(string $value, array &$errors): ?string
    {
        $name = Str::squish($value);

        if ($name === '') {
            return null;
        }

        if (mb_strlen($name) > self::GUARDIAN_NAME_MAX_LENGTH) {
            $errors[] = 'Guardian name must not be longer than '.self::GUARDIAN_NAME_MAX_LENGTH.' characters.';

            return null;
        }

        return $name;
    }

    /**
     * Validate the guardian's email address; returns it lowercased.
     *
     * @param  list<string>  $errors
     */
    private function guardianEmail(string $value, array &$errors): ?string
    {
        if ($value === '') {
            return null;
        }

        $email = Str::lower($value);

        if (strlen($email) > self::GUARDIAN_EMAIL_MAX_LENGTH || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $errors[] = 'Guardian email must be a valid email address.';

            return null;
        }

        return $email;
    }

    /**
     * Validate the guardian relationship (any letter case); a blank cell means Guardian.
     *
     * @param  list<string>  $errors
     */
    private function guardianRelationship(string $value, array &$errors): GuardianRelationship
    {
        if ($value === '') {
            return GuardianRelationship::Guardian;
        }

        $relationship = GuardianRelationship::tryFrom(Str::lower($value));

        if ($relationship === null) {
            $errors[] = 'Guardian relationship must be '.$this->relationshipList().'.';

            return GuardianRelationship::Guardian;
        }

        return $relationship;
    }

    /**
     * List the accepted relationships: "mother, father, … or other".
     */
    private function relationshipList(): string
    {
        $values = array_map(fn (GuardianRelationship $relationship): string => $relationship->value, GuardianRelationship::cases());
        $last = array_pop($values);

        return implode(', ', $values).' or '.$last;
    }
}
