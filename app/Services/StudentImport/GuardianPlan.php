<?php

namespace App\Services\StudentImport;

use App\Enums\GuardianRelationship;
use App\Models\User;

/**
 * How one row's guardian columns are applied: create a parent account, or link an existing or just-created one.
 */
final readonly class GuardianPlan
{
    /**
     * @param  string  $email  The guardian's email address, lowercased.
     * @param  string|null  $name  The name of the parent account to create (CreateAccount only).
     * @param  User|null  $parent  The existing parent account (LinkExisting and AlreadyLinked only).
     * @param  int|null  $sourceRow  The row that creates the parent account (LinkCreated only).
     */
    public function __construct(
        public GuardianAction $action,
        public string $email,
        public GuardianRelationship $relationship,
        public ?string $name = null,
        public ?User $parent = null,
        public ?int $sourceRow = null,
    ) {}

    /**
     * Get the parent's name to show (the new account's name, or the existing account's).
     */
    public function displayName(): ?string
    {
        return $this->parent->name ?? $this->name;
    }
}
