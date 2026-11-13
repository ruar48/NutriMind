<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum RewardType: string
{
    use HasOptions;

    case Badge = 'badge';
    case Trophy = 'trophy';
    case AvatarItem = 'avatar_item';

    /**
     * Get the human-readable label of the reward type.
     */
    public function label(): string
    {
        return match ($this) {
            self::Badge => 'Badge',
            self::Trophy => 'Trophy',
            self::AvatarItem => 'Avatar item',
        };
    }

    /**
     * Get the palette key that represents the reward type.
     */
    public function color(): string
    {
        return match ($this) {
            self::Badge => 'sun',
            self::Trophy => 'carrot',
            self::AvatarItem => 'grape',
        };
    }

    /**
     * Get the icon name that represents the reward type.
     */
    public function icon(): string
    {
        return match ($this) {
            self::Badge => 'award',
            self::Trophy => 'trophy',
            self::AvatarItem => 'sparkles',
        };
    }
}
