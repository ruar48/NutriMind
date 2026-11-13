<?php

namespace App\Http\Requests\Admin;

use App\Enums\GradeLevel;
use App\Models\Subject;
use App\Services\Analytics\AnalyticsPeriod;
use App\Services\Analytics\PeriodOptions;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\Attributes\RedirectToRoute;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * The filters of the school analytics page. Invalid filters send the administrator back to the unfiltered page.
 */
#[RedirectToRoute('admin.analytics.index')]
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
        return [
            'grade' => ['nullable', 'integer', Rule::enum(GradeLevel::class)],
            'subject' => ['nullable', 'integer', Rule::exists(Subject::class, 'id')],
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
            'grade.integer' => 'Choose Grade 5 or Grade 6.',
            'grade.enum' => 'Choose Grade 5 or Grade 6.',
            'subject.integer' => 'Choose a subject from the list.',
            'subject.exists' => 'Choose a subject from the list.',
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
     * Get the chosen grade, or null for every grade.
     */
    public function grade(): ?GradeLevel
    {
        $grade = $this->validated('grade');

        return $grade === null ? null : GradeLevel::from((int) $grade);
    }

    /**
     * Get the chosen subject id, or null for every subject.
     */
    public function subjectId(): ?int
    {
        $subject = $this->validated('subject');

        return $subject === null ? null : (int) $subject;
    }
}
