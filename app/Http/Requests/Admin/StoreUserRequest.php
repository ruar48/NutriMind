<?php

namespace App\Http\Requests\Admin;

use App\Enums\UserRole;
use App\Http\Requests\Admin\Concerns\ValidatesAccountIdentifiers;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class StoreUserRequest extends FormRequest
{
    use ValidatesAccountIdentifiers;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        $this->squishInput('name');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'role' => ['required', 'string', Rule::in(array_map(fn (UserRole $role): string => $role->value, UserRole::webPortals()))],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', $this->uniqueAccountIdentifier('email')],
            'username' => ['nullable', 'string', 'alpha_dash:ascii', 'max:30', $this->uniqueAccountIdentifier('username')],
            'phone' => ['nullable', 'string', 'max:30'],
            'password' => ['nullable', 'string', Password::defaults(), 'confirmed'],
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
            'role' => 'account type',
            'name' => 'full name',
            'email' => 'email address',
            'phone' => 'mobile number',
        ];
    }

    /**
     * Get the messages that read better than the defaults.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'role.required' => 'Choose the type of account.',
            'role.in' => 'Choose an administrator, teacher or parent account. Learners are added under Learners.',
        ];
    }

    /**
     * Get the role of the new account.
     */
    public function role(): UserRole
    {
        return UserRole::from($this->validated('role'));
    }
}
