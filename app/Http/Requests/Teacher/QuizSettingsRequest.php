<?php

namespace App\Http\Requests\Teacher;

use App\Enums\GradeLevel;
use App\Models\AcademicTerm;
use App\Models\Classroom;
use App\Models\SchoolYear;
use App\Models\User;
use Carbon\CarbonInterface;
use Carbon\Exceptions\InvalidFormatException;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validation shared by the quiz settings forms (create and edit).
 */
abstract class QuizSettingsRequest extends FormRequest
{
    /**
     * Memoized classroom ids the quiz may be assigned to.
     *
     * @var list<int>|null
     */
    private ?array $allowedClassroomIds = null;

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
     * Get the subject ids the quiz may use.
     *
     * @return list<int>
     */
    abstract protected function allowedSubjectIds(): array;

    /**
     * Get the quarter ids the quiz may use.
     *
     * @return list<int>
     */
    protected function allowedTermIds(): array
    {
        $schoolYear = SchoolYear::current();

        return $schoolYear === null
            ? []
            : AcademicTerm::query()->whereBelongsTo($schoolYear)->pluck('id')->map(fn (mixed $id): int => (int) $id)->all();
    }

    /**
     * Get the subject the quiz will have once saved, when the input names a valid one.
     */
    protected function effectiveSubjectId(): ?int
    {
        $subjectId = filter_var($this->input('subject_id'), FILTER_VALIDATE_INT);

        return $subjectId !== false && in_array($subjectId, $this->allowedSubjectIds(), true) ? $subjectId : null;
    }

    /**
     * Get the grade level the quiz will have once saved, when the input names a valid one.
     */
    protected function effectiveGradeLevel(): ?GradeLevel
    {
        $grade = filter_var($this->input('grade_level'), FILTER_VALIDATE_INT);

        return $grade === false ? null : GradeLevel::tryFrom($grade);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $subjectId = $this->effectiveSubjectId();
        $grade = $this->effectiveGradeLevel();
        $opensAt = $this->parsedDate('available_from');

        return [
            'title' => ['required', 'string', 'max:150'],
            'subject_id' => ['required', 'integer', Rule::in($this->allowedSubjectIds())],
            'grade_level' => ['required', Rule::enum(GradeLevel::class)],
            'learning_module_id' => [
                'nullable',
                'integer',
                Rule::exists('learning_modules', 'id')
                    ->where('subject_id', $subjectId ?? 0)
                    ->where('grade_level', $grade->value ?? 0),
            ],
            'academic_term_id' => ['nullable', 'integer', Rule::in($this->allowedTermIds())],
            'instructions' => ['nullable', 'string', 'max:2000'],
            'time_limit_minutes' => ['nullable', 'integer', 'between:1,180'],
            'passing_score' => ['required', 'integer', 'between:1,100'],
            'max_attempts' => ['nullable', 'integer', 'between:1,10'],
            'shuffle_questions' => ['nullable', 'boolean'],
            'show_correct_answers' => ['nullable', 'boolean'],
            'classroom_ids' => ['nullable', 'array', 'list'],
            'classroom_ids.*' => ['integer', 'distinct', Rule::in($this->allowedClassroomIds())],
            'available_from' => ['nullable', 'date'],
            'due_at' => array_values(array_filter([
                'nullable',
                'date',
                $opensAt instanceof CarbonInterface ? 'after_or_equal:available_from' : null,
            ])),
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
            'learning_module_id.exists' => 'Choose a learning module of the selected subject and grade.',
            'academic_term_id.in' => 'Choose a quarter of the current school year.',
            'classroom_ids.list' => 'Choose the classrooms from the list.',
            'classroom_ids.*.integer' => 'Choose the classrooms from the list.',
            'classroom_ids.*.in' => 'Choose only your active classrooms of the selected subject and grade.',
            'classroom_ids.*.distinct' => 'Each classroom can be chosen only once.',
            'due_at.after_or_equal' => 'The due date must be on or after the date the quiz opens.',
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
            'learning_module_id' => 'learning module',
            'academic_term_id' => 'quarter',
            'time_limit_minutes' => 'time limit',
            'passing_score' => 'passing score',
            'max_attempts' => 'attempts allowed',
            'shuffle_questions' => 'shuffle questions',
            'show_correct_answers' => 'show correct answers',
            'classroom_ids' => 'classrooms',
            'available_from' => 'opening date',
            'due_at' => 'due date',
        ];
    }

    /**
     * Get the validated quiz attributes to save.
     *
     * @return array<string, mixed>
     */
    public function quizAttributes(): array
    {
        $validated = $this->validated();
        $integerOrNull = fn (string $key): ?int => isset($validated[$key]) ? (int) $validated[$key] : null;

        $attributes = [
            'title' => $validated['title'],
            'learning_module_id' => $integerOrNull('learning_module_id'),
            'academic_term_id' => $integerOrNull('academic_term_id'),
            'instructions' => $validated['instructions'] ?? null,
            'time_limit_minutes' => $integerOrNull('time_limit_minutes'),
            'max_attempts' => $integerOrNull('max_attempts'),
            'shuffle_questions' => $this->boolean('shuffle_questions'),
            'show_correct_answers' => $this->boolean('show_correct_answers'),
        ];

        if (isset($validated['subject_id'])) {
            $attributes['subject_id'] = (int) $validated['subject_id'];
        }

        if (isset($validated['grade_level'])) {
            $attributes['grade_level'] = GradeLevel::from((int) $validated['grade_level']);
        }

        if (isset($validated['passing_score'])) {
            $attributes['passing_score'] = (int) $validated['passing_score'];
        }

        return $attributes;
    }

    /**
     * Get the validated ids of the classrooms selected in the form.
     *
     * @return list<int>
     */
    public function classroomIds(): array
    {
        return array_values(array_map(fn (mixed $id): int => (int) $id, $this->validated('classroom_ids') ?? []));
    }

    /**
     * Get the availability window stored on every selected classroom assignment.
     *
     * @return array{available_from: ?CarbonInterface, due_at: ?CarbonInterface}
     */
    public function availabilityWindow(): array
    {
        return [
            'available_from' => $this->parsedDate('available_from'),
            'due_at' => $this->parsedDate('due_at'),
        ];
    }

    /**
     * Get the ids of the teacher's active classrooms of the quiz's subject and grade.
     *
     * @return list<int>
     */
    protected function allowedClassroomIds(): array
    {
        $subjectId = $this->effectiveSubjectId();
        $grade = $this->effectiveGradeLevel();

        if ($subjectId === null || $grade === null) {
            return [];
        }

        return $this->allowedClassroomIds ??= Classroom::query()
            ->accessibleBy($this->teacher())
            ->active()
            ->where('subject_id', $subjectId)
            ->where('grade_level', $grade)
            ->pluck('id')
            ->map(fn (mixed $id): int => (int) $id)
            ->all();
    }

    /**
     * Parse a date field, or return null when it is empty or not a date.
     */
    protected function parsedDate(string $key): ?CarbonInterface
    {
        $value = $this->input($key);

        if (! is_string($value) || trim($value) === '' || strtotime($value) === false) {
            return null;
        }

        try {
            return $this->date($key);
        } catch (InvalidFormatException) {
            return null;
        }
    }

    /**
     * Get the signed-in teacher.
     */
    protected function teacher(): User
    {
        return $this->user();
    }
}
