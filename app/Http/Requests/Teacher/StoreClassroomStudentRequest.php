<?php

namespace App\Http\Requests\Teacher;

use App\Enums\Sex;
use App\Models\User;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class StoreClassroomStudentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules for a new learner account; the grade level always comes from the classroom.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'lrn' => ['nullable', 'string', 'digits:12', Rule::unique('student_profiles', 'lrn')],
            'sex' => ['nullable', Rule::enum(Sex::class)],
            'birthdate' => ['nullable', 'date', 'before:today'],
            'username' => [
                'nullable',
                'string',
                'alpha_dash:ascii',
                'max:30',
                function (string $attribute, mixed $value, Closure $fail): void {
                    if (User::query()->whereRaw('LOWER(username) = ?', [Str::lower((string) $value)])->exists()) {
                        $fail('This username is already taken.');
                    }
                },
            ],
            'password' => ['nullable', 'string', Password::defaults()],
        ];
    }

    /**
     * Get the custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'lrn.digits' => 'The LRN must be exactly 12 digits.',
            'lrn.unique' => 'Another learner already has this LRN.',
            'birthdate.before' => 'The birthdate must be a date before today.',
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
            'lrn' => 'LRN',
        ];
    }
}
