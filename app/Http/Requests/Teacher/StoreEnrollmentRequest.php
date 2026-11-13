<?php

namespace App\Http\Requests\Teacher;

use App\Enums\UserRole;
use App\Models\Classroom;
use App\Models\User;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Http\FormRequest;

class StoreEnrollmentRequest extends FormRequest
{
    /**
     * The most learners one request may enroll.
     */
    public const MAX_LEARNERS = 50;

    /**
     * The submitted learner IDs that may join the classroom, looked up once per request.
     *
     * @var list<int>|null
     */
    private ?array $eligibleStudentIds = null;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules: every chosen ID must be an active learner of the classroom's grade who is not enrolled yet.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'student_ids' => ['required', 'array', 'max:'.self::MAX_LEARNERS],
            'student_ids.*' => [
                'bail',
                'integer',
                'distinct',
                function (string $attribute, mixed $value, Closure $fail): void {
                    if (! in_array((int) $value, $this->eligibleStudentIds(), true)) {
                        $fail('Only active '.$this->classroom()->grade_level->label().' learners who are not in this class yet can be enrolled.');
                    }
                },
            ],
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
            'student_ids.required' => 'Choose at least one learner to enroll.',
            'student_ids.array' => 'Choose the learners to enroll from the search results.',
            'student_ids.max' => 'You can enroll at most '.self::MAX_LEARNERS.' learners at a time.',
            'student_ids.*.integer' => 'Choose the learners to enroll from the search results.',
            'student_ids.*.distinct' => 'Each learner can be chosen only once.',
        ];
    }

    /**
     * Get the classroom learners are enrolled in.
     */
    public function classroom(): Classroom
    {
        return $this->route('classroom');
    }

    /**
     * Get the submitted IDs of active learners of the classroom's grade who are not enrolled in it yet.
     *
     * @return list<int>
     */
    private function eligibleStudentIds(): array
    {
        if ($this->eligibleStudentIds !== null) {
            return $this->eligibleStudentIds;
        }

        $classroom = $this->classroom();
        $submittedIds = collect((array) $this->input('student_ids', []))
            ->filter(fn (mixed $id): bool => is_int($id) || (is_string($id) && ctype_digit($id)))
            ->map(fn (int|string $id): int => (int) $id)
            ->unique()
            ->values()
            ->all();

        return $this->eligibleStudentIds = User::query()
            ->ofRole(UserRole::Student)
            ->active()
            ->whereKey($submittedIds)
            ->whereHas('studentProfile', fn (Builder $profile) => $profile->where('grade_level', $classroom->grade_level))
            ->whereDoesntHave('enrolledClassrooms', fn (Builder $classrooms) => $classrooms->whereKey($classroom->id))
            ->pluck('id')
            ->map(fn (mixed $id): int => (int) $id)
            ->all();
    }
}
