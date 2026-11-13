<?php

namespace App\Services\Monitoring;

use App\Enums\FeedbackType;

/**
 * A ready-to-send feedback message suggested from a learner's results.
 */
final readonly class FeedbackSuggestion
{
    public function __construct(
        /** The feedback type the message fits. */
        public FeedbackType $type,
        /** A short heading for the teacher. */
        public string $title,
        /** Why the suggestion is offered, e.g. "Average 92.5% · Outstanding". */
        public string $reason,
        /** The message, addressed to the learner (at most 1,000 characters). */
        public string $message,
    ) {}
}
