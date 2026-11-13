<?php

namespace App\Http\Requests\Auth;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
{
    /**
     * The failed sign-in attempts allowed per minute for one email address and IP address.
     */
    public const MAX_ATTEMPTS = 5;

    /**
     * The error shown when the password is correct but the account is deactivated.
     */
    public const DEACTIVATED_MESSAGE = 'This account has been deactivated. Please contact your school administrator.';

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
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
            'remember' => ['nullable', 'boolean'],
        ];
    }

    /**
     * Sign in the active account of the portal's role that matches the credentials.
     *
     * A wrong password and an account of another role fail with the same message, so the form never reveals
     * which part was wrong or which portal an address belongs to. Every failure counts towards the rate limit.
     *
     * @throws ValidationException
     */
    public function authenticate(UserRole $portal): void
    {
        $this->ensureIsNotRateLimited();

        $isDeactivated = false;

        $signedIn = Auth::attemptWhen(
            [
                'email' => $this->string('email')->toString(),
                'password' => $this->string('password')->toString(),
                fn (Builder $query) => $query->where('role', $portal->value),
            ],
            function (User $user) use (&$isDeactivated): bool {
                $isDeactivated = ! $user->is_active;

                return $user->is_active;
            },
            $this->boolean('remember'),
        );

        if (! $signedIn) {
            RateLimiter::hit($this->throttleKey());

            throw ValidationException::withMessages([
                'email' => $isDeactivated ? self::DEACTIVATED_MESSAGE : trans('auth.failed'),
            ]);
        }

        RateLimiter::clear($this->throttleKey());
    }

    /**
     * Ensure the sign-in request is not rate limited.
     *
     * @throws ValidationException
     */
    public function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), self::MAX_ATTEMPTS)) {
            return;
        }

        event(new Lockout($this));

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'email' => trans('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ]);
    }

    /**
     * Get the rate limiting key: the lowercased email address and the IP address.
     */
    public function throttleKey(): string
    {
        return Str::transliterate(Str::lower($this->string('email')->toString()).'|'.$this->ip());
    }
}
