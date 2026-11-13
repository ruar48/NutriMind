<?php

namespace App\Http\Controllers\Forum;

use App\Http\Controllers\Controller;
use App\Http\Requests\Forum\UpdateForumThreadModerationRequest;
use App\Models\ForumThread;
use App\Services\Forum\ForumModeration;
use App\Services\Forum\ModerationAction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Attributes\Controllers\Authorize;

class ThreadModerationController extends Controller
{
    /**
     * Pin, unpin, lock, unlock, hide or show a thread again.
     */
    #[Authorize('moderate', ForumThread::class)]
    public function update(UpdateForumThreadModerationRequest $request, ForumThread $thread, ForumModeration $moderation): RedirectResponse
    {
        $action = $request->enum('action', ModerationAction::class);
        $refusal = $moderation->threadRefusal($thread, $action);

        if ($refusal !== null) {
            return redirect()->route('forum.threads.show', $thread)->with('error', $refusal);
        }

        return redirect()->route('forum.threads.show', $thread)
            ->with('status', $moderation->moderateThread($thread, $action, $request->user()));
    }
}
