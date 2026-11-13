<?php

namespace App\Services\Forum;

use App\Enums\ForumContentStatus;
use App\Models\ForumReply;
use App\Models\ForumReport;
use App\Models\ForumThread;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * The administrators' moderation of the parent forum: thread and reply tools, the reports queue and the approval
 * of pending threads.
 *
 * Hiding content (from a thread's tools or from the reports queue) also resolves the open reports about that
 * content with the "content_hidden" resolution, so the queue only lists what still needs a decision.
 */
class ForumModeration
{
    /**
     * The refusal shown when "show again" is used on a thread that still waits for approval.
     */
    public const PENDING_UNHIDE_MESSAGE = 'This thread is waiting for approval. Approve it from the moderation queue instead.';

    /**
     * The refusal shown when a report was resolved in the meantime.
     */
    public const REPORT_RESOLVED_MESSAGE = 'This report was already resolved.';

    /**
     * The refusal shown when a thread left the approval queue in the meantime.
     */
    public const ALREADY_REVIEWED_MESSAGE = 'This thread was already reviewed.';

    /**
     * Get the reason a moderator tool cannot be used on the thread, or null when it can.
     */
    public function threadRefusal(ForumThread $thread, ModerationAction $action): ?string
    {
        return $action === ModerationAction::Unhide && $thread->status === ForumContentStatus::Pending
            ? self::PENDING_UNHIDE_MESSAGE
            : null;
    }

    /**
     * Apply a moderator tool to the thread and get the confirmation message.
     */
    public function moderateThread(ForumThread $thread, ModerationAction $action, User $moderator): string
    {
        DB::transaction(function () use ($thread, $action, $moderator): void {
            $thread->update(match ($action) {
                ModerationAction::Pin => ['is_pinned' => true],
                ModerationAction::Unpin => ['is_pinned' => false],
                ModerationAction::Lock => ['is_locked' => true],
                ModerationAction::Unlock => ['is_locked' => false],
                ModerationAction::Hide => ['status' => ForumContentStatus::Hidden],
                ModerationAction::Unhide => ['status' => ForumContentStatus::Published],
            });

            if ($action === ModerationAction::Hide) {
                $this->resolveOpenReports($thread, null, $moderator);
            }
        });

        $title = $this->quotedTitle($thread);

        return match ($action) {
            ModerationAction::Pin => $title.' is pinned to the top of the forum.',
            ModerationAction::Unpin => $title.' is no longer pinned.',
            ModerationAction::Lock => $title.' is locked. Only administrators can reply now.',
            ModerationAction::Unlock => $title.' is open for replies again.',
            ModerationAction::Hide => $title.' is hidden from the forum.',
            ModerationAction::Unhide => $title.' is visible in the forum again.',
        };
    }

    /**
     * Hide the reply or show it again, and get the confirmation message.
     */
    public function moderateReply(ForumReply $reply, ModerationAction $action, User $moderator): string
    {
        $hide = $action === ModerationAction::Hide;

        DB::transaction(function () use ($reply, $hide, $moderator): void {
            $reply->update(['status' => $hide ? ForumContentStatus::Hidden : ForumContentStatus::Published]);

            if ($hide) {
                $this->resolveOpenReports($reply->thread, $reply, $moderator);
            }
        });

        return $hide
            ? 'The reply is hidden. Readers see a moderator notice instead.'
            : 'The reply is visible again.';
    }

    /**
     * Keep the reported content and close the report.
     */
    public function dismiss(ForumReport $report, User $moderator): void
    {
        $report->update([
            'resolved_at' => now(),
            'resolved_by' => $moderator->id,
            'resolution' => ForumReport::RESOLUTION_DISMISSED,
        ]);
    }

    /**
     * Hide the reported thread or reply and close every open report about it.
     *
     * @return int the number of reports closed
     */
    public function hideReportedContent(ForumReport $report, User $moderator): int
    {
        return DB::transaction(function () use ($report, $moderator): int {
            $reply = $report->reply;

            ($reply ?? $report->thread)->update(['status' => ForumContentStatus::Hidden]);

            return $this->resolveOpenReports($report->thread, $reply, $moderator);
        });
    }

    /**
     * Publish a pending thread; it counts as active from now on.
     */
    public function approve(ForumThread $thread): void
    {
        $thread->update([
            'status' => ForumContentStatus::Published,
            'last_activity_at' => now(),
        ]);
    }

    /**
     * Keep a pending thread out of the forum.
     */
    public function reject(ForumThread $thread): void
    {
        $thread->update(['status' => ForumContentStatus::Hidden]);
    }

    /**
     * Get the thread title in quotes, shortened for a notification.
     */
    public function quotedTitle(ForumThread $thread): string
    {
        return '“'.Str::limit($thread->title, 60).'”';
    }

    /**
     * Resolve the open reports about the thread itself (no reply) or about one of its replies.
     */
    private function resolveOpenReports(ForumThread $thread, ?ForumReply $reply, User $moderator): int
    {
        return ForumReport::query()
            ->unresolved()
            ->where('forum_thread_id', $thread->id)
            ->when(
                $reply,
                fn (Builder $query, ForumReply $reply) => $query->where('forum_reply_id', $reply->id),
                fn (Builder $query) => $query->whereNull('forum_reply_id'),
            )
            ->update([
                'resolved_at' => now(),
                'resolved_by' => $moderator->id,
                'resolution' => ForumReport::RESOLUTION_CONTENT_HIDDEN,
            ]);
    }
}
