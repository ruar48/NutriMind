<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum Sex: string
{
    use HasOptions;

    case Male = 'male';
    case Female = 'female';

    /**
     * Get the human-readable label of the sex.
     */
    public function label(): string
    {
        return match ($this) {
            self::Male => 'Male',
            self::Female => 'Female',
        };
    }
}
