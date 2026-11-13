<?php

namespace App\Http\Requests\Teacher;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates moving a quiz question one place up or down.
 */
class UpdateQuizQuestionPositionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * Authorization runs before validation through the controller's #[Authorize] attributes.
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
            'direction' => ['required', Rule::in(['up', 'down'])],
        ];
    }

    /**
     * Get the custom validation messages.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'direction.required' => 'Choose whether to move the question up or down.',
            'direction.in' => 'Choose whether to move the question up or down.',
        ];
    }

    /**
     * Determine whether the question moves towards the start of the quiz.
     */
    public function movesUp(): bool
    {
        return $this->validated('direction') === 'up';
    }
}
