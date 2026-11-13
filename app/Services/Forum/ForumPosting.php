<?php

namespace App\Services\Forum;

use App\Enums\ForumContentStatus;
use App\Models\ForumReply;
use App\Models\ForumThread;
use App\Models\User;
use App\Services\Settings;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Posts threads and replies, and pages through a thread's replies.
 *
 * Callers authorize and validate first. While the "forum_requires_approval" setting is on, threads by teachers and
 * parents wait for a moderator; administrators always publish right away.
 */
class ForumPosting
{
    /**
     * How many replies one page of a thread shows.
     */
    public const REPLIES_PER_PAGE = 20;

    /**
     * The message shown when a reply is posted to a thread that is not published (only administrators may).
     */
    public const UNPUBLISHED_THREAD_MESSAGE = 'Replies open once a moderator publishes this thread.';

    public function __construct(private readonly Settings $settings) {}

    /**
     * Determine whether a new thread by the author waits for a moderator's approval.
     */
    public function requiresApproval(User $author): bool
    {
        return ! $author->isAdmin() && (bool) $this->settings->get('forum_requires_approval', false);
    }

    /**
     * Determine whether the thread's state lets the user reply: published threads, and every thread for
     * administrators. The reply policy (locked threads, posting permission) is checked separately.
     */
    public function acceptsRepliesFrom(User $user, ForumThread $thread): bool
    {
        return $thread->status === ForumContentStatus::Published || $user->isAdmin();
    }

    /**
     * Start a thread.
     *
     * @param  array{forum_category_id: int|string, title: string, body: string}  $attributes
     */
    public function startThread(User $author, array $attributes): ForumThread
    {
        return $author->forumThreads()->create([
            'forum_category_id' => $attributes['forum_category_id'],
            'title' => $attributes['title'],
            'body' => $attributes['body'],
            'status' => $this->requiresApproval($author) ? ForumContentStatus::Pending : ForumContentStatus::Published,
            'last_activity_at' => now(),
        ]);
    }

    /**
     * Post a reply and mark the thread as active.
     */
    public function reply(User $author, ForumThread $thread, string $body): ForumReply
    {
        return DB::transaction(function () use ($author, $thread, $body): ForumReply {
            $reply = $author->forumReplies()->create([
                'forum_thread_id' => $thread->id,
                'body' => $body,
            ]);

            $thread->update(['last_activity_at' => $reply->created_at]);

            return $reply;
        });
    }

    /**
     * Get one page of the thread's replies, oldest first, with their authors.
     *
     * Every reply is listed (hidden ones too) so a reply keeps its page for every reader; the page decides how
     * much of a hidden reply each reader sees. Each reply gets the given thread as its "thread" relation, which the
     * reply policy reads.
     *
     * @return LengthAwarePaginator<int, ForumReply>
     */
    public function replies(ForumThread $thread): LengthAwarePaginator
    {
        return $thread->replies()
            ->chaperone('thread')
            ->with('author')
            ->orderBy('created_at')
            ->orderBy('id')
            ->paginate(self::REPLIES_PER_PAGE)
            ->withQueryString();
    }

    /**
     * Get the page of the thread that shows the reply.
     */
    public function pageOf(ForumReply $reply): int
    {
        $earlierReplies = ForumReply::query()
            ->where('forum_thread_id', $reply->forum_thread_id)
            ->where(function (Builder $query) use ($reply): void {
                $query->where('created_at', '<', $reply->created_at)
                    ->orWhere(function (Builder $query) use ($reply): void {
                        $query->where('created_at', $reply->created_at)->where('id', '<', $reply->id);
                    });
            })
            ->count();

        return intdiv($earlierReplies, self::REPLIES_PER_PAGE) + 1;
    }

    /**
     * Get the link to a page of the thread's replies; a page past the end becomes the last page.
     */
    public function threadUrl(ForumThread $thread, int $page = 1): string
    {
        $lastPage = max(1, (int) ceil($thread->replies()->count() / self::REPLIES_PER_PAGE));
        $page = min(max(1, $page), $lastPage);

        return route('forum.threads.show', $page > 1 ? ['thread' => $thread, 'page' => $page] : ['thread' => $thread]);
    }

    /**
     * Get the link to the reply: its page of the thread, scrolled to the reply.
     */
    public function replyUrl(ForumReply $reply): string
    {
        $page = $this->pageOf($reply);
        $parameters = $page > 1 ? ['thread' => $reply->forum_thread_id, 'page' => $page] : ['thread' => $reply->forum_thread_id];

        return route('forum.threads.show', $parameters).'#reply-'.$reply->id;
    }
}
