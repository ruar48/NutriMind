<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\LessonPlan;
use App\Models\User;
use App\Policies\Concerns\AuthorizesAccess;
use Illuminate\Auth\Access\Response;

class LessonPlanPolicy
{
    use AuthorizesAccess;

    /**
     * Determine whether the user can list their lesson plans.
     */
    public function viewAny(User $user): Response
    {
        return $this->every(
            fn (): Response => $this->teacher($user),
            fn (): Response => $this->permission($user, Permission::ManageLessonPlans),
        );
    }

    /**
     * Determine whether the user can open the lesson plan: its teacher, while they teach its subject.
     */
    public function view(User $user, LessonPlan $lessonPlan): Response
    {
        return $this->every(
            fn (): Response => $this->ownerWithSubjectAccess($user, $lessonPlan->teacher_id, $lessonPlan->subject_id),
            fn (): Response => $this->permission($user, Permission::ManageLessonPlans),
        );
    }

    /**
     * Determine whether the user can write lesson plans.
     */
    public function create(User $user): Response
    {
        return $this->every(
            fn (): Response => $this->viewAny($user),
            fn (): Response => $this->assignedSubject($user),
        );
    }

    /**
     * Determine whether the user can edit the lesson plan and its alignment.
     */
    public function update(User $user, LessonPlan $lessonPlan): Response
    {
        return $this->view($user, $lessonPlan);
    }

    /**
     * Determine whether the user can delete the lesson plan.
     */
    public function delete(User $user, LessonPlan $lessonPlan): Response
    {
        return $this->view($user, $lessonPlan);
    }
}
