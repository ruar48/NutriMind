<?php

namespace App\Http\Requests\Teacher;

use App\Models\Classroom;
use App\Services\Analytics\AnalyticsPeriod;
use App\Services\Analytics\PeriodOptions;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\Attributes\RedirectToRoute;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * The filters of the teacher's learning analytics page.
 *
 * The classroom must be one of the teacher's accessible classrooms (archived ones included) and the subject one of
 * the teacher's assigned subjects, so forged ids are refused instead of revealing another teacher's data. Invalid
 * filters send the teacher back to the unfiltered page.
 */
#[RedirectToRoute('teacher.analytics.index')]
class IndexAnalyticsRequest extends FormRequest
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
    public function rules(PeriodOptions $periods): array
    {
        $teacher = $this->user();

        return [
            'classroom' => ['nullable', 'integer', Rule::in(Classroom::query()->accessibleBy($teacher)->pluck('id')->all())],
            'subject' => ['nullable', 'integer', Rule::in($teacher->assignedSubjectIds())],
            ...$periods->rules(),
        ];
    }

    /**
     * Get the checks that run after the rules pass.
     *
     * @return list<Closure(Validator): void>
     */
    public function after(PeriodOptions $periods): array
    {
        return [
            fn (Validator $validator) => $periods->validateCustomRange($validator, $this->input('period'), $this->input('from'), $this->input('to')),
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
            'classroom.integer' => 'Choose one of your classrooms.',
            'classroom.in' => 'Choose one of your classrooms.',
            'subject.integer' => 'Choose one of your subjects.',
            'subject.in' => 'Choose one of your subjects.',
            ...PeriodOptions::messages(),
        ];
    }

    /**
     * Get the period the validated filters select.
     */
    public function period(): AnalyticsPeriod
    {
        return app(PeriodOptions::class)->resolve($this->validated('period'), $this->validated('from'), $this->validated('to'));
    }

    /**
     * Get the chosen classroom id, or null for all of the teacher's classrooms.
     */
    public function classroomId(): ?int
    {
        $classroom = $this->validated('classroom');

        return $classroom === null ? null : (int) $classroom;
    }

    /**
     * Get the chosen subject id, or null for all of the teacher's subjects.
     */
    public function subjectId(): ?int
    {
        $subject = $this->validated('subject');

        return $subject === null ? null : (int) $subject;
    }
}
