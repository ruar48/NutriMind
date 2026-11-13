<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum ForumContentStatus: string
{
    use HasOptions;

    case Published = 'published';
    case Pending = 'pending';
    case Hidden = 'hidden';

    /**
     * Get the human-readable label of the status.
     */
    public function label(): string
    {
        return match ($this) {
            self::Published => 'Published',
            self::Pending => 'Pending approval',
            self::Hidden => 'Hidden',
        };
    }

    /**
     * Get the palette key that represents the status.
     */
    public function color(): string
    {
        return match ($this) {
            self::Published => 'leaf',
            self::Pending => 'sun',
            self::Hidden => 'slate',
        };
    }

    /**
     * Get the icon name that represents the status.
     */
    public function icon(): string
    {
        return match ($this) {
            self::Published => 'circle-check',
            self::Pending => 'clock',
            self::Hidden => 'eye-off',
        };
    }
}
