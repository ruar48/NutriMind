<?php

namespace App\Http\Controllers\Admin\Concerns;

use App\Enums\UserRole;
use App\Models\User;

/**
 * Helpers shared by the admin people controllers: the page that shows an account and the guard that keeps at
 * least one active administrator.
 */
trait ManagesPeopleAccounts
{
    /**
     * The refusal shown when an action would leave the school without an active administrator.
     */
    public const LAST_ADMINISTRATOR_MESSAGE = 'Keep at least one active administrator. Add or reactivate another administrator first.';

    /**
     * Get the admin page that shows the account: the learner, teacher or parent profile, or the edit page of an
     * administrator.
     */
    protected function accountUrl(User $user): string
    {
        return match ($user->role) {
            UserRole::Student => route('admin.students.show', $user),
            UserRole::Teacher => route('admin.teachers.show', $user),
            UserRole::Parent => route('admin.parents.show', $user),
            default => route('admin.users.edit', $user),
        };
    }

    /**
     * Get the list the account belongs to.
     */
    protected function accountListUrl(UserRole $role): string
    {
        return match ($role) {
            UserRole::Student => route('admin.students.index'),
            UserRole::Teacher => route('admin.teachers.index'),
            UserRole::Parent => route('admin.parents.index'),
            UserRole::Admin => route('admin.users.index', ['role' => UserRole::Admin->value]),
        };
    }

    /**
     * Determine whether the account is the only active administrator left. Call it inside a transaction: the other
     * active administrators are locked until it commits, so two simultaneous requests cannot remove the last two.
     */
    protected function isLastActiveAdministrator(User $user): bool
    {
        if (! $user->isAdmin() || ! $user->is_active) {
            return false;
        }

        return User::query()
            ->ofRole(UserRole::Admin)
            ->active()
            ->whereKeyNot($user->getKey())
            ->lockForUpdate()
            ->count() === 0;
    }
}
