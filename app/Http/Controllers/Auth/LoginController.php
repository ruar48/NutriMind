<?php

namespace App\Http\Controllers\Auth;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\User;
use App\Services\Settings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\View\View;

class LoginController extends Controller
{
    /**
     * Show the sign-in form of a portal.
     */
    public function create(UserRole $portal, Settings $settings): View
    {
        return view('auth.login', [
            'portal' => $portal,
            'schoolName' => $settings->get('school_name'),
        ]);
    }

    /**
     * Sign in to a portal.
     *
     * The user returns to the page they first asked for only when it belongs to their portal, the forum or
     * their account settings; otherwise they land on their portal dashboard.
     */
    public function store(LoginRequest $request, UserRole $portal): RedirectResponse
    {
        $request->authenticate($portal);

        $request->session()->regenerate();

        /** @var User $user */
        $user = $request->user();

        User::withoutTimestamps(function () use ($user): void {
            $user->forceFill(['last_login_at' => now()])->save();
        });

        $intendedUrl = $request->session()->pull('url.intended');

        if (is_string($intendedUrl) && $this->belongsToPortal($intendedUrl, $portal)) {
            return redirect()->to($intendedUrl);
        }

        return redirect()->route($portal->dashboardRoute());
    }

    /**
     * Sign out.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('status', 'You have been signed out.');
    }

    /**
     * Determine whether the URL is a page of this application that users of the portal's role may open.
     */
    private function belongsToPortal(string $url, UserRole $portal): bool
    {
        $root = rtrim(url('/'), '/');

        if ($url !== $root && ! Str::startsWith($url, $root.'/')) {
            return false;
        }

        $path = trim((string) parse_url(Str::after($url, $root), PHP_URL_PATH), '/');

        foreach ([$portal->value, 'forum', 'account'] as $area) {
            if ($path === $area || Str::startsWith($path, $area.'/')) {
                return true;
            }
        }

        return false;
    }
}
