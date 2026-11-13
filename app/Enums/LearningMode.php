<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum LearningMode: string
{
    use HasOptions;

    case Automatic = 'automatic';
    case Manual = 'manual';
    case LearnerChoice = 'learner_choice';

    /**
     * Get the human-readable label of the learning mode.
     */
    public function label(): string
    {
        return match ($this) {
            self::Automatic => 'Automatic path',
            self::Manual => 'Manual selection',
            self::LearnerChoice => "Learner's choice",
        };
    }

    /**
     * Get a one-sentence explanation of how the game behaves in this mode.
     */
    public function description(): string
    {
        return match ($this) {
            self::Automatic => 'NutriMind unlocks worlds, missions and activities in order as each learner progresses.',
            self::Manual => 'You choose which learning modules and activities are open to the class.',
            self::LearnerChoice => 'Learners pick any unlocked world or activity they want to play next.',
        };
    }

    /**
     * Get the palette key that represents the learning mode.
     */
    public function color(): string
    {
        return match ($this) {
            self::Automatic => 'leaf',
            self::Manual => 'sky',
            self::LearnerChoice => 'grape',
        };
    }

    /**
     * Get the icon name that represents the learning mode.
     */
    public function icon(): string
    {
        return match ($this) {
            self::Automatic => 'compass',
            self::Manual => 'sliders-horizontal',
            self::LearnerChoice => 'mouse-pointer-click',
        };
    }
}
