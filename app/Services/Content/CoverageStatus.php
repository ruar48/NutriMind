<?php

namespace App\Services\Content;

use App\Enums\Concerns\HasOptions;

/**
 * How well the game content covers a competency that teachers plan to teach.
 */
enum CoverageStatus: string
{
    use HasOptions;

    case Covered = 'covered';
    case Partial = 'partial';
    case NotCovered = 'not_covered';

    /**
     * Determine the status from the content that matches a competency.
     *
     * Covered: a module teaches the competency AND it has a published activity or a quiz is aligned to the
     * lesson plans. Partial: a module or an aligned quiz exists, but not both conditions. Not covered: nothing.
     */
    public static function fromCounts(int $modules, int $publishedActivities, int $alignedQuizzes): self
    {
        return match (true) {
            $modules > 0 && ($publishedActivities > 0 || $alignedQuizzes > 0) => self::Covered,
            $modules > 0 || $alignedQuizzes > 0 => self::Partial,
            default => self::NotCovered,
        };
    }

    /**
     * Get the human-readable label of the status.
     */
    public function label(): string
    {
        return match ($this) {
            self::Covered => 'Covered',
            self::Partial => 'Partial',
            self::NotCovered => 'Not covered',
        };
    }

    /**
     * Get one sentence explaining the status.
     */
    public function description(): string
    {
        return match ($this) {
            self::Covered => 'A game module teaches this competency and has published activities or aligned quizzes.',
            self::Partial => 'Some content exists, but the module still needs published activities or an aligned quiz.',
            self::NotCovered => 'No game module or aligned quiz covers this competency yet.',
        };
    }

    /**
     * Get the palette key that represents the status (status palette: success, warning, danger).
     */
    public function color(): string
    {
        return match ($this) {
            self::Covered => 'leaf',
            self::Partial => 'sun',
            self::NotCovered => 'berry',
        };
    }

    /**
     * Get the icon name that represents the status.
     */
    public function icon(): string
    {
        return match ($this) {
            self::Covered => 'circle-check',
            self::Partial => 'circle-dashed',
            self::NotCovered => 'circle-x',
        };
    }
}
