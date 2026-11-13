<?php

namespace App\Http\Controllers\Forum;

use App\Http\Controllers\Controller;
use App\Http\Requests\Forum\StoreForumReplyRequest;
use App\Http\Requests\Forum\UpdateForumReplyRequest;
use App\Models\ForumReply;
use App\Models\ForumThread;
use App\Services\Forum\ForumPosting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Attributes\Controllers\Authorize;
use Illuminate\View\View;

#[Authorize('viewAny', ForumThread::class)]
class ReplyController extends Controller
{
    /**
     * Post a reply to a published thread (administrators may reply to any thread they can open).
     */
    #[Authorize('reply', 'thread')]
    public function store(StoreForumReplyRequest $request, ForumThread $thread, ForumPosting $posting): RedirectResponse
    {
        if (! $posting->acceptsRepliesFrom($request->user(), $thread)) {
            return redirect()->route('forum.threads.show', $thread)->with('error', ForumPosting::UNPUBLISHED_THREAD_MESSAGE);
        }

        $reply = $posting->reply($request->user(), $thread, $request->validated('body'));

        return redirect()->to($posting->replyUrl($reply))->with('status', 'Your reply is posted.');
    }

    /**
     * Show the form to edit a reply.
     */
    #[Authorize('view', 'reply')]
    #[Authorize('update', 'reply')]
    public function edit(ForumReply $reply, ForumPosting $posting): View
    {
        $reply->load('thread.category');

        return view('forum.replies.edit', [
            'reply' => $reply,
            'replyUrl' => $posting->replyUrl($reply),
        ]);
    }

    /**
     * Save the author's changes to a reply.
     */
    #[Authorize('view', 'reply')]
    #[Authorize('update', 'reply')]
    public function update(UpdateForumReplyRequest $request, ForumReply $reply, ForumPosting $posting): RedirectResponse
    {
        $reply->update($request->safe()->only(['body']));

        return redirect()->to($posting->replyUrl($reply))->with('status', 'Your reply was updated.');
    }

    /**
     * Delete a reply.
     */
    #[Authorize('view', 'reply')]
    #[Authorize('delete', 'reply')]
    public function destroy(ForumReply $reply, ForumPosting $posting): RedirectResponse
    {
        $page = $posting->pageOf($reply);

        $reply->delete();

        return redirect()->to($posting->threadUrl($reply->thread, $page).'#replies')->with('status', 'The reply was deleted.');
    }
}
