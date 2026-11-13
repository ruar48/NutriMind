<?php

namespace App\Http\Requests\Admin;

use App\Enums\GradeLevel;
use App\Http\Requests\Admin\Concerns\ValidatesLearnerProfile;
use App\Models\Classroom;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateStudentRequest extends FormRequest
{
    use ValidatesLearnerProfile;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        $this->normalizeLearnerInput();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            ...$this->learnerRules($this->student()),
            'username' => $this->usernameRules(required: true, student: $this->student()),
        ];
    }

    /**
     * Get the "after" validation callables for the request.
     *
     * A grade change is refused while the learner stays enrolled in classrooms of another grade: ticked classrooms
     * of this form (untick them to remove the learner) or non-archived classrooms of past school years, which this
     * form never changes.
     *
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->hasAny(['grade_level', 'classroom_ids', 'classroom_ids.*'])) {
                    return;
                }

                $student = $this->student();
                $grade = GradeLevel::from((int) $this->input('grade_level'));
                $selected = $this->selectedClassrooms();

                if ($student->studentProfile !== null && $student->studentProfile->grade_level !== $grade) {
                    $message = $this->gradeChangeConflict($student, $grade, $selected);

                    if ($message !== null) {
                        $validator->errors()->add('grade_level', $message);

                        return;
                    }
                }

                $message = $this->gradeMismatchMessage($selected, $grade);

                if ($message !== null) {
                    $validator->errors()->add('classroom_ids', $message);
                }
            },
        ];
    }

    /**
     * Get the learner being updated.
     */
    private function student(): User
    {
        return $this->route('student');
    }

    /**
     * Describe why the learner cannot move to the grade, or null when nothing conflicts.
     *
     * @param  EloquentCollection<int, Classroom>  $selected
     */
    private function gradeChangeConflict(User $student, GradeLevel $grade, EloquentCollection $selected): ?string
    {
        $enrollableIds = $this->enrollable()->modelKeys();
        $currentClassrooms = $student->enrolledClassrooms()->active()->with('schoolYear')->orderBy('name')->orderBy('classrooms.id')->get();

        $pastConflicts = $currentClassrooms->filter(
            fn (Classroom $classroom): bool => ! in_array($classroom->id, $enrollableIds, true) && $classroom->grade_level !== $grade,
        );

        if ($pastConflicts->isNotEmpty()) {
            return trans_choice(
                ':name is still enrolled in :classrooms, which is not archived. Archive it or remove the learner from it before changing the grade.|:name is still enrolled in :classrooms, which are not archived. Archive them or remove the learner from them before changing the grade.',
                $pastConflicts->count(),
                [
                    'name' => $student->name,
                    'classrooms' => $pastConflicts->map(fn (Classroom $classroom): string => $classroom->name.' ('.$classroom->schoolYear->name.')')->join(', ', ' and '),
                ],
            );
        }

        $tickedConflicts = $selected->filter(
            fn (Classroom $classroom): bool => $currentClassrooms->contains($classroom) && $classroom->grade_level !== $grade,
        );

        if ($tickedConflicts->isEmpty()) {
            return null;
        }

        return trans_choice(
            ':grade doesn\'t match :classrooms, where :name is enrolled. Untick that classroom or keep :current.|:grade doesn\'t match :classrooms, where :name is enrolled. Untick those classrooms or keep :current.',
            $tickedConflicts->count(),
            [
                'grade' => $grade->label(),
                'classrooms' => $tickedConflicts->pluck('name')->join(', ', ' and '),
                'name' => $student->name,
                'current' => $student->studentProfile->grade_level->label(),
            ],
        );
    }
}
