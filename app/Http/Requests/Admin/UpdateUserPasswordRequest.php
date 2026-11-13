<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\Attributes\ErrorBag;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

#[ErrorBag('resetPassword')]
class UpdateUserPasswordRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request: either generate=1, or a new password with confirmation.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'generate' => ['sometimes', 'boolean'],
            'password' => [Rule::excludeIf($this->wantsGeneratedPassword()), 'required', 'string', Password::defaults(), 'confirmed'],
        ];
    }

    /**
     * Get the field names used in validation messages, matching the form labels.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'password' => 'new password',
        ];
    }

    /**
     * Determine whether the administrator asked for a generated password.
     */
    public function wantsGeneratedPassword(): bool
    {
        return $this->boolean('generate');
    }
}
