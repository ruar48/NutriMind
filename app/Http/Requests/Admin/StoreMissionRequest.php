<?php

namespace App\Http\Requests\Admin;

use App\Enums\Difficulty;
use App\Enums\GradeLevel;
use App\Enums\MissionType;
use App\Models\LearningModule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;
use Illuminate\Validation\Validator;

class StoreMissionRequest extends FormRequest
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
            'subject_id' => ['required', 'integer', $this->subjectRule()],
            'learning_module_id' => ['nullable', 'integer', Rule::exists('learning_modules', 'id')->where('subject_id', $this->integer('subject_id'))],
            'grade_level' => ['nullable', Rule::enum(GradeLevel::class)],
            'title' => ['required', 'string', 'max:150'],
            'description' => ['required', 'string', 'max:2000'],
            'objective' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::enum(MissionType::class)],
            'difficulty' => ['required', Rule::enum(Difficulty::class)],
            'points_reward' => ['required', 'integer', 'between:0,10000'],
            'starts_on' => ['nullable', 'date'],
            'ends_on' => ['nullable', 'date', Rule::when($this->filled('starts_on'), ['after_or_equal:starts_on'])],
            'sort_order' => ['required', 'integer', 'between:0,999'],
            'is_active' => ['required', 'boolean'],
        ];
    }

    /**
     * Check that a module chosen for a one-grade mission is for that grade.
     *
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->hasAny(['learning_module_id', 'grade_level']) || ! $this->filled('learning_module_id') || ! $this->filled('grade_level')) {
                    return;
                }

                $module = LearningModule::query()->find($this->integer('learning_module_id'));

                if ($module !== null && $module->grade_level->value !== $this->integer('grade_level')) {
                    $validator->errors()->add(
                        'learning_module_id',
                        "This module is for {$module->grade_level->label()}. Choose a module for the mission's grade, or open the mission to both grades.",
                    );
                }
            },
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
            'learning_module_id' => 'learning module',
            'grade_level' => 'grade level',
            'type' => 'mission type',
            'points_reward' => 'reward points',
            'starts_on' => 'start date',
            'ends_on' => 'end date',
            'sort_order' => 'display order',
            'is_active' => 'active setting',
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
            'learning_module_id.exists' => 'Choose a module of the mission\'s subject.',
            'ends_on.after_or_equal' => 'The end date must be on or after the start date.',
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
