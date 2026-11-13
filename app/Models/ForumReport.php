<?php

namespace App\Models;

use App\Enums\ForumReportReason;
use Database\Factories\ForumReportFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['forum_thread_id', 'forum_reply_id', 'reason', 'details', 'resolved_at', 'resolved_by', 'resolution'])]
class ForumReport extends Model
{
    /** @use HasFactory<ForumReportFactory> */
    use HasFactory;

    /**
     * The resolution recorded when a moderator keeps the reported content.
     */
    public const RESOLUTION_DISMISSED = 'dismissed';

    /**
     * The resolution recorded when a moderator hides the reported content.
     */
    public const RESOLUTION_CONTENT_HIDDEN = 'content_hidden';

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'reason' => ForumReportReason::class,
            'resolved_at' => 'datetime',
        ];
    }

    /**
     * Get the reported thread (or the thread of the reported reply).
     *
     * @return BelongsTo<ForumThread, $this>
     */
    public function thread(): BelongsTo
    {
        return $this->belongsTo(ForumThread::class, 'forum_thread_id');
    }

    /**
     * Get the reported reply, when the report is about a reply.
     *
     * @return BelongsTo<ForumReply, $this>
     */
    public function reply(): BelongsTo
    {
        return $this->belongsTo(ForumReply::class, 'forum_reply_id');
    }

    /**
     * Get the user who filed the report.
     *
     * @return BelongsTo<User, $this>
     */
    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reporter_id');
    }

    /**
     * Get the administrator who resolved the report.
     *
     * @return BelongsTo<User, $this>
     */
    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    /**
     * Scope the query to reports no moderator has resolved yet.
     *
     * @param  Builder<ForumReport>  $query
     */
    #[Scope]
    protected function unresolved(Builder $query): void
    {
        $query->whereNull($query->qualifyColumn('resolved_at'));
    }
}
