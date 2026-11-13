<?php

namespace App\Http\Requests\Teacher;

use App\Enums\Difficulty;
use App\Enums\GradeLevel;
use App\Models\QuestionBankItem;
use App\Models\QuizQuestion;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\Rule;

/**
 * Validates a new question bank item: its question content and how it is filed in the bank.
 */
class StoreQuestionBankItemRequest extends QuestionContentRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $subjectId = filter_var($this->input('subject_id'), FILTER_VALIDATE_INT);
        $grade = filter_var($this->input('grade_level'), FILTER_VALIDATE_INT);

        return [
            ...parent::rules(),
            'subject_id' => ['required', 'integer', Rule::in($this->allowedSubjectIds())],
            'grade_level' => ['required', Rule::enum(GradeLevel::class)],
            'learning_module_id' => [
                'nullable',
                'integer',
                Rule::exists('learning_modules', 'id')
                    ->where('subject_id', $subjectId === false ? 0 : $subjectId)
                    ->where('grade_level', $grade === false ? 0 : $grade),
            ],
            'topic' => ['nullable', 'string', 'max:100'],
            'competency_code' => ['nullable', 'string', 'max:40'],
            'difficulty' => ['required', Rule::enum(Difficulty::class)],
            'is_shared' => ['nullable', 'boolean'],
            'reviewed' => ['nullable', 'boolean'],
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
            ...parent::messages(),
            'subject_id.in' => 'Choose one of your active subjects.',
            'learning_module_id.exists' => 'Choose a learning module of the selected subject and grade.',
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
            ...parent::attributes(),
            'subject_id' => 'subject',
            'grade_level' => 'grade level',
            'learning_module_id' => 'learning module',
            'competency_code' => 'competency code',
        ];
    }

    /**
     * Get the validated item attributes to save (sharing and review are decided by the controller).
     *
     * @return array<string, mixed>
     */
    public function itemAttributes(): array
    {
        $validated = $this->validated();

        return [
            ...$this->questionAttributes(),
            'subject_id' => (int) $validated['subject_id'],
            'grade_level' => GradeLevel::from((int) $validated['grade_level']),
            'learning_module_id' => isset($validated['learning_module_id']) ? (int) $validated['learning_module_id'] : null,
            'topic' => $validated['topic'] ?? null,
            'competency_code' => $validated['competency_code'] ?? null,
            'difficulty' => Difficulty::from($validated['difficulty']),
        ];
    }

    /**
     * Get the subject ids the item may use: the teacher's active assigned subjects.
     *
     * @return list<int>
     */
    protected function allowedSubjectIds(): array
    {
        return $this->assignedSubjects()
            ->where('is_active', true)
            ->map(fn (Subject $subject): int => (int) $subject->getKey())
            ->values()
            ->all();
    }

    /**
     * Get the subjects assigned to the signed-in teacher.
     *
     * @return Collection<int, Subject>
     */
    protected function assignedSubjects(): Collection
    {
        /** @var User $teacher */
        $teacher = $this->user();
        $teacher->assignedSubjectIds();

        return $teacher->assignedSubjects;
    }

    /**
     * A new item has no stored media yet.
     */
    protected function currentQuestion(): QuizQuestion|QuestionBankItem|null
    {
        return null;
    }
}
