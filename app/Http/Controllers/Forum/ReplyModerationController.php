<?php

namespace App\Http\Controllers\Forum;

use App\Http\Controllers\Controller;
use App\Http\Requests\Forum\UpdateForumReplyModerationRequest;
use App\Models\ForumReply;
use App\Services\Forum\ForumModeration;
use App\Services\Forum\ForumPosting;
use App\Services\Forum\ModerationAction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Attributes\Controllers\Authorize;

class ReplyModerationController extends Controller
{
    /**
     * Hide a reply or show it again.
     */
    #[Authorize('moderate', ForumReply::class)]
    public function update(UpdateForumReplyModerationRequest $request, ForumReply $reply, ForumModeration $moderation, ForumPosting $posting): RedirectResponse
    {
        $message = $moderation->moderateReply($reply, $request->enum('action', ModerationAction::class), $request->user());

        return redirect()->to($posting->replyUrl($reply))->with('status', $message);
    }
}
