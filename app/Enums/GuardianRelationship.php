<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum GuardianRelationship: string
{
    use HasOptions;

    case Mother = 'mother';
    case Father = 'father';
    case Guardian = 'guardian';
    case Grandparent = 'grandparent';
    case Sibling = 'sibling';
    case Relative = 'relative';
    case Other = 'other';

    /**
     * Get the human-readable label of the relationship.
     */
    public function label(): string
    {
        return match ($this) {
            self::Mother => 'Mother',
            self::Father => 'Father',
            self::Guardian => 'Guardian',
            self::Grandparent => 'Grandparent',
            self::Sibling => 'Sibling',
            self::Relative => 'Relative',
            self::Other => 'Other',
        };
    }
}
