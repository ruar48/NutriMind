<?php

namespace App\Http\Controllers\Forum;

use App\Http\Controllers\Controller;
use App\Http\Requests\Forum\StoreForumReportRequest;
use App\Models\ForumThread;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Attributes\Controllers\Authorize;
use Illuminate\Support\Facades\Gate;

class ReportController extends Controller
{
    /**
     * The refusal shown when the reporter already has an open report about the same post.
     */
    public const ALREADY_REPORTED_MESSAGE = 'You already reported this.';

    /**
     * Report a thread or one of its replies to the moderators.
     *
     * The reported post comes from the request body, so it is authorized here: posts the reporter cannot open are
     * reported as missing, and nobody can report their own post.
     */
    #[Authorize('viewAny', ForumThread::class)]
    public function store(StoreForumReportRequest $request): RedirectResponse
    {
        $thread = ForumThread::query()->findOrFail($request->integer('forum_thread_id'));
        $reply = $request->filled('forum_reply_id')
            ? $thread->replies()->findOrFail($request->integer('forum_reply_id'))->setRelation('thread', $thread)
            : null;

        Gate::authorize('report', $reply ?? $thread);

        $reporter = $request->user();
        $back = $reply === null ? route('forum.threads.show', $thread) : route('forum.threads.show', ['thread' => $thread, 'reply' => $reply->id]);

        $alreadyReported = $reporter->forumReports()
            ->unresolved()
            ->where('forum_thread_id', $thread->id)
            ->when(
                $reply,
                fn (Builder $query) => $query->where('forum_reply_id', $reply->id),
                fn (Builder $query) => $query->whereNull('forum_reply_id'),
            )
            ->exists();

        if ($alreadyReported) {
            return redirect()->back(fallback: $back)->with('error', self::ALREADY_REPORTED_MESSAGE);
        }

        $reporter->forumReports()->create([
            'forum_thread_id' => $thread->id,
            'forum_reply_id' => $reply?->id,
            'reason' => $request->validated('reason'),
            'details' => $request->validated('details'),
        ]);

        return redirect()->back(fallback: $back)->with('status', 'Thank you. A moderator will review your report.');
    }
}
