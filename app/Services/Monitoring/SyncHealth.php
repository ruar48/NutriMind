<?php

namespace App\Services\Monitoring;

use App\Services\LearnerProgressSummary;
use Carbon\CarbonInterface;

/**
 * The offline-sync warning of the teacher's learner lists.
 *
 * A learner needs a look when the game never synced their progress, or when the last sync is more than
 * LearnerProgressSummary::SYNC_OVERDUE_AFTER_DAYS (7) days old (exactly 7 days is still fine).
 */
final class SyncHealth
{
    /**
     * Determine whether the last game sync is missing or older than the allowed number of days.
     */
    public static function needsAttention(?CarbonInterface $lastSyncedAt): bool
    {
        return $lastSyncedAt === null
            || $lastSyncedAt->lt(now()->subDays(LearnerProgressSummary::SYNC_OVERDUE_AFTER_DAYS));
    }
}
