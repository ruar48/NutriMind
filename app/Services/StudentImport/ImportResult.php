<?php

namespace App\Services\StudentImport;

use App\Enums\UserRole;
use App\Models\Classroom;
use Illuminate\Support\Str;

/**
 * What a committed learner import did, and the generated sign-in details to hand out once.
 */
final readonly class ImportResult
{
    /**
     * The headings of the one-time sign-in details CSV.
     *
     * @var list<string>
     */
    public const CREDENTIALS_HEADINGS = ['Name', 'Account', 'Username or email', 'Password', 'Signs in to'];

    /**
     * The credentials hold the sign-in details of every new account in row order; a parent's "username" is the
     * email address they sign in with.
     *
     * @param  int  $created  New learner accounts.
     * @param  int  $enrolled  Existing learners enrolled in the classroom.
     * @param  int  $skipped  Rows skipped.
     * @param  int  $failed  Rows not imported because of errors.
     * @param  int  $parentsCreated  New parent accounts.
     * @param  int  $guardiansLinked  Parent–learner links added.
     * @param  list<array{name: string, username: string, password: string, role: UserRole}>  $credentials
     */
    public function __construct(
        public int $created,
        public int $enrolled,
        public int $skipped,
        public int $failed,
        public int $parentsCreated,
        public int $guardiansLinked,
        public array $credentials,
    ) {}

    /**
     * Get the number of learners the import created or enrolled.
     */
    public function imported(): int
    {
        return $this->created + $this->enrolled;
    }

    /**
     * Get the sign-in details in the shape of the credentials alert (name, username, password).
     *
     * @return list<array{name: string, username: string, password: string}>
     */
    public function alertCredentials(): array
    {
        return array_map(fn (array $account): array => [
            'name' => $account['name'],
            'username' => $account['username'],
            'password' => $account['password'],
        ], $this->credentials);
    }

    /**
     * Turn stored sign-in details into the rows of the sign-in details CSV, in CREDENTIALS_HEADINGS order.
     *
     * @param  list<array{name: string, username: string, password: string, role: UserRole}>  $credentials
     * @return list<list<string>>
     */
    public static function credentialsCsvRows(array $credentials): array
    {
        return array_map(fn (array $account): array => [
            $account['name'],
            $account['role']->label(),
            $account['username'],
            $account['password'],
            $account['role']->portalLabel(),
        ], $credentials);
    }

    /**
     * Get the counts the import page shows after the import (flashed to the session).
     *
     * @return array{created: int, enrolled: int, skipped: int, failed: int, parentsCreated: int, guardiansLinked: int, accounts: int, classroom: string|null}
     */
    public function summary(?Classroom $classroom): array
    {
        return [
            'created' => $this->created,
            'enrolled' => $this->enrolled,
            'skipped' => $this->skipped,
            'failed' => $this->failed,
            'parentsCreated' => $this->parentsCreated,
            'guardiansLinked' => $this->guardiansLinked,
            'accounts' => count($this->credentials),
            'classroom' => $classroom?->name,
        ];
    }

    /**
     * Get the one-line success message: "Imported 15 learners into Health 5 – Sampaguita: 12 new accounts and 3 existing learners enrolled. Created 4 parent accounts."
     */
    public function statusMessage(?Classroom $classroom): string
    {
        $parts = [];

        if ($this->created > 0) {
            $parts[] = $this->created.' new '.Str::plural('account', $this->created);
        }

        if ($this->enrolled > 0) {
            $parts[] = $this->enrolled.' existing '.Str::plural('learner', $this->enrolled).' enrolled';
        }

        $message = 'Imported '.$this->imported().' '.Str::plural('learner', $this->imported())
            .($classroom !== null ? ' into '.$classroom->name : '')
            .': '.implode(' and ', $parts).'.';

        if ($this->parentsCreated > 0) {
            $message .= ' Created '.$this->parentsCreated.' parent '.Str::plural('account', $this->parentsCreated).'.';
        }

        return $message;
    }
}
