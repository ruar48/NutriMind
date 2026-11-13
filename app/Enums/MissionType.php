<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum MissionType: string
{
    use HasOptions;

    case Main = 'main';
    case Daily = 'daily';
    case Weekly = 'weekly';
    case Challenge = 'challenge';

    /**
     * Get the human-readable label of the mission type.
     */
    public function label(): string
    {
        return match ($this) {
            self::Main => 'Story mission',
            self::Daily => 'Daily',
            self::Weekly => 'Weekly',
            self::Challenge => 'Challenge',
        };
    }

    /**
     * Get the palette key that represents the mission type.
     */
    public function color(): string
    {
        return match ($this) {
            self::Main => 'leaf',
            self::Daily => 'sky',
            self::Weekly => 'grape',
            self::Challenge => 'carrot',
        };
    }

    /**
     * Get the icon name that represents the mission type.
     */
    public function icon(): string
    {
        return match ($this) {
            self::Main => 'map',
            self::Daily => 'calendar-check',
            self::Weekly => 'calendar-days',
            self::Challenge => 'zap',
        };
    }
}
