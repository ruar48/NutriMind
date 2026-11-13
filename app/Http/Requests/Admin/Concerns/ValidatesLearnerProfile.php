<?php

namespace App\Http\Requests\Admin\Concerns;

use App\Enums\GradeLevel;
use App\Enums\Sex;
use App\Models\Classroom;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Validation\Rule;

/**
 * Rules shared by the admin learner forms: profile fields and the classrooms a learner can be enrolled in.
 *
 * Only classrooms that are not archived and belong to the active school year can be chosen ("enrollable"
 * classrooms); enrollments in archived or past classrooms are never changed by these forms.
 */
trait ValidatesLearnerProfile
{
    use ValidatesAccountIdentifiers;

    /**
     * The enrollable classrooms, loaded once per request.
     *
     * @var EloquentCollection<int, Classroom>|null
     */
    private ?EloquentCollection $loadedEnrollableClassrooms = null;

    /**
     * Get the classrooms the admin learner forms can enroll learners in: not archived, in the active school year,
     * with subject and teacher loaded, ordered by grade, name and id.
     *
     * @return EloquentCollection<int, Classroom>
     */
    public static function enrollableClassrooms(): EloquentCollection
    {
        return Classroom::query()
            ->active()
            ->whereHas('schoolYear', fn (Builder $query): Builder => $query->active())
            ->with(['subject', 'teacher'])
            ->orderBy('grade_level')
            ->orderBy('name')
            ->orderBy('id')
            ->get();
    }

    /**
     * Get the enrollable classrooms of this request (one query).
     *
     * @return EloquentCollection<int, Classroom>
     */
    public function enrollable(): EloquentCollection
    {
        return $this->loadedEnrollableClassrooms ??= static::enrollableClassrooms();
    }

    /**
     * Get the enrollable classrooms the form selected (call after validation).
     *
     * @return EloquentCollection<int, Classroom>
     */
    public function selectedClassrooms(): EloquentCollection
    {
        $selectedIds = array_map(intval(...), array_values((array) $this->input('classroom_ids', [])));

        return $this->enrollable()->filter(fn (Classroom $classroom): bool => in_array($classroom->id, $selectedIds, true))->values();
    }

    /**
     * Normalize the learner fields before validation: squish the name and drop spaces and dashes inside the LRN.
     */
    protected function normalizeLearnerInput(): void
    {
        $this->squishInput('name');

        $lrn = $this->input('lrn');

        if (is_string($lrn)) {
            $this->merge(['lrn' => preg_replace('/[\s-]+/', '', $lrn)]);
        }
    }

    /**
     * Get the profile and enrollment rules; the given learner is ignored by the uniqueness checks.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    protected function learnerRules(?User $student = null): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'lrn' => ['nullable', 'string', 'digits:12', Rule::unique('student_profiles', 'lrn')->ignore($student?->studentProfile?->getKey())],
            'grade_level' => ['required', Rule::enum(GradeLevel::class)],
            'sex' => ['nullable', Rule::enum(Sex::class)],
            'birthdate' => ['nullable', 'date', 'before:today'],
            'classroom_ids' => ['nullable', 'array'],
            'classroom_ids.*' => ['integer', 'distinct', Rule::in($this->enrollable()->modelKeys())],
        ];
    }

    /**
     * Get the username rules: ASCII letters, digits, dashes and underscores, unique ignoring case.
     *
     * @return list<mixed>
     */
    protected function usernameRules(bool $required, ?User $student = null): array
    {
        return [
            $required ? 'required' : 'nullable',
            'string',
            'alpha_dash:ascii',
            'max:30',
            $this->uniqueAccountIdentifier('username', $student),
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
            'name' => 'full name',
            'lrn' => 'LRN',
            'grade_level' => 'grade level',
            'classroom_ids' => 'classrooms',
            'classroom_ids.*' => 'classroom',
        ];
    }

    /**
     * Get the messages that read better than the defaults.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'classroom_ids.*.in' => 'Choose classrooms of the current school year that are not archived.',
            'classroom_ids.*.integer' => 'Choose classrooms of the current school year that are not archived.',
            'classroom_ids.*.distinct' => 'Each classroom can be chosen only once.',
        ];
    }

    /**
     * Describe the selected classrooms whose grade differs from the learner's grade, or null when all match.
     *
     * @param  EloquentCollection<int, Classroom>  $classrooms
     */
    protected function gradeMismatchMessage(EloquentCollection $classrooms, GradeLevel $grade): ?string
    {
        $mismatched = $classrooms->reject(fn (Classroom $classroom): bool => $classroom->grade_level === $grade);

        if ($mismatched->isEmpty()) {
            return null;
        }

        return trans_choice(
            ':classrooms is not a :grade classroom. Choose :grade classrooms only.|:classrooms are not :grade classrooms. Choose :grade classrooms only.',
            $mismatched->count(),
            ['classrooms' => $mismatched->pluck('name')->join(', ', ' and '), 'grade' => $grade->label()],
        );
    }
}
