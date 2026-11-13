<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum PerformanceBand: string
{
    use HasOptions;

    case Outstanding = 'outstanding';
    case VerySatisfactory = 'very_satisfactory';
    case Satisfactory = 'satisfactory';
    case FairlySatisfactory = 'fairly_satisfactory';
    case DidNotMeetExpectations = 'did_not_meet_expectations';
    case NoData = 'no_data';

    /**
     * Resolve the DepEd descriptor band for a percentage score (null when there is no score yet).
     */
    public static function fromScore(?float $percentage): self
    {
        return match (true) {
            $percentage === null => self::NoData,
            $percentage >= 90 => self::Outstanding,
            $percentage >= 85 => self::VerySatisfactory,
            $percentage >= 80 => self::Satisfactory,
            $percentage >= 75 => self::FairlySatisfactory,
            default => self::DidNotMeetExpectations,
        };
    }

    /**
     * Get the DepEd descriptor of the band.
     */
    public function label(): string
    {
        return match ($this) {
            self::Outstanding => 'Outstanding',
            self::VerySatisfactory => 'Very Satisfactory',
            self::Satisfactory => 'Satisfactory',
            self::FairlySatisfactory => 'Fairly Satisfactory',
            self::DidNotMeetExpectations => 'Did Not Meet Expectations',
            self::NoData => 'No data',
        };
    }

    /**
     * Get the abbreviated descriptor for compact cells and chart labels.
     */
    public function shortLabel(): string
    {
        return match ($this) {
            self::Outstanding => 'O',
            self::VerySatisfactory => 'VS',
            self::Satisfactory => 'S',
            self::FairlySatisfactory => 'FS',
            self::DidNotMeetExpectations => 'DNME',
            self::NoData => '—',
        };
    }

    /**
     * Get the palette key that represents the band.
     */
    public function color(): string
    {
        return match ($this) {
            self::Outstanding => 'leaf',
            self::VerySatisfactory => 'teal',
            self::Satisfactory => 'sky',
            self::FairlySatisfactory => 'sun',
            self::DidNotMeetExpectations => 'berry',
            self::NoData => 'slate',
        };
    }

    /**
     * Get the icon name that represents the band.
     */
    public function icon(): string
    {
        return match ($this) {
            self::Outstanding => 'crown',
            self::VerySatisfactory => 'star',
            self::Satisfactory => 'badge-check',
            self::FairlySatisfactory => 'trending-up',
            self::DidNotMeetExpectations => 'triangle-alert',
            self::NoData => 'minus',
        };
    }

    /**
     * Determine whether a learner in this band needs the teacher's attention.
     */
    public function needsAttention(): bool
    {
        return $this === self::DidNotMeetExpectations;
    }
}
