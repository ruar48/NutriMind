<?php

namespace App\Services\Content;

/**
 * The icons and colors a reward badge can wear in the game.
 */
final class RewardAppearance
{
    /**
     * The curated reward icons, in picker order (every name is in the design-system icon set).
     *
     * @var list<string>
     */
    public const array ICONS = [
        'award', 'trophy', 'medal', 'crown', 'star', 'sparkles', 'badge-check', 'gift', 'party-popper', 'flame',
        'zap', 'rocket', 'target', 'flag', 'compass', 'map', 'search', 'backpack', 'heart', 'heart-pulse', 'hand-heart',
        'smile', 'apple', 'carrot', 'salad', 'leaf', 'sprout', 'cherry', 'citrus', 'banana', 'wheat', 'egg', 'fish',
        'milk', 'glass-water', 'droplet', 'utensils', 'cooking-pot', 'brain', 'lightbulb', 'book-open', 'gamepad-2',
        'puzzle', 'shield-check',
    ];

    /**
     * The palette keys a reward can use, in picker order.
     *
     * @var list<string>
     */
    public const array COLORS = ['leaf', 'carrot', 'sun', 'berry', 'grape', 'sky', 'teal', 'lime', 'pink', 'indigo', 'slate'];
}
