<?php

namespace App\Http\Requests\Admin;

use App\Enums\GradeLevel;
use App\Services\Content\CompetencyCode;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;

class StoreLearningModuleRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Normalize the competency code (trimmed and uppercased) before validating it.
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('competency_code')) {
            $this->merge(['competency_code' => CompetencyCode::normalize($this->input('competency_code'))]);
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'subject_id' => ['required', 'integer', $this->subjectRule()],
            'grade_level' => ['required', Rule::enum(GradeLevel::class)],
            'quarter' => ['nullable', 'integer', 'between:1,4'],
            'title' => ['required', 'string', 'max:150'],
            'world_name' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:2000'],
            'competency_code' => ['nullable', 'string', 'max:'.CompetencyCode::MAX_LENGTH],
            'learning_competency' => ['nullable', 'string', 'max:1000'],
            'sort_order' => ['required', 'integer', 'between:0,999'],
            'is_published' => ['required', 'boolean'],
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
            'subject_id' => 'subject',
            'grade_level' => 'grade level',
            'world_name' => 'game world',
            'competency_code' => 'competency code',
            'learning_competency' => 'learning competency',
            'sort_order' => 'display order',
            'is_published' => 'published setting',
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
            'subject_id.exists' => 'Choose an active subject.',
        ];
    }

    /**
     * Get the rule the subject must pass: an active subject.
     */
    protected function subjectRule(): Exists
    {
        return Rule::exists('subjects', 'id')->where('is_active', true);
    }
}
