<?php

namespace App\Http\Requests\Game;

use App\Enums\UserRole;
use App\Http\Requests\Auth\LoginRequest as PortalLoginRequest;
use App\Models\User;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Validates the game's sign-in: a learner's username and password, the credentials teachers hand out.
 *
 * Only learner accounts may sign in; any other account gets the same "credentials do not match" answer as a wrong
 * password, so the game never reveals that a staff or parent username exists. Like the web portals, five failed
 * tries per username and device address lock the sign-in for a while.
 */
class LoginRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'username' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string', 'max:255'],
            'device_name' => ['nullable', 'string', 'max:100'],
        ];
    }

    /**
     * Find the active learner the credentials belong to.
     *
     * @throws ValidationException
     */
    public function authenticate(): User
    {
        $this->ensureIsNotRateLimited();

        $student = User::query()
            ->where('role', UserRole::Student)
            ->whereRaw('lower(username) = ?', [Str::lower(trim($this->string('username')->toString()))])
            ->first();

        if ($student === null || ! Hash::check($this->string('password')->toString(), $student->password)) {
            RateLimiter::hit($this->throttleKey());

            throw ValidationException::withMessages(['username' => trans('auth.failed')]);
        }

        if (! $student->is_active) {
            throw ValidationException::withMessages(['username' => PortalLoginRequest::DEACTIVATED_MESSAGE]);
        }

        RateLimiter::clear($this->throttleKey());

        return $student;
    }

    /**
     * Get the name the new API token is stored under, so a learner's devices can be told apart.
     */
    public function deviceName(): string
    {
        return $this->filled('device_name') ? $this->string('device_name')->toString() : 'NutriMind game';
    }

    /**
     * Refuse the sign-in while the username and device address have too many failed tries.
     *
     * @throws ValidationException
     */
    private function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), PortalLoginRequest::MAX_ATTEMPTS)) {
            return;
        }

        event(new Lockout($this));

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'username' => trans('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ]);
    }

    /**
     * Get the rate limiting key of the sign-in.
     */
    private function throttleKey(): string
    {
        return 'game|'.Str::transliterate(Str::lower($this->string('username')->toString()).'|'.$this->ip());
    }
}
