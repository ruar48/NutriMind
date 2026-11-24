<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
use Closure;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Restrict a route to users with one of the given roles (`role:admin` or `role:admin,teacher,parent`).
 *
 * A deactivated account is signed out on its next request, whatever its role. A token request (the game's API)
 * has no session to end, so the account's API tokens are revoked and the request gets 403 instead.
 */
class EnsureUserHasRole
{
    /**
     * The message shown on the sign-in page after a deactivated account is signed out.
     */
    public const DEACTIVATED_MESSAGE = 'Your account has been deactivated. Please contact the school administrator.';

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     *
     * @throws AuthenticationException
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if ($user === null) {
            throw new AuthenticationException;
        }

        if (! $user->is_active && ! $request->hasSession()) {
            $user->tokens()->delete();

            abort(Response::HTTP_FORBIDDEN, self::DEACTIVATED_MESSAGE);
        }

        if (! $user->is_active) {
            Auth::logout();

            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->with('error', self::DEACTIVATED_MESSAGE);
        }

        $allowedRoles = array_map(fn (string $role): UserRole => UserRole::from($role), $roles);

        abort_unless($user->hasRole(...$allowedRoles), Response::HTTP_FORBIDDEN, 'Your account does not have access to this page.');

        return $next($request);
    }
}
