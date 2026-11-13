<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum QuestionType: string
{
    use HasOptions;

    case MultipleChoice = 'multiple_choice';
    case TrueFalse = 'true_false';
    case Identification = 'identification';

    /**
     * Get the human-readable label of the question type.
     */
    public function label(): string
    {
        return match ($this) {
            self::MultipleChoice => 'Multiple choice',
            self::TrueFalse => 'True or false',
            self::Identification => 'Identification',
        };
    }

    /**
     * Get a one-sentence explanation of how learners answer this type.
     */
    public function description(): string
    {
        return match ($this) {
            self::MultipleChoice => 'Learners pick the one correct answer from two to six choices.',
            self::TrueFalse => 'Learners decide whether a statement is true or false.',
            self::Identification => 'Learners type a short answer. Matching ignores letter case and extra spaces.',
        };
    }

    /**
     * Determine whether learners answer by picking one of the stored choices.
     */
    public function usesChoices(): bool
    {
        return match ($this) {
            self::MultipleChoice, self::TrueFalse => true,
            self::Identification => false,
        };
    }

    /**
     * Get the palette key that represents the question type.
     */
    public function color(): string
    {
        return match ($this) {
            self::MultipleChoice => 'sky',
            self::TrueFalse => 'grape',
            self::Identification => 'teal',
        };
    }

    /**
     * Get the icon name that represents the question type.
     */
    public function icon(): string
    {
        return match ($this) {
            self::MultipleChoice => 'list-checks',
            self::TrueFalse => 'check-check',
            self::Identification => 'pencil',
        };
    }
}
