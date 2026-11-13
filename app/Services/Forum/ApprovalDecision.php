<?php

namespace App\Services\Forum;

/**
 * How a moderator reviews a thread that waits for approval.
 */
enum ApprovalDecision: string
{
    case Approve = 'approve';
    case Reject = 'reject';

    /**
     * Get the label of the button that applies the decision.
     */
    public function label(): string
    {
        return match ($this) {
            self::Approve => 'Approve',
            self::Reject => 'Reject',
        };
    }
}
