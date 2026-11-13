<?php

namespace App\Http\Requests\Teacher;

use App\Models\LessonPlan;
use App\Services\Content\LessonPlanAlignmentOptions;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates the activities, quizzes and missions aligned to a lesson plan: every id must come from the content
 * the alignment editor offers for the plan (LessonPlanAlignmentOptions).
 */
class UpdateLessonPlanAlignmentRequest extends FormRequest
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
    public function rules(LessonPlanAlignmentOptions $options): array
    {
        /** @var LessonPlan $lessonPlan */
        $lessonPlan = $this->route('lesson_plan');

        return [
            'activities' => ['nullable', 'array'],
            'activities.*' => ['integer', 'distinct', Rule::in($options->activities($lessonPlan)->modelKeys())],
            'quizzes' => ['nullable', 'array'],
            'quizzes.*' => ['integer', 'distinct', Rule::in($options->quizzes($lessonPlan)->modelKeys())],
            'missions' => ['nullable', 'array'],
            'missions.*' => ['integer', 'distinct', Rule::in($options->missions($lessonPlan)->modelKeys())],
        ];
    }

    /**
     * Get the field names used in validation messages.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'activities.*' => 'activity',
            'quizzes.*' => 'quiz',
            'missions.*' => 'mission',
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
            'activities.*.in' => 'Choose activities from published modules of this lesson plan\'s subject and grade.',
            'quizzes.*.in' => 'Choose your own quizzes for this lesson plan\'s subject and grade.',
            'missions.*.in' => 'Choose active missions for this lesson plan\'s subject and grade.',
        ];
    }
}
