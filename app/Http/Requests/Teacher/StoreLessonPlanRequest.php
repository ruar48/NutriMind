<?php

namespace App\Http\Requests\Teacher;

use App\Enums\GradeLevel;
use App\Enums\LessonPlanStatus;
use App\Models\SchoolYear;
use App\Services\Content\CompetencyCode;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;

class StoreLessonPlanRequest extends FormRequest
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
            'subject_id' => ['required', 'integer', Rule::in($this->allowedSubjectIds())],
            'grade_level' => ['required', Rule::enum(GradeLevel::class)],
            'academic_term_id' => ['nullable', 'integer', $this->termRule()],
            'learning_module_id' => ['nullable', 'integer', $this->moduleRule()],
            'title' => ['required', 'string', 'max:150'],
            'competency_code' => ['nullable', 'string', 'max:'.CompetencyCode::MAX_LENGTH],
            'learning_competency' => ['nullable', 'string', 'max:1000'],
            'objectives' => ['nullable', 'string', 'max:2000'],
            'week_number' => ['nullable', 'integer', 'between:1,12'],
            'starts_on' => ['nullable', 'date'],
            'ends_on' => ['nullable', 'date', Rule::when($this->filled('starts_on'), ['after_or_equal:starts_on'])],
            'status' => ['required', Rule::enum(LessonPlanStatus::class)],
            'notes' => ['nullable', 'string', 'max:2000'],
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
            'academic_term_id' => 'quarter',
            'learning_module_id' => 'learning module',
            'competency_code' => 'competency code',
            'learning_competency' => 'learning competency',
            'week_number' => 'week number',
            'starts_on' => 'start date',
            'ends_on' => 'end date',
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
            'subject_id.in' => 'Choose one of your active subjects.',
            'academic_term_id.exists' => 'Choose a quarter of the active school year.',
            'learning_module_id.exists' => 'Choose a published module of the same subject and grade level.',
            'ends_on.after_or_equal' => 'The end date must be on or after the start date.',
        ];
    }

    /**
     * Get the subjects the plan may use: the teacher's active assigned subjects.
     *
     * @return list<int>
     */
    protected function allowedSubjectIds(): array
    {
        return $this->user()->assignedSubjects()->active()->pluck('subjects.id')->map(fn (mixed $id): int => (int) $id)->all();
    }

    /**
     * Get the rule the quarter must pass: a term of the active school year.
     */
    protected function termRule(): Exists
    {
        $activeYearId = SchoolYear::current()?->getKey();

        return Rule::exists('academic_terms', 'id')->where(function (Builder $query) use ($activeYearId): void {
            $query->where('school_year_id', $activeYearId);
        });
    }

    /**
     * Get the rule the module must pass: a published module of the chosen subject and grade.
     */
    protected function moduleRule(): Exists
    {
        return Rule::exists('learning_modules', 'id')->where(function (Builder $query): void {
            $query->where('subject_id', $this->integer('subject_id'))
                ->where('grade_level', $this->integer('grade_level'))
                ->where('is_published', true);
        });
    }
}
