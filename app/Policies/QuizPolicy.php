<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Quiz;
use App\Models\User;
use App\Policies\Concerns\AuthorizesAccess;
use Illuminate\Auth\Access\Response;

class QuizPolicy
{
    use AuthorizesAccess;

    /**
     * The message shown when a quiz with learner attempts would be deleted.
     */
    public const HAS_ATTEMPTS_MESSAGE = 'This quiz already has learner attempts. Close it instead.';

    /**
     * Determine whether the user can list their quizzes.
     */
    public function viewAny(User $user): Response
    {
        return $this->teacher($user);
    }

    /**
     * Determine whether the user can open the quiz: its teacher, while they teach its subject.
     */
    public function view(User $user, Quiz $quiz): Response
    {
        return $this->ownerWithSubjectAccess($user, $quiz->teacher_id, $quiz->subject_id);
    }

    /**
     * Determine whether the user can see the quiz results and attempts.
     */
    public function viewResults(User $user, Quiz $quiz): Response
    {
        return $this->view($user, $quiz);
    }

    /**
     * Determine whether the user can create quizzes.
     */
    public function create(User $user): Response
    {
        return $this->every(
            fn (): Response => $this->teacher($user),
            fn (): Response => $this->permission($user, Permission::CreateQuizzes),
            fn (): Response => $this->assignedSubject($user),
        );
    }

    /**
     * Determine whether the user can edit the quiz, its questions, review and status.
     */
    public function update(User $user, Quiz $quiz): Response
    {
        return $this->every(
            fn (): Response => $this->view($user, $quiz),
            fn (): Response => $this->permission($user, Permission::CreateQuizzes),
        );
    }

    /**
     * Determine whether the user can delete the quiz: only while no learner attempted it.
     *
     * Pass a quiz loaded with withExists('attempts') to answer without a query.
     */
    public function delete(User $user, Quiz $quiz): Response
    {
        return $this->every(
            fn (): Response => $this->update($user, $quiz),
            fn (): Response => ($quiz->getAttribute('attempts_exists') ?? $quiz->attempts()->exists())
                ? Response::deny(self::HAS_ATTEMPTS_MESSAGE)
                : Response::allow(),
        );
    }
}
