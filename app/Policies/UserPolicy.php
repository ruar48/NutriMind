<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\User;
use App\Policies\Concerns\AuthorizesAccess;
use Illuminate\Auth\Access\Response;

/**
 * Access to learner accounts from the teacher and parent portals.
 *
 * Administrators manage every account through the role:admin routes and need no policy.
 */
class UserPolicy
{
    use AuthorizesAccess;

    /**
     * Determine whether the teacher can see the learner: enrolled in one of the teacher's classrooms of a
     * subject still assigned to them (archived classrooms included). Other learners are reported as missing.
     */
    public function viewAsTeacher(User $user, User $student): Response
    {
        $canView = $user->isTeacher()
            && $student->isStudent()
            && $student->enrolledClassrooms()->accessibleBy($user)->exists();

        return $canView ? Response::allow() : Response::denyAsNotFound();
    }

    /**
     * Determine whether the teacher can write feedback for the learner.
     */
    public function giveFeedbackTo(User $user, User $student): Response
    {
        return $this->every(
            fn (): Response => $this->viewAsTeacher($user, $student),
            fn (): Response => $this->permission($user, Permission::GiveFeedback),
        );
    }

    /**
     * Determine whether the parent can follow the learner: a learner linked to them. Other learners are reported
     * as missing; a linked learner is refused with a reason when the parent may not view child progress.
     */
    public function viewAsGuardian(User $user, User $student): Response
    {
        $isLinked = $user->isParent()
            && $student->isStudent()
            && $user->linkedStudents()->whereKey($student->getKey())->exists();

        return $this->every(
            fn (): Response => $isLinked ? Response::allow() : Response::denyAsNotFound(),
            fn (): Response => $this->permission($user, Permission::ViewChildProgress),
        );
    }
}
