<?php

namespace App\Services\Monitoring;

/**
 * The difficulty label of a quiz question from its difficulty index p (the share of attempts that answered it
 * correctly): Easy when p ≥ 0.75, Moderate when 0.40 ≤ p < 0.75, Difficult when p < 0.40.
 */
enum ItemDifficulty: string
{
    case Easy = 'easy';
    case Moderate = 'moderate';
    case Difficult = 'difficult';

    /**
     * The lowest difficulty index labelled Easy.
     */
    public const EASY_FROM = 0.75;

    /**
     * The lowest difficulty index labelled Moderate.
     */
    public const MODERATE_FROM = 0.40;

    /**
     * Resolve the label of a difficulty index between 0 and 1.
     */
    public static function fromIndex(float $index): self
    {
        return match (true) {
            $index >= self::EASY_FROM => self::Easy,
            $index >= self::MODERATE_FROM => self::Moderate,
            default => self::Difficult,
        };
    }

    /**
     * Get the human-readable label.
     */
    public function label(): string
    {
        return match ($this) {
            self::Easy => 'Easy',
            self::Moderate => 'Moderate',
            self::Difficult => 'Difficult',
        };
    }

    /**
     * Get the rule that defines the label.
     */
    public function description(): string
    {
        return match ($this) {
            self::Easy => '75% or more answered correctly',
            self::Moderate => '40–74% answered correctly',
            self::Difficult => 'Fewer than 40% answered correctly',
        };
    }

    /**
     * Get the palette key that represents the label.
     */
    public function color(): string
    {
        return match ($this) {
            self::Easy => 'leaf',
            self::Moderate => 'sun',
            self::Difficult => 'berry',
        };
    }

    /**
     * Get the icon name that represents the label.
     */
    public function icon(): string
    {
        return match ($this) {
            self::Easy => 'circle-check',
            self::Moderate => 'gauge',
            self::Difficult => 'triangle-alert',
        };
    }
}
