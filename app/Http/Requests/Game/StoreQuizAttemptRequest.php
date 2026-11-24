<?php

namespace App\Http\Requests\Game;

use App\Models\Quiz;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Validates a quiz the learner answered in the game.
 *
 * Payload: answers (a list of {question_id, answer}; questions left out count as unanswered), and optionally
 * started_at, submitted_at (for an attempt finished offline and uploaded later), duration_seconds and is_offline.
 * A learner who already used the quiz's maximum number of attempts is refused.
 */
class StoreQuizAttemptRequest extends FormRequest
{
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
            'answers' => ['present', 'array', 'max:500'],
            'answers.*' => ['array:question_id,answer'],
            'answers.*.question_id' => ['required', 'integer', 'distinct', Rule::exists('quiz_questions', 'id')->where('quiz_id', $this->quiz()->id)],
            'answers.*.answer' => ['nullable', 'string', 'max:500'],
            'started_at' => ['nullable', 'date', 'before_or_equal:now'],
            'submitted_at' => ['nullable', 'date', 'before_or_equal:now', Rule::when($this->filled('started_at'), ['after_or_equal:started_at'])],
            'duration_seconds' => ['nullable', 'integer', 'between:0,86400'],
            'is_offline' => ['nullable', 'boolean'],
        ];
    }

    /**
     * Refuse the attempt once the learner used every attempt the quiz allows.
     *
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $quiz = $this->quiz();

                if ($quiz->max_attempts === null) {
                    return;
                }

                $usedAttempts = $quiz->attempts()->where('student_id', $this->user()->getKey())->submitted()->count();

                if ($usedAttempts >= $quiz->max_attempts) {
                    $validator->errors()->add('quiz', "You have used all {$quiz->max_attempts} attempts for this quiz.");
                }
            },
        ];
    }

    /**
     * Get the field names used in validation messages.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'answers.*.question_id' => 'question',
            'answers.*.answer' => 'answer',
            'started_at' => 'start time',
            'submitted_at' => 'submit time',
            'duration_seconds' => 'time spent',
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
            'answers.*.question_id.exists' => 'This question is not part of the quiz.',
            'answers.*.question_id.distinct' => 'Each question can be answered only once.',
        ];
    }

    /**
     * Get the quiz being answered (already limited to the learner's available quizzes by the route).
     */
    public function quiz(): Quiz
    {
        /** @var Quiz */
        return $this->route('gameQuiz');
    }

    /**
     * Get each answer keyed by its quiz question ID.
     *
     * @return array<int, string|null>
     */
    public function answersByQuestion(): array
    {
        return collect($this->validated('answers'))
            ->mapWithKeys(fn (array $answer): array => [(int) $answer['question_id'] => $answer['answer'] ?? null])
            ->all();
    }

    /**
     * Get the moment the learner started, when the game sent it.
     */
    public function startedAt(): ?Carbon
    {
        return $this->filled('started_at') ? $this->date('started_at') : null;
    }

    /**
     * Get the moment the learner submitted, when the game sent it (an offline attempt uploaded later).
     */
    public function submittedAt(): ?Carbon
    {
        return $this->filled('submitted_at') ? $this->date('submitted_at') : null;
    }
}
