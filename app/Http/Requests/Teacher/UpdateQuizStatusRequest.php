<?php

namespace App\Http\Requests\Teacher;

use App\Enums\QuizStatus;
use App\Models\Quiz;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Validates a quiz status change: publish, close, reopen or move back to draft.
 *
 * Draft → Published and Closed → Published need at least one question, at least one active classroom and a review
 * after the last question change. Published → Closed. Published or Closed → Draft only while nobody attempted it.
 */
class UpdateQuizStatusRequest extends FormRequest
{
    /**
     * The message shown when a quiz without questions would be published.
     */
    public const NEEDS_QUESTION_MESSAGE = 'Add at least one question before publishing.';

    /**
     * The message shown when a quiz without an active classroom would be published.
     */
    public const NEEDS_CLASSROOM_MESSAGE = 'Assign the quiz to at least one classroom before publishing.';

    /**
     * The message shown when a quiz that was not reviewed since its last change would be published.
     */
    public const NEEDS_REVIEW_MESSAGE = 'Review the questions before publishing. Any change to a question asks for a new review.';

    /**
     * The message shown when a draft would be closed.
     */
    public const CLOSE_DRAFT_MESSAGE = 'Only a published quiz can be closed.';

    /**
     * The message shown when an attempted quiz would go back to draft.
     */
    public const DRAFT_WITH_ATTEMPTS_MESSAGE = 'Learners have already taken this quiz, so it cannot go back to draft. Close it instead.';

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
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'status' => ['required', Rule::enum(QuizStatus::class)],
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
            'status.required' => 'Choose the new status of the quiz.',
            'status.enum' => 'Choose a valid quiz status.',
        ];
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
                $target = $this->targetStatus();

                if ($target === null || $validator->errors()->has('status')) {
                    return;
                }

                foreach ($this->transitionProblems($this->quiz(), $target) as $problem) {
                    $validator->errors()->add('status', $problem);
                }
            },
        ];
    }

    /**
     * Get the status the quiz moves to.
     */
    public function targetStatus(): ?QuizStatus
    {
        return $this->enum('status', QuizStatus::class);
    }

    /**
     * Get the quiz whose status changes.
     */
    public function quiz(): Quiz
    {
        return $this->route('quiz');
    }

    /**
     * Get every reason the quiz cannot move to the target status.
     *
     * @return list<string>
     */
    private function transitionProblems(Quiz $quiz, QuizStatus $target): array
    {
        if ($quiz->status === $target) {
            return [$target === QuizStatus::Draft ? 'This quiz is already a draft.' : 'This quiz is already '.strtolower($target->label()).'.'];
        }

        return match ($target) {
            QuizStatus::Published => array_values(array_filter([
                $quiz->questions()->reorder()->doesntExist() ? self::NEEDS_QUESTION_MESSAGE : null,
                $quiz->classrooms()->active()->doesntExist() ? self::NEEDS_CLASSROOM_MESSAGE : null,
                $quiz->isReviewed() ? null : self::NEEDS_REVIEW_MESSAGE,
            ])),
            QuizStatus::Closed => $quiz->isDraft() ? [self::CLOSE_DRAFT_MESSAGE] : [],
            QuizStatus::Draft => $quiz->attempts()->exists() ? [self::DRAFT_WITH_ATTEMPTS_MESSAGE] : [],
        };
    }
}
