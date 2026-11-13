<?php

namespace App\Http\Controllers\Forum;

use App\Enums\ForumContentStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Forum\StoreForumThreadRequest;
use App\Http\Requests\Forum\UpdateForumThreadRequest;
use App\Models\ForumCategory;
use App\Models\ForumReport;
use App\Models\ForumThread;
use App\Models\User;
use App\Services\Forum\ForumModeration;
use App\Services\Forum\ForumPosting;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Attributes\Controllers\Authorize;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

#[Authorize('viewAny', ForumThread::class)]
class ThreadController extends Controller
{
    /**
     * Show the form to start a thread, optionally in the category given as ?category={slug}.
     */
    #[Authorize('create', ForumThread::class)]
    public function create(Request $request, ForumPosting $posting): View
    {
        $categories = $this->categories();
        $requested = $request->query('category');
        $selected = is_string($requested) ? $categories->firstWhere('slug', $requested) : null;

        return view('forum.threads.create', [
            'thread' => new ForumThread(['forum_category_id' => $selected?->id]),
            'categoryOptions' => $categories->pluck('name', 'id')->all(),
            'requiresApproval' => $posting->requiresApproval($request->user()),
        ]);
    }

    /**
     * Start a thread: published right away, or waiting for a moderator when approval is required.
     */
    #[Authorize('create', ForumThread::class)]
    public function store(StoreForumThreadRequest $request, ForumPosting $posting): RedirectResponse
    {
        $thread = $posting->startThread($request->user(), $request->validated());

        return redirect()->route('forum.threads.show', $thread)->with('status', $thread->status === ForumContentStatus::Pending
            ? 'Submitted for approval. A moderator will review your thread before it appears in the forum.'
            : 'Your thread is posted.');
    }

    /**
     * Show a thread with one page of its replies. ?reply={id} jumps to the page of that reply.
     */
    #[Authorize('view', 'thread')]
    public function show(Request $request, ForumThread $thread, ForumPosting $posting): View|RedirectResponse
    {
        $requestedReply = $request->query('reply');

        if (is_string($requestedReply) && ctype_digit($requestedReply)) {
            $reply = $thread->replies()->find((int) $requestedReply);

            if ($reply !== null) {
                return redirect()->to($posting->replyUrl($reply));
            }
        }

        $user = $request->user();
        $thread->load(['category', 'author'])
            ->loadCount(['replies as published_replies_count' => fn (Builder $query) => $query->published()]);
        $replyAccess = Gate::inspect('reply', $thread);
        $repliesOpen = $posting->acceptsRepliesFrom($user, $thread);
        $ownReports = $user->forumReports()->unresolved()->where('forum_thread_id', $thread->id)->pluck('forum_reply_id');
        $openReports = $this->openReportCounts($user, $thread);

        return view('forum.threads.show', [
            'thread' => $thread,
            'replies' => $posting->replies($thread),
            'canReply' => $replyAccess->allowed() && $repliesOpen,
            'replyNotice' => match (true) {
                $replyAccess->denied() => $replyAccess->message() ?? 'You cannot reply to this thread.',
                ! $repliesOpen => ForumPosting::UNPUBLISHED_THREAD_MESSAGE,
                default => null,
            },
            'threadReported' => $ownReports->containsStrict(null),
            'reportedReplyIds' => $ownReports->filter()->map(fn (mixed $id): int => (int) $id)->values()->all(),
            'threadOpenReports' => $openReports->get('thread', 0),
            'replyOpenReports' => $openReports->except('thread')->all(),
            'quotedTitle' => app(ForumModeration::class)->quotedTitle($thread),
        ]);
    }

    /**
     * Show the form to edit a thread.
     */
    #[Authorize('update', 'thread')]
    public function edit(ForumThread $thread): View
    {
        return view('forum.threads.edit', [
            'thread' => $thread,
            'categoryOptions' => $this->categories($thread)->pluck('name', 'id')->all(),
        ]);
    }

    /**
     * Save the author's changes to a thread.
     */
    #[Authorize('update', 'thread')]
    public function update(UpdateForumThreadRequest $request, ForumThread $thread): RedirectResponse
    {
        $thread->update($request->safe()->only(['forum_category_id', 'title', 'body']));

        return redirect()->route('forum.threads.show', $thread)->with('status', 'Your thread was updated.');
    }

    /**
     * Delete a thread with its replies and reports.
     */
    #[Authorize('delete', 'thread')]
    public function destroy(ForumThread $thread, ForumModeration $moderation): RedirectResponse
    {
        $title = $moderation->quotedTitle($thread);

        $thread->delete();

        return redirect()->route('forum.index')->with('status', $title.' was deleted.');
    }

    /**
     * Get the categories a thread can be posted in: the active ones, plus the thread's own category.
     *
     * @return Collection<int, ForumCategory>
     */
    private function categories(?ForumThread $thread = null): Collection
    {
        return ForumCategory::query()
            ->where(function (Builder $query) use ($thread): void {
                $query->where('is_active', true)
                    ->when($thread, fn (Builder $query, ForumThread $thread) => $query->orWhere('id', $thread->forum_category_id));
            })
            ->orderBy('sort_order')
            ->orderBy('name')
            ->orderBy('id')
            ->get(['id', 'name', 'slug'])
            ->toBase();
    }

    /**
     * Count the open reports about the thread ("thread") and about each of its replies (keyed by reply ID), for
     * administrators only.
     *
     * @return Collection<int|string, int>
     */
    private function openReportCounts(User $user, ForumThread $thread): Collection
    {
        if (! $user->isAdmin()) {
            return collect();
        }

        return ForumReport::query()
            ->unresolved()
            ->where('forum_thread_id', $thread->id)
            ->toBase()
            ->selectRaw('forum_reply_id, COUNT(*) AS aggregate')
            ->groupBy('forum_reply_id')
            ->get()
            ->mapWithKeys(fn (object $row): array => [$row->forum_reply_id === null ? 'thread' : (int) $row->forum_reply_id => (int) $row->aggregate]);
    }
}
