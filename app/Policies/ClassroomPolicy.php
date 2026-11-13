<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Classroom;
use App\Models\User;
use App\Policies\Concerns\AuthorizesAccess;
use Illuminate\Auth\Access\Response;

class ClassroomPolicy
{
    use AuthorizesAccess;

    /**
     * The message shown when learners of an archived classroom would change.
     */
    public const ARCHIVED_MESSAGE = 'This classroom is archived. Restore it before changing its learners.';

    /**
     * Determine whether the user can list their classrooms.
     */
    public function viewAny(User $user): Response
    {
        return $this->teacher($user);
    }

    /**
     * Determine whether the user can open the classroom: its teacher, while they teach its subject.
     */
    public function view(User $user, Classroom $classroom): Response
    {
        return $this->ownerWithSubjectAccess($user, $classroom->teacher_id, $classroom->subject_id);
    }

    /**
     * Determine whether the user can create classrooms.
     */
    public function create(User $user): Response
    {
        return $this->every(
            fn (): Response => $this->teacher($user),
            fn (): Response => $this->permission($user, Permission::ManageClassrooms),
            fn (): Response => $this->assignedSubject($user, mustBeActive: true),
        );
    }

    /**
     * Determine whether the user can edit the classroom (and archive or restore it).
     */
    public function update(User $user, Classroom $classroom): Response
    {
        return $this->every(
            fn (): Response => $this->view($user, $classroom),
            fn (): Response => $this->permission($user, Permission::ManageClassrooms),
        );
    }

    /**
     * Determine whether the user can delete the classroom.
     */
    public function delete(User $user, Classroom $classroom): Response
    {
        return $this->update($user, $classroom);
    }

    /**
     * Determine whether the user can add, enroll and remove learners of the classroom and reset their passwords.
     */
    public function manageStudents(User $user, Classroom $classroom): Response
    {
        return $this->every(
            fn (): Response => $this->view($user, $classroom),
            fn (): Response => $this->permission($user, Permission::ManageStudents),
            fn (): Response => $this->notArchived($classroom),
        );
    }

    /**
     * Determine whether the user can import learners into the classroom from a CSV file.
     */
    public function importStudents(User $user, Classroom $classroom): Response
    {
        return $this->every(
            fn (): Response => $this->view($user, $classroom),
            fn (): Response => $this->permission($user, Permission::ImportStudents),
            fn (): Response => $this->notArchived($classroom),
        );
    }

    /**
     * Allow only classrooms that are not archived.
     */
    private function notArchived(Classroom $classroom): Response
    {
        return $classroom->isArchived() ? Response::deny(self::ARCHIVED_MESSAGE) : Response::allow();
    }
}
