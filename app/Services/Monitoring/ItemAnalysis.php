<?php

namespace App\Services\Monitoring;

use App\Models\QuizQuestion;

/**
 * The item analysis of one quiz question over the submitted attempts in scope.
 *
 * The difficulty index p is the share of attempts that answered the question correctly (an unanswered question counts
 * as not correct). The most-chosen wrong answer is the choice text for multiple choice and true/false questions and the
 * normalized typed text (lowercase, single spaces) for identification questions.
 */
final readonly class ItemAnalysis
{
    public function __construct(
        /** The question, in quiz order. */
        public QuizQuestion $question,
        /** The position of the question in the quiz (1-based). */
        public int $number,
        /** The number of submitted attempts in scope. */
        public int $responses,
        /** The number of those attempts that answered the question correctly. */
        public int $correct,
        /** The difficulty index p between 0 and 1, or null without attempts. */
        public ?float $difficultyIndex,
        /** The label of the difficulty index, or null without attempts. */
        public ?ItemDifficulty $difficulty,
        /** The most frequent wrong answer as display text, or null when nobody answered it wrongly. */
        public ?string $mostChosenWrongAnswer,
        /** How many attempts gave the most frequent wrong answer. */
        public int $mostChosenWrongCount,
    ) {}

    /**
     * Get the share of correct answers as a percentage with 1 decimal, or null without attempts.
     */
    public function percentCorrect(): ?float
    {
        return $this->difficultyIndex === null ? null : round($this->difficultyIndex * 100, 1);
    }
}
