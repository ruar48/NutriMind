<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum LessonPlanStatus: string
{
    use HasOptions;

    case Planned = 'planned';
    case Ongoing = 'ongoing';
    case Completed = 'completed';

    /**
     * Get the human-readable label of the status.
     */
    public function label(): string
    {
        return match ($this) {
            self::Planned => 'Planned',
            self::Ongoing => 'Ongoing',
            self::Completed => 'Completed',
        };
    }

    /**
     * Get the palette key that represents the status.
     */
    public function color(): string
    {
        return match ($this) {
            self::Planned => 'slate',
            self::Ongoing => 'sky',
            self::Completed => 'leaf',
        };
    }

    /**
     * Get the icon name that represents the status.
     */
    public function icon(): string
    {
        return match ($this) {
            self::Planned => 'calendar',
            self::Ongoing => 'timer',
            self::Completed => 'circle-check',
        };
    }
}
