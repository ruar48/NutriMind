<?php

namespace App\Services\Forum;

/**
 * How a moderator resolves a forum report.
 */
enum ReportDecision: string
{
    case Dismiss = 'dismiss';
    case HideContent = 'hide';

    /**
     * Get the label of the button that applies the decision.
     */
    public function label(): string
    {
        return match ($this) {
            self::Dismiss => 'Dismiss',
            self::HideContent => 'Hide content',
        };
    }
}
