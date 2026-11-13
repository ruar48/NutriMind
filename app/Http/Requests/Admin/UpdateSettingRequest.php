<?php

namespace App\Http\Requests\Admin;

use App\Enums\LearningMode;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSettingRequest extends FormRequest
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
            'school_name' => ['required', 'string', 'max:150'],
            'school_id' => ['nullable', 'digits_between:6,10'],
            'school_address' => ['nullable', 'string', 'max:255'],
            'school_head' => ['nullable', 'string', 'max:150'],
            'school_email' => ['nullable', 'string', 'email', 'max:255'],
            'school_phone' => ['nullable', 'string', 'max:30'],
            'default_passing_score' => ['required', 'integer', 'between:50,100'],
            'default_max_attempts' => ['nullable', 'integer', 'between:1,10'],
            'default_learning_mode' => ['required', Rule::enum(LearningMode::class)],
            'points_per_level' => ['required', 'integer', 'between:50,10000'],
            'max_level' => ['required', 'integer', 'between:5,200'],
            'forum_enabled' => ['nullable', 'boolean'],
            'forum_requires_approval' => ['nullable', 'boolean'],
            'announcement' => ['nullable', 'string', 'max:500'],
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
            'school_id.digits_between' => 'The DepEd School ID must be 6 to 10 digits.',
            'default_learning_mode.enum' => 'Choose one of the learning modes.',
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
            'school_name' => 'school name',
            'school_id' => 'DepEd School ID',
            'school_address' => 'school address',
            'school_head' => 'school head',
            'school_email' => 'school email',
            'school_phone' => 'school phone',
            'default_passing_score' => 'passing score',
            'default_max_attempts' => 'attempts allowed',
            'default_learning_mode' => 'learning mode',
            'points_per_level' => 'points per level',
            'max_level' => 'highest level',
            'forum_enabled' => 'parent forum',
            'forum_requires_approval' => 'post approval',
            'announcement' => 'announcement',
        ];
    }

    /**
     * Get the validated values as typed settings, ready for Settings::setMany().
     *
     * @return array{school_name: string, school_id: ?string, school_address: ?string, school_head: ?string, school_email: ?string, school_phone: ?string, default_passing_score: int, default_max_attempts: ?int, default_learning_mode: string, points_per_level: int, max_level: int, forum_enabled: bool, forum_requires_approval: bool, announcement: ?string}
     */
    public function settings(): array
    {
        $validated = $this->validated();

        return [
            'school_name' => (string) $validated['school_name'],
            'school_id' => $this->nullableString($validated['school_id'] ?? null),
            'school_address' => $this->nullableString($validated['school_address'] ?? null),
            'school_head' => $this->nullableString($validated['school_head'] ?? null),
            'school_email' => $this->nullableString($validated['school_email'] ?? null),
            'school_phone' => $this->nullableString($validated['school_phone'] ?? null),
            'default_passing_score' => (int) $validated['default_passing_score'],
            'default_max_attempts' => isset($validated['default_max_attempts']) ? (int) $validated['default_max_attempts'] : null,
            'default_learning_mode' => LearningMode::from($validated['default_learning_mode'])->value,
            'points_per_level' => (int) $validated['points_per_level'],
            'max_level' => (int) $validated['max_level'],
            'forum_enabled' => $this->boolean('forum_enabled'),
            'forum_requires_approval' => $this->boolean('forum_requires_approval'),
            'announcement' => $this->nullableString($validated['announcement'] ?? null),
        ];
    }

    /**
     * Turn a validated optional text value into a string, or null when it is empty.
     */
    private function nullableString(mixed $value): ?string
    {
        return filled($value) ? (string) $value : null;
    }
}
