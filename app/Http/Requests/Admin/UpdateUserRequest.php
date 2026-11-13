<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\Admin\Concerns\ValidatesAccountIdentifiers;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
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
     * Get the validation rules that apply to the request. The role cannot change after an account is created, and an
     * unchanged username keeps passing the format check so older accounts can still be edited.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $user = $this->route('user');

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', $this->uniqueAccountIdentifier('email', $user)],
            'username' => [
                'nullable',
                'string',
                Rule::when($this->input('username') !== $user->username, ['alpha_dash:ascii']),
                'max:30',
                $this->uniqueAccountIdentifier('username', $user),
            ],
            'phone' => ['nullable', 'string', 'max:30'],
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
            'name' => 'full name',
            'email' => 'email address',
            'phone' => 'mobile number',
        ];
    }
}
