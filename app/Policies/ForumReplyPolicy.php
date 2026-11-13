<?php

namespace App\Policies;

use App\Enums\ForumContentStatus;
use App\Models\ForumReply;
use App\Models\User;
use App\Policies\Concerns\AuthorizesAccess;
use Illuminate\Auth\Access\Response;
use Illuminate\Support\Facades\Gate;

class ForumReplyPolicy
{
    use AuthorizesAccess;

    /**
     * The message shown when someone other than the author would edit a reply.
     */
    public const NOT_AUTHOR_MESSAGE = 'Only the author can edit this reply.';

    /**
     * The message shown when someone other than the author or an administrator would delete a reply.
     */
    public const CANNOT_DELETE_MESSAGE = 'Only the author or an administrator can delete this reply.';

    /**
     * Determine whether the user can open the reply: anyone who can open its thread (otherwise reported as
     * missing, or refused while the forum is closed to them).
     */
    public function view(User $user, ForumReply $reply): Response
    {
        return Gate::forUser($user)->inspect('view', $reply->thread);
    }

    /**
     * Determine whether the user can edit the reply: its author, while the thread is not locked.
     */
    public function update(User $user, ForumReply $reply): Response
    {
        if (! $this->isOwner($user, $reply->author_id)) {
            return Response::deny(self::NOT_AUTHOR_MESSAGE);
        }

        return $reply->thread->is_locked ? Response::deny(ForumThreadPolicy::LOCKED_MESSAGE) : Response::allow();
    }

    /**
     * Determine whether the user can delete the reply: its author or an administrator.
     */
    public function delete(User $user, ForumReply $reply): Response
    {
        return $this->isOwner($user, $reply->author_id) || $user->isAdmin()
            ? Response::allow()
            : Response::deny(self::CANNOT_DELETE_MESSAGE);
    }

    /**
     * Determine whether the user can hide or publish replies.
     */
    public function moderate(User $user): Response
    {
        return $user->isAdmin() ? Response::allow() : Response::deny(ForumThreadPolicy::MODERATORS_ONLY_MESSAGE);
    }

    /**
     * Determine whether the user can report the reply: anyone who can open its thread, except its author. A reply
     * that is not published (hidden by a moderator) is reported as missing.
     */
    public function report(User $user, ForumReply $reply): Response
    {
        return $this->every(
            fn (): Response => Gate::forUser($user)->inspect('view', $reply->thread),
            fn (): Response => $reply->status === ForumContentStatus::Published
                ? Response::allow()
                : Response::denyAsNotFound(),
            fn (): Response => $this->isOwner($user, $reply->author_id)
                ? Response::deny(ForumThreadPolicy::OWN_CONTENT_MESSAGE)
                : Response::allow(),
        );
    }
}
