<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\ManagesPeopleAccounts;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateUserPasswordRequest;
use App\Models\User;
use App\Services\StudentAccounts;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;

/**
 * Reset another account's password: either a password the administrator types, or a generated one that is shown
 * once. Remembered sign-ins ("remember me") of the account stop working either way.
 */
class UserPasswordController extends Controller
{
    use ManagesPeopleAccounts;

    /**
     * The refusal shown when administrators try to reset their own password here (Account settings asks for the
     * current password first).
     */
    public const SELF_RESET_MESSAGE = 'Change your own password in Account settings.';

    /**
     * Set or generate the new password and go back to the page the reset started from.
     */
    public function update(UpdateUserPasswordRequest $request, User $user, StudentAccounts $accounts): RedirectResponse
    {
        $back = back(fallback: $this->accountUrl($user));

        if ($user->is($request->user())) {
            return $back->with('error', self::SELF_RESET_MESSAGE);
        }

        if (! $request->wantsGeneratedPassword()) {
            $this->changePassword($user, $request->validated('password'));

            return $back->with('status', $user->name."'s password was changed.");
        }

        $plainPassword = $user->isStudent()
            ? $accounts->resetPassword($user)
            : $this->changePassword($user, Str::password(UserController::GENERATED_PASSWORD_LENGTH, symbols: false));

        return $back
            ->with('status', 'A new password was generated for '.$user->name.'.')
            ->with('credentials', [[
                'name' => $user->name,
                'username' => $user->isStudent() ? (string) $user->username : (string) $user->email,
                'password' => $plainPassword,
            ]]);
    }

    /**
     * Give the account the password, sign out its remembered sessions, and return the password.
     */
    private function changePassword(User $user, string $plainPassword): string
    {
        $user->password = $plainPassword;
        $user->setRememberToken(Str::random(60));
        $user->save();

        return $plainPassword;
    }
}
