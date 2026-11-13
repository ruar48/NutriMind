<?php

namespace App\Policies\Concerns;

use App\Enums\Permission;
use App\Models\User;
use App\Services\RolePermissions;
use Illuminate\Auth\Access\Response;

/**
 * Checks shared by the application's policies.
 *
 * Teacher "access to X" means the user is a teacher and X's subject is currently assigned to them.
 */
trait AuthorizesAccess
{
    /**
     * The message shown when the owner of a record lost the record's subject.
     */
    public const SUBJECT_REVOKED_MESSAGE = 'Your access to this subject was removed by the administrator.';

    /**
     * The message shown when a teacher has no assigned subject to create a record for.
     */
    public const NO_SUBJECT_MESSAGE = 'No subject is assigned to you yet. Ask your school administrator to assign one.';

    /**
     * The message shown when none of the teacher's assigned subjects is active.
     */
    public const NO_ACTIVE_SUBJECT_MESSAGE = 'None of your assigned subjects is active. Ask your school administrator to assign an active subject.';

    /**
     * Allow teachers only.
     */
    protected function teacher(User $user): Response
    {
        return $user->isTeacher() ? Response::allow() : Response::deny();
    }

    /**
     * Allow the user when the permission matrix grants them the permission.
     */
    protected function permission(User $user, Permission $permission): Response
    {
        return $user->hasPermission($permission)
            ? Response::allow()
            : Response::deny(RolePermissions::deniedMessage($permission));
    }

    /**
     * Allow the teacher who owns the record while they still teach its subject.
     *
     * Another user's record is reported as missing (404); the owner of a record whose subject was revoked is
     * told why (403).
     */
    protected function ownerWithSubjectAccess(User $user, mixed $ownerId, mixed $subjectId): Response
    {
        if (! $user->isTeacher() || ! $this->isOwner($user, $ownerId)) {
            return Response::denyAsNotFound();
        }

        return $user->teachesSubject((int) $subjectId)
            ? Response::allow()
            : Response::deny(self::SUBJECT_REVOKED_MESSAGE);
    }

    /**
     * Allow a user with at least one assigned subject (or, when required, at least one active one).
     */
    protected function assignedSubject(User $user, bool $mustBeActive = false): Response
    {
        if ($user->assignedSubjectIds() === []) {
            return Response::deny(self::NO_SUBJECT_MESSAGE);
        }

        if ($mustBeActive && ! $user->assignedSubjects->contains('is_active', true)) {
            return Response::deny(self::NO_ACTIVE_SUBJECT_MESSAGE);
        }

        return Response::allow();
    }

    /**
     * Determine whether the owner foreign key points to the user.
     */
    protected function isOwner(User $user, mixed $ownerId): bool
    {
        return $ownerId !== null
            && $user->getKey() !== null
            && (int) $ownerId === (int) $user->getKey();
    }

    /**
     * Return the first denied response, or allow when every check allows.
     *
     * The checks run in order and stop at the first denial, so ownership checks (404) run before
     * permission and state checks (403).
     *
     * @param  callable(): Response  ...$checks
     */
    protected function every(callable ...$checks): Response
    {
        foreach ($checks as $check) {
            $response = $check();

            if ($response->denied()) {
                return $response;
            }
        }

        return Response::allow();
    }
}
