<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum ActivityType: string
{
    use HasOptions;

    case Lesson = 'lesson';
    case Video = 'video';
    case MiniGame = 'mini_game';
    case Reading = 'reading';
    case Practice = 'practice';

    /**
     * Get the human-readable label of the activity type.
     */
    public function label(): string
    {
        return match ($this) {
            self::Lesson => 'Lesson',
            self::Video => 'Video',
            self::MiniGame => 'Mini-game',
            self::Reading => 'Reading',
            self::Practice => 'Practice',
        };
    }

    /**
     * Get the palette key that represents the activity type.
     */
    public function color(): string
    {
        return match ($this) {
            self::Lesson => 'leaf',
            self::Video => 'sky',
            self::MiniGame => 'grape',
            self::Reading => 'teal',
            self::Practice => 'carrot',
        };
    }

    /**
     * Get the icon name that represents the activity type.
     */
    public function icon(): string
    {
        return match ($this) {
            self::Lesson => 'book-open',
            self::Video => 'circle-play',
            self::MiniGame => 'gamepad-2',
            self::Reading => 'scroll-text',
            self::Practice => 'target',
        };
    }
}
