<?php

namespace App\Http\Requests\Teacher;

use App\Enums\GradeLevel;
use App\Enums\LearningMode;
use App\Http\Requests\Teacher\Concerns\ValidatesClassroomSection;
use App\Models\SchoolYear;
use App\Models\Subject;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreClassroomRequest extends FormRequest
{
    use ValidatesClassroomSection;

    /**
     * The error shown when no school year is active, so a classroom has no school year to belong to.
     */
    public const NO_ACTIVE_SCHOOL_YEAR_MESSAGE = 'There is no active school year yet. Ask your school administrator to activate one before you create classrooms.';

    /**
     * The active school year, looked up once per request.
     */
    private ?SchoolYear $activeSchoolYear = null;

    /**
     * Whether the active school year was looked up already.
     */
    private bool $activeSchoolYearResolved = false;

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
            'subject_id' => ['required', 'integer', Rule::in($this->activeSubjectIds())],
            'grade_level' => ['required', Rule::enum(GradeLevel::class)],
            'section' => ['required', 'string', 'max:50'],
            'name' => ['required', 'string', 'max:100'],
            'room' => ['nullable', 'string', 'max:50'],
            'description' => ['nullable', 'string', 'max:500'],
            'learning_mode' => ['required', Rule::enum(LearningMode::class)],
        ];
    }

    /**
     * Get the "after" validation callables: an active school year must exist and the section must be free in it.
     *
     * @return list<Closure(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $schoolYear = $this->schoolYear();

                if ($schoolYear === null) {
                    $validator->errors()->add('school_year_id', self::NO_ACTIVE_SCHOOL_YEAR_MESSAGE);

                    return;
                }

                if ($validator->errors()->hasAny(['subject_id', 'grade_level', 'section'])) {
                    return;
                }

                $subject = Subject::query()->findOrFail($this->integer('subject_id'));
                $grade = GradeLevel::from($this->integer('grade_level'));
                $takenSection = $this->takenSection($schoolYear->id, $subject->id, $grade, (string) $this->string('section'));

                if ($takenSection !== null) {
                    $validator->errors()->add('section', $this->duplicateSectionMessage($subject, $grade, $takenSection));
                }
            },
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
            'subject_id.in' => 'Choose one of the active subjects assigned to you.',
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
            'learning_mode' => 'learning mode',
        ];
    }

    /**
     * Get the active school year the new classroom belongs to.
     */
    public function schoolYear(): ?SchoolYear
    {
        if (! $this->activeSchoolYearResolved) {
            $this->activeSchoolYear = SchoolYear::current();
            $this->activeSchoolYearResolved = true;
        }

        return $this->activeSchoolYear;
    }

    /**
     * Get the IDs of the active subjects assigned to the teacher.
     *
     * @return list<int>
     */
    private function activeSubjectIds(): array
    {
        return $this->user()->assignedSubjects()
            ->active()
            ->pluck('subjects.id')
            ->map(fn (mixed $subjectId): int => (int) $subjectId)
            ->all();
    }
}
