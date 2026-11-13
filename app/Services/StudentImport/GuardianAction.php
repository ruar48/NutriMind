<?php

namespace App\Services\StudentImport;

/**
 * What the importer does with the guardian columns of one row.
 */
enum GuardianAction: string
{
    case CreateAccount = 'create_account';
    case LinkCreated = 'link_created';
    case LinkExisting = 'link_existing';
    case AlreadyLinked = 'already_linked';

    /**
     * Get the human-readable label of the action.
     */
    public function label(): string
    {
        return match ($this) {
            self::CreateAccount => 'New parent account',
            self::LinkCreated => 'New parent account',
            self::LinkExisting => 'Existing parent',
            self::AlreadyLinked => 'Already linked',
        };
    }

    /**
     * Get the palette key that represents the action.
     */
    public function color(): string
    {
        return match ($this) {
            self::CreateAccount, self::LinkCreated => 'carrot',
            self::LinkExisting => 'sky',
            self::AlreadyLinked => 'slate',
        };
    }

    /**
     * Get the icon name that represents the action.
     */
    public function icon(): string
    {
        return match ($this) {
            self::CreateAccount, self::LinkCreated => 'user-plus',
            self::LinkExisting => 'link',
            self::AlreadyLinked => 'check',
        };
    }

    /**
     * Determine whether the action adds a parent–learner link.
     */
    public function createsLink(): bool
    {
        return $this !== self::AlreadyLinked;
    }
}
