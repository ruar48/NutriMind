<?php

namespace App\Http\Requests\Teacher;

use App\Enums\FeedbackType;
use App\Enums\UserRole;
use App\Models\User;
use App\Services\Monitoring\FeedbackTargets;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Validates new learner feedback.
 *
 * The learner must be a learner account; whether it is one of the teacher's learners is checked by the controller
 * after validation (Gate giveFeedbackTo → 404), so the classroom and attempt checks only run for the teacher's own
 * learners and never reveal anything about other learners.
 */
class StoreFeedbackRequest extends FormRequest
{
    /**
     * The longest feedback message.
     */
    public const MESSAGE_MAX_LENGTH = 1000;

    /**
     * Determine if the user is authorized to make this request (the controller's #[Authorize] covers it).
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
            'student_id' => [
                'required',
                'integer',
                Rule::exists('users', 'id')->where('role', UserRole::Student->value),
            ],
            ...$this->feedbackRules(),
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
            'student_id.required' => 'Choose the learner this feedback is for.',
            'student_id.integer' => 'Choose the learner this feedback is for.',
            'student_id.exists' => 'Choose one of your learners.',
            'classroom_id.integer' => 'Choose one of your classrooms this learner is enrolled in.',
            'quiz_attempt_id.integer' => 'Choose one of this learner’s attempts at your quizzes.',
            'type.required' => 'Choose the kind of feedback.',
            'type.enum' => 'Choose the kind of feedback.',
            'message.required' => 'Write a message for the learner.',
            'message.string' => 'Write a message for the learner.',
            'message.max' => 'Keep the message to :max characters or fewer.',
            'is_visible_to_guardians.boolean' => 'Choose whether parents and guardians can read this feedback.',
        ];
    }

    /**
     * Get the "after" validation callables: the classroom and attempt must belong to the learner and the teacher.
     *
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $student = $this->feedbackStudent();

                if ($student === null || $validator->errors()->has('student_id') || ! $this->checksTargetsOf($student)) {
                    return;
                }

                $targets = app(FeedbackTargets::class);
                $teacher = $this->user();

                if (filled($this->input('classroom_id')) && ! $validator->errors()->has('classroom_id')
                    && ! $targets->allowsClassroom($teacher, $student, (int) $this->input('classroom_id'))) {
                    $validator->errors()->add('classroom_id', 'Choose one of your classrooms this learner is enrolled in.');
                }

                if (filled($this->input('quiz_attempt_id')) && ! $validator->errors()->has('quiz_attempt_id')
                    && ! $targets->allowsAttempt($teacher, $student, (int) $this->input('quiz_attempt_id'))) {
                    $validator->errors()->add('quiz_attempt_id', 'Choose one of this learner’s attempts at your quizzes.');
                }
            },
        ];
    }

    /**
     * Get the rules shared with UpdateFeedbackRequest.
     *
     * @return array<string, array<mixed>>
     */
    protected function feedbackRules(): array
    {
        return [
            'classroom_id' => ['nullable', 'integer'],
            'quiz_attempt_id' => ['nullable', 'integer'],
            'type' => ['required', Rule::enum(FeedbackType::class)],
            'message' => ['required', 'string', 'max:'.self::MESSAGE_MAX_LENGTH],
            'is_visible_to_guardians' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * Get the learner the feedback is about.
     */
    protected function feedbackStudent(): ?User
    {
        $studentId = $this->input('student_id');

        if (! is_scalar($studentId) || ! ctype_digit((string) $studentId)) {
            return null;
        }

        return User::query()->ofRole(UserRole::Student)->find((int) $studentId);
    }

    /**
     * Determine whether the classroom and attempt checks apply: only for the teacher's own learners (anyone else is
     * answered with 404 by the controller).
     */
    protected function checksTargetsOf(User $student): bool
    {
        return $this->user()->can('viewAsTeacher', $student);
    }
}
