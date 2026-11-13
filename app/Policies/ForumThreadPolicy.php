<?php

namespace App\Policies;

use App\Enums\ForumContentStatus;
use App\Enums\Permission;
use App\Models\ForumThread;
use App\Models\User;
use App\Policies\Concerns\AuthorizesAccess;
use App\Services\Settings;
use Illuminate\Auth\Access\Response;

class ForumThreadPolicy
{
    use AuthorizesAccess;

    /**
     * The message shown to teachers and parents while the administrator has turned the forum off.
     */
    public const FORUM_DISABLED_MESSAGE = 'The parent forum is turned off right now.';

    /**
     * The message shown when a locked thread would be replied to or edited.
     */
    public const LOCKED_MESSAGE = 'This thread is locked.';

    /**
     * The message shown when someone other than the author would edit a thread.
     */
    public const NOT_AUTHOR_MESSAGE = 'Only the author can edit this thread.';

    /**
     * The message shown when someone other than the author or an administrator would delete a thread.
     */
    public const CANNOT_DELETE_MESSAGE = 'Only the author or an administrator can delete this thread.';

    /**
     * The message shown when the author would report their own thread.
     */
    public const OWN_CONTENT_MESSAGE = 'You cannot report your own post.';

    /**
     * The message shown when a non-administrator would moderate the forum.
     */
    public const MODERATORS_ONLY_MESSAGE = 'Only administrators can moderate the forum.';

    public function __construct(private readonly Settings $settings) {}

    /**
     * Determine whether the user can read the forum: administrators always; teachers and parents with the
     * AccessForum permission while the forum is turned on.
     */
    public function viewAny(User $user): Response
    {
        if ($user->isAdmin()) {
            return Response::allow();
        }

        return $this->every(
            fn (): Response => $user->isTeacher() || $user->isParent() ? Response::allow() : Response::deny(),
            fn (): Response => $this->settings->get('forum_enabled', true)
                ? Response::allow()
                : Response::deny(self::FORUM_DISABLED_MESSAGE),
            fn (): Response => $this->permission($user, Permission::AccessForum),
        );
    }

    /**
     * Determine whether the user can open the thread: published threads, the author's own threads and, for
     * administrators, every thread. Other threads are reported as missing.
     */
    public function view(User $user, ForumThread $thread): Response
    {
        return $this->every(
            fn (): Response => $this->viewAny($user),
            fn (): Response => $thread->status === ForumContentStatus::Published
                || $user->isAdmin()
                || $this->isOwner($user, $thread->author_id)
                    ? Response::allow()
                    : Response::denyAsNotFound(),
        );
    }

    /**
     * Determine whether the user can start threads.
     */
    public function create(User $user): Response
    {
        return $this->every(
            fn (): Response => $this->viewAny($user),
            fn (): Response => $user->isAdmin() ? Response::allow() : $this->permission($user, Permission::PostInForum),
        );
    }

    /**
     * Determine whether the user can reply to the thread; only administrators reply to locked threads.
     */
    public function reply(User $user, ForumThread $thread): Response
    {
        return $this->every(
            fn (): Response => $this->view($user, $thread),
            fn (): Response => $this->create($user),
            fn (): Response => $thread->is_locked && ! $user->isAdmin()
                ? Response::deny(self::LOCKED_MESSAGE)
                : Response::allow(),
        );
    }

    /**
     * Determine whether the user can edit the thread: its author, while it is not locked.
     */
    public function update(User $user, ForumThread $thread): Response
    {
        if (! $this->isOwner($user, $thread->author_id)) {
            return $this->deniedForOthers($user, $thread, self::NOT_AUTHOR_MESSAGE);
        }

        return $thread->is_locked ? Response::deny(self::LOCKED_MESSAGE) : Response::allow();
    }

    /**
     * Determine whether the user can delete the thread: its author or an administrator.
     */
    public function delete(User $user, ForumThread $thread): Response
    {
        if ($this->isOwner($user, $thread->author_id) || $user->isAdmin()) {
            return Response::allow();
        }

        return $this->deniedForOthers($user, $thread, self::CANNOT_DELETE_MESSAGE);
    }

    /**
     * Determine whether the user can pin, lock, hide or publish threads.
     */
    public function moderate(User $user): Response
    {
        return $user->isAdmin() ? Response::allow() : Response::deny(self::MODERATORS_ONLY_MESSAGE);
    }

    /**
     * Determine whether the user can report the thread: anyone who can open it, except its author.
     */
    public function report(User $user, ForumThread $thread): Response
    {
        return $this->every(
            fn (): Response => $this->view($user, $thread),
            fn (): Response => $this->isOwner($user, $thread->author_id)
                ? Response::deny(self::OWN_CONTENT_MESSAGE)
                : Response::allow(),
        );
    }

    /**
     * Refuse someone who may not change the thread: as missing when they cannot even open it.
     */
    private function deniedForOthers(User $user, ForumThread $thread, string $message): Response
    {
        $view = $this->view($user, $thread);

        return $view->denied() ? $view : Response::deny($message);
    }
}
