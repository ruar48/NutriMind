<?php

namespace App\Http\Requests\Teacher;

use App\Models\QuestionBankItem;
use App\Models\QuizQuestion;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

/**
 * Validates a new question of a draft quiz.
 */
class StoreQuizQuestionRequest extends QuestionContentRequest
{
    /**
     * The fewest points a question may be worth.
     */
    public const MIN_POINTS = 1;

    /**
     * The most points a question may be worth.
     */
    public const MAX_POINTS = 10;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'points' => ['required', 'integer', 'between:'.self::MIN_POINTS.','.self::MAX_POINTS],
            'next' => ['nullable', Rule::in(['another'])],
        ];
    }

    /**
     * Get the validated question attributes to save, including its points.
     *
     * @return array<string, mixed>
     */
    public function quizQuestionAttributes(): array
    {
        return [
            ...$this->questionAttributes(),
            'points' => (int) $this->validated('points'),
        ];
    }

    /**
     * Determine whether the teacher asked to write another question right after saving this one.
     */
    public function wantsAnotherQuestion(): bool
    {
        return $this->validated('next') === 'another';
    }

    /**
     * A new question has no stored media yet.
     */
    protected function currentQuestion(): QuizQuestion|QuestionBankItem|null
    {
        return null;
    }
}
