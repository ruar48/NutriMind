<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum GradeLevel: int
{
    use HasOptions;

    case Five = 5;
    case Six = 6;

    /**
     * Get the human-readable label of the grade level.
     */
    public function label(): string
    {
        return "Grade {$this->value}";
    }

    /**
     * Get the palette key that represents the grade level.
     */
    public function color(): string
    {
        return match ($this) {
            self::Five => 'sky',
            self::Six => 'grape',
        };
    }

    /**
     * Get the icon name that represents the grade level.
     */
    public function icon(): string
    {
        return 'graduation-cap';
    }
}
