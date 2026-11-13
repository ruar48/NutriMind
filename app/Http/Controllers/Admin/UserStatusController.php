<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\ManagesPeopleAccounts;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateUserStatusRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

/**
 * Activate or deactivate an account. A deactivated account is signed out on its next request and cannot sign in.
 */
class UserStatusController extends Controller
{
    use ManagesPeopleAccounts;

    /**
     * The refusal shown when administrators try to deactivate themselves.
     */
    public const SELF_DEACTIVATION_MESSAGE = "You can't deactivate your own account.";

    /**
     * Set the account to the requested status, refusing the signed-in administrator and the last active one.
     */
    public function update(UpdateUserStatusRequest $request, User $user): RedirectResponse
    {
        $activate = $request->boolean('is_active');
        $back = back(fallback: $this->accountUrl($user));

        if (! $activate && $user->is($request->user())) {
            return $back->with('error', self::SELF_DEACTIVATION_MESSAGE);
        }

        if ($user->is_active === $activate) {
            return $back->with('status', $user->name.($activate ? ' is already active.' : ' is already deactivated.'));
        }

        $isChanged = DB::transaction(function () use ($user, $activate): bool {
            if (! $activate && $this->isLastActiveAdministrator($user)) {
                return false;
            }

            $user->is_active = $activate;

            return $user->save();
        });

        if (! $isChanged) {
            return $back->with('error', self::LAST_ADMINISTRATOR_MESSAGE);
        }

        return $back->with('status', $activate
            ? $user->name.' was reactivated and can sign in again.'
            : $user->name.' was deactivated and can no longer sign in.');
    }
}
