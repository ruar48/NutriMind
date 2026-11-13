<?php

namespace App\Http\Requests\Teacher;

use App\Models\Quiz;
use App\Models\Subject;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Arr;
use Illuminate\Validation\Validator;

/**
 * Validates changes to a quiz's settings.
 *
 * Once learners attempted the quiz, the settings that decide how attempts are scored stay as they are, and
 * classrooms with attempts stay assigned.
 */
class UpdateQuizRequest extends QuizSettingsRequest
{
    /**
     * The settings that are locked once learners attempted the quiz.
     *
     * @var list<string>
     */
    public const LOCKED_SETTINGS = ['subject_id', 'grade_level', 'passing_score', 'time_limit_minutes', 'max_attempts', 'shuffle_questions'];

    /**
     * The message shown when a published quiz would lose its last classroom.
     */
    public const PUBLISHED_NEEDS_CLASSROOM_MESSAGE = 'A published quiz needs at least one classroom. Close it or move it back to draft first.';

    /**
     * Memoized answer to whether learners attempted the quiz.
     */
    private ?bool $hasAttempts = null;

    /**
     * Get the quiz being edited.
     */
    public function quiz(): Quiz
    {
        return $this->route('quiz');
    }

    /**
     * Determine whether any learner attempted the quiz.
     */
    public function hasAttempts(): bool
    {
        return $this->hasAttempts ??= $this->quiz()->attempts()->exists();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * Locked settings may be left out of the form; when they are sent they must keep their current values.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $rules = parent::rules();

        if ($this->hasAttempts()) {
            foreach (self::LOCKED_SETTINGS as $field) {
                $rules[$field] = ['sometimes', ...array_values(array_filter($rules[$field], fn (mixed $rule): bool => $rule !== 'required'))];
            }
        }

        return $rules;
    }

    /**
     * Get the "after" validation callables for the request.
     *
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($this->hasAttempts()) {
                    $this->validateLockedSettings($validator);
                    $this->validateAttemptedClassrooms($validator);
                }

                $this->validatePublishedQuizKeepsAClassroom($validator);
            },
        ];
    }

    /**
     * Get the validated quiz attributes to save, leaving out locked settings.
     *
     * @return array<string, mixed>
     */
    public function quizAttributes(): array
    {
        $attributes = parent::quizAttributes();

        return $this->hasAttempts() ? Arr::except($attributes, self::LOCKED_SETTINGS) : $attributes;
    }

    /**
     * Get the classroom assignments to sync: the selected classrooms with the availability window, plus the quiz's
     * archived classrooms of its subject and grade, which the form does not list and which stay untouched.
     *
     * @return array<int, array<string, mixed>>
     */
    public function classroomSync(): array
    {
        $keptArchivedIds = $this->quiz()->classrooms()
            ->archived()
            ->where('classrooms.subject_id', $this->effectiveSubjectId())
            ->where('classrooms.grade_level', $this->effectiveGradeLevel())
            ->pluck('classrooms.id')
            ->map(fn (mixed $id): int => (int) $id)
            ->all();

        return array_fill_keys($this->classroomIds(), $this->availabilityWindow()) + array_fill_keys($keptArchivedIds, []);
    }

    /**
     * Get the subject ids the quiz may use: the teacher's active assigned subjects and its current subject.
     *
     * @return list<int>
     */
    protected function allowedSubjectIds(): array
    {
        $this->teacher()->assignedSubjectIds();

        return $this->teacher()->assignedSubjects
            ->filter(fn (Subject $subject): bool => $subject->is_active || $subject->is($this->quiz()->subject()->getParent()->subject ?? null) || (int) $subject->getKey() === (int) $this->quiz()->subject_id)
            ->map(fn (Subject $subject): int => (int) $subject->getKey())
            ->values()
            ->all();
    }

    /**
     * Get the quarter ids the quiz may use: the current school year's quarters and its current quarter.
     *
     * @return list<int>
     */
    protected function allowedTermIds(): array
    {
        $termIds = parent::allowedTermIds();

        if ($this->quiz()->academic_term_id !== null) {
            $termIds[] = (int) $this->quiz()->academic_term_id;
        }

        return array_values(array_unique($termIds));
    }

    /**
     * Get the subject the quiz will have once saved; it cannot change once learners attempted the quiz.
     */
    protected function effectiveSubjectId(): ?int
    {
        return $this->hasAttempts() ? (int) $this->quiz()->subject_id : parent::effectiveSubjectId();
    }

    /**
     * Get the grade level the quiz will have once saved; it cannot change once learners attempted the quiz.
     */
    protected function effectiveGradeLevel(): ?\App\Enums\GradeLevel
    {
        return $this->hasAttempts() ? $this->quiz()->grade_level : parent::effectiveGradeLevel();
    }

    /**
     * Reject changes to the settings that are locked by learner attempts.
     */
    private function validateLockedSettings(Validator $validator): void
    {
        $quiz = $this->quiz();
        $current = [
            'subject_id' => (int) $quiz->subject_id,
            'grade_level' => $quiz->grade_level->value,
            'passing_score' => $quiz->passing_score,
            'time_limit_minutes' => $quiz->time_limit_minutes,
            'max_attempts' => $quiz->max_attempts,
            'shuffle_questions' => $quiz->shuffle_questions,
        ];

        foreach ($current as $field => $value) {
            if (! $this->has($field) || $validator->errors()->has($field)) {
                continue;
            }

            $submitted = match ($field) {
                'shuffle_questions' => $this->boolean($field),
                default => $this->filled($field) ? (int) $this->input($field) : null,
            };

            if ($submitted !== $value) {
                $validator->errors()->add($field, 'Learners have already taken this quiz, so the '.$this->attributes()[$field].' cannot change.');
            }
        }
    }

    /**
     * Keep every active classroom that has learner attempts assigned.
     */
    private function validateAttemptedClassrooms(Validator $validator): void
    {
        if ($validator->errors()->has('classroom_ids') || $validator->errors()->has('classroom_ids.*')) {
            return;
        }

        $quiz = $this->quiz();
        $removed = $quiz->classrooms()
            ->active()
            ->whereIn('classrooms.id', $quiz->attempts()->whereNotNull('classroom_id')->select('classroom_id'))
            ->whereNotIn('classrooms.id', $this->classroomIds())
            ->orderBy('classrooms.name')
            ->pluck('classrooms.name')
            ->all();

        if ($removed !== []) {
            $validator->errors()->add('classroom_ids', count($removed) === 1
                ? $removed[0].' already has learner attempts, so it must stay assigned.'
                : Arr::join($removed, ', ', ' and ').' already have learner attempts, so they must stay assigned.');
        }
    }

    /**
     * Keep at least one classroom on a published quiz.
     */
    private function validatePublishedQuizKeepsAClassroom(Validator $validator): void
    {
        if (! $this->quiz()->isPublished() || $validator->errors()->has('classroom_ids') || $validator->errors()->has('classroom_ids.*')) {
            return;
        }

        if ($this->classroomIds() === []) {
            $validator->errors()->add('classroom_ids', self::PUBLISHED_NEEDS_CLASSROOM_MESSAGE);
        }
    }
}
