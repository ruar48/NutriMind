<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\LearnerFeedback;
use App\Models\User;
use App\Policies\Concerns\AuthorizesAccess;
use Illuminate\Auth\Access\Response;

class LearnerFeedbackPolicy
{
    use AuthorizesAccess;

    /**
     * Determine whether the user can list the feedback they wrote.
     */
    public function viewAny(User $user): Response
    {
        return $this->every(
            fn (): Response => $this->teacher($user),
            fn (): Response => $this->permission($user, Permission::GiveFeedback),
        );
    }

    /**
     * Determine whether the user can write feedback.
     */
    public function create(User $user): Response
    {
        return $this->viewAny($user);
    }

    /**
     * Determine whether the user can edit the feedback: only the teacher who wrote it.
     */
    public function update(User $user, LearnerFeedback $feedback): Response
    {
        return $this->every(
            fn (): Response => $user->isTeacher() && $this->isOwner($user, $feedback->teacher_id)
                ? Response::allow()
                : Response::denyAsNotFound(),
            fn (): Response => $this->permission($user, Permission::GiveFeedback),
        );
    }

    /**
     * Determine whether the user can delete the feedback: only the teacher who wrote it.
     */
    public function delete(User $user, LearnerFeedback $feedback): Response
    {
        return $this->update($user, $feedback);
    }
}
