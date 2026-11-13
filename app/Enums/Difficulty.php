<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum Difficulty: string
{
    use HasOptions;

    case Easy = 'easy';
    case Medium = 'medium';
    case Hard = 'hard';

    /**
     * Get the human-readable label of the difficulty.
     */
    public function label(): string
    {
        return match ($this) {
            self::Easy => 'Easy',
            self::Medium => 'Medium',
            self::Hard => 'Hard',
        };
    }

    /**
     * Get the palette key that represents the difficulty.
     */
    public function color(): string
    {
        return match ($this) {
            self::Easy => 'leaf',
            self::Medium => 'sun',
            self::Hard => 'berry',
        };
    }

    /**
     * Get the icon name that represents the difficulty.
     */
    public function icon(): string
    {
        return match ($this) {
            self::Easy => 'sprout',
            self::Medium => 'trending-up',
            self::Hard => 'flame',
        };
    }
}
