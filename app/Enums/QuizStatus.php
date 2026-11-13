<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum QuizStatus: string
{
    use HasOptions;

    case Draft = 'draft';
    case Published = 'published';
    case Closed = 'closed';

    /**
     * Get the human-readable label of the status.
     */
    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Published => 'Published',
            self::Closed => 'Closed',
        };
    }

    /**
     * Get the palette key that represents the status.
     */
    public function color(): string
    {
        return match ($this) {
            self::Draft => 'sun',
            self::Published => 'leaf',
            self::Closed => 'slate',
        };
    }

    /**
     * Get the icon name that represents the status.
     */
    public function icon(): string
    {
        return match ($this) {
            self::Draft => 'square-pen',
            self::Published => 'circle-check',
            self::Closed => 'lock',
        };
    }
}
