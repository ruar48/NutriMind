<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum ProgressStatus: string
{
    use HasOptions;

    case NotStarted = 'not_started';
    case InProgress = 'in_progress';
    case Completed = 'completed';

    /**
     * Get the human-readable label of the status.
     */
    public function label(): string
    {
        return match ($this) {
            self::NotStarted => 'Not started',
            self::InProgress => 'In progress',
            self::Completed => 'Completed',
        };
    }

    /**
     * Get the palette key that represents the status.
     */
    public function color(): string
    {
        return match ($this) {
            self::NotStarted => 'slate',
            self::InProgress => 'sky',
            self::Completed => 'leaf',
        };
    }

    /**
     * Get the icon name that represents the status.
     */
    public function icon(): string
    {
        return match ($this) {
            self::NotStarted => 'clock',
            self::InProgress => 'timer',
            self::Completed => 'circle-check',
        };
    }
}
