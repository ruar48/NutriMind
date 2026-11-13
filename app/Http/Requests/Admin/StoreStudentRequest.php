<?php

namespace App\Http\Requests\Admin;

use App\Enums\GradeLevel;
use App\Http\Requests\Admin\Concerns\ValidatesLearnerProfile;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\Validator;

class StoreStudentRequest extends FormRequest
{
    use ValidatesLearnerProfile;

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
        $this->normalizeLearnerInput();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            ...$this->learnerRules(),
            'username' => $this->usernameRules(required: false),
            'password' => ['nullable', 'string', Password::defaults(), 'confirmed'],
        ];
    }

    /**
     * Get the "after" validation callables for the request.
     *
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->hasAny(['grade_level', 'classroom_ids', 'classroom_ids.*'])) {
                    return;
                }

                $message = $this->gradeMismatchMessage($this->selectedClassrooms(), GradeLevel::from((int) $this->input('grade_level')));

                if ($message !== null) {
                    $validator->errors()->add('classroom_ids', $message);
                }
            },
        ];
    }
}
