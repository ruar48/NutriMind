<?php

namespace App\Services\Forum;

/**
 * The moderator tools of a forum thread (all cases) and of a reply (hide and unhide only).
 */
enum ModerationAction: string
{
    case Pin = 'pin';
    case Unpin = 'unpin';
    case Lock = 'lock';
    case Unlock = 'unlock';
    case Hide = 'hide';
    case Unhide = 'unhide';

    /**
     * Get the actions a moderator can take on a reply.
     *
     * @return list<self>
     */
    public static function forReplies(): array
    {
        return [self::Hide, self::Unhide];
    }

    /**
     * Get the label of the button that applies the action.
     */
    public function label(): string
    {
        return match ($this) {
            self::Pin => 'Pin to top',
            self::Unpin => 'Unpin',
            self::Lock => 'Lock replies',
            self::Unlock => 'Unlock replies',
            self::Hide => 'Hide',
            self::Unhide => 'Show again',
        };
    }

    /**
     * Get the icon of the button that applies the action.
     */
    public function icon(): string
    {
        return match ($this) {
            self::Pin => 'pin',
            self::Unpin => 'pin-off',
            self::Lock => 'lock',
            self::Unlock => 'lock-open',
            self::Hide => 'eye-off',
            self::Unhide => 'eye',
        };
    }
}
