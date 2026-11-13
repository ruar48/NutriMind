<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum FeedbackType: string
{
    use HasOptions;

    case Praise = 'praise';
    case Encouragement = 'encouragement';
    case Guidance = 'guidance';
    case Concern = 'concern';

    /**
     * Get the human-readable label of the feedback type.
     */
    public function label(): string
    {
        return match ($this) {
            self::Praise => 'Praise',
            self::Encouragement => 'Encouragement',
            self::Guidance => 'Guidance',
            self::Concern => 'Concern',
        };
    }

    /**
     * Get the palette key that represents the feedback type.
     */
    public function color(): string
    {
        return match ($this) {
            self::Praise => 'leaf',
            self::Encouragement => 'sky',
            self::Guidance => 'grape',
            self::Concern => 'carrot',
        };
    }

    /**
     * Get the icon name that represents the feedback type.
     */
    public function icon(): string
    {
        return match ($this) {
            self::Praise => 'star',
            self::Encouragement => 'heart',
            self::Guidance => 'lightbulb',
            self::Concern => 'circle-alert',
        };
    }
}
