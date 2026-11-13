<?php

namespace App\Policies;

use App\Models\Mission;
use App\Models\User;
use App\Policies\Concerns\AuthorizesAccess;
use Illuminate\Auth\Access\Response;

/**
 * Teacher access to learners' progress on game missions (P2-Monitoring).
 *
 * Administrators manage the mission catalog through the role:admin routes and need no policy; these abilities only
 * cover the teacher's mission progress pages.
 */
class MissionPolicy
{
    use AuthorizesAccess;

    /**
     * Determine whether the user can open the mission progress overview.
     */
    public function viewAnyProgress(User $user): Response
    {
        return $this->teacher($user);
    }

    /**
     * Determine whether the user can follow learners' progress on the mission: a teacher of its subject.
     * Missions of other subjects are reported as missing.
     */
    public function viewProgress(User $user, Mission $mission): Response
    {
        return $user->isTeacher() && $user->teachesSubject((int) $mission->subject_id)
            ? Response::allow()
            : Response::denyAsNotFound();
    }
}
