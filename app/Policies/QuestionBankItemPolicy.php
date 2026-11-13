<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\QuestionBankItem;
use App\Models\User;
use App\Policies\Concerns\AuthorizesAccess;
use Illuminate\Auth\Access\Response;

class QuestionBankItemPolicy
{
    use AuthorizesAccess;

    /**
     * The message shown when a teacher would change a shared item another teacher owns.
     */
    public const NOT_OWNER_MESSAGE = 'Only the teacher who created this question can change it. Duplicate it to make your own copy.';

    /**
     * Determine whether the user can browse the question bank.
     */
    public function viewAny(User $user): Response
    {
        return $this->every(
            fn (): Response => $this->teacher($user),
            fn (): Response => $this->permission($user, Permission::ManageQuestionBank),
        );
    }

    /**
     * Determine whether the user can open the item: their own or a shared one, in a subject they teach.
     *
     * Items outside the user's subjects and other teachers' private items are reported as missing (404),
     * matching QuestionBankItem::visibleTo().
     */
    public function view(User $user, QuestionBankItem $item): Response
    {
        return $this->every(
            fn (): Response => $this->visible($user, $item),
            fn (): Response => $this->permission($user, Permission::ManageQuestionBank),
        );
    }

    /**
     * Determine whether the user can add items to the question bank.
     */
    public function create(User $user): Response
    {
        return $this->every(
            fn (): Response => $this->viewAny($user),
            fn (): Response => $this->assignedSubject($user),
        );
    }

    /**
     * Determine whether the user can edit the item: only its owner.
     */
    public function update(User $user, QuestionBankItem $item): Response
    {
        return $this->every(
            fn (): Response => $this->view($user, $item),
            fn (): Response => $this->isOwner($user, $item->teacher_id)
                ? Response::allow()
                : Response::deny(self::NOT_OWNER_MESSAGE),
        );
    }

    /**
     * Determine whether the user can delete the item: only its owner.
     */
    public function delete(User $user, QuestionBankItem $item): Response
    {
        return $this->update($user, $item);
    }

    /**
     * Determine whether the user can share the item with other teachers of its subject, or stop sharing it.
     */
    public function share(User $user, QuestionBankItem $item): Response
    {
        return $this->every(
            fn (): Response => $this->update($user, $item),
            fn (): Response => $this->permission($user, Permission::ShareQuestions),
        );
    }

    /**
     * Allow a teacher of the item's subject who owns the item or sees it shared.
     */
    private function visible(User $user, QuestionBankItem $item): Response
    {
        $isVisible = $user->isTeacher()
            && $user->teachesSubject((int) $item->subject_id)
            && ($this->isOwner($user, $item->teacher_id) || $item->is_shared);

        return $isVisible ? Response::allow() : Response::denyAsNotFound();
    }
}
