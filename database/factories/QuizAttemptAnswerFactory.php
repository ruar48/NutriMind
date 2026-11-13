<?php

namespace Database\Factories;

use App\Enums\QuestionType;
use App\Models\QuizAttempt;
use App\Models\QuizAttemptAnswer;
use App\Models\QuizQuestion;
use Illuminate\Database\Eloquent\Factories\Factory;
use LogicException;

/**
 * @extends Factory<QuizAttemptAnswer>
 */
class QuizAttemptAnswerFactory extends Factory
{
    /**
     * Typed answers that no question in the factories accepts.
     *
     * @var list<string>
     */
    private const WRONG_TYPED_ANSWERS = ['Candy', 'Soft drinks', 'Potato chips', 'I do not know'];

    /**
     * The questions this factory instance already looked up, keyed by ID.
     *
     * @var array<int, QuizQuestion>
     */
    private array $questions = [];

    /**
     * Define the model's default state: a right or wrong answer to a question of the attempt's quiz,
     * with is_correct and points_awarded derived from the answer.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'quiz_attempt_id' => QuizAttempt::factory(),
            'quiz_question_id' => fn (array $attributes): QuizQuestionFactory => QuizQuestion::factory()->state([
                'quiz_id' => QuizAttempt::query()->whereKey($attributes['quiz_attempt_id'])->value('quiz_id'),
            ]),
            'answer' => fn (array $attributes): string => fake()->boolean()
                ? $this->correctAnswerFor($this->question((int) $attributes['quiz_question_id']))
                : $this->wrongAnswerFor($this->question((int) $attributes['quiz_question_id'])),
            'is_correct' => fn (array $attributes): bool => $this->question((int) $attributes['quiz_question_id'])
                ->isCorrectAnswer($attributes['answer']),
            'points_awarded' => fn (array $attributes): int => $attributes['is_correct']
                ? $this->question((int) $attributes['quiz_question_id'])->points
                : 0,
        ];
    }

    /**
     * Indicate that the learner answered the question correctly.
     */
    public function correct(): static
    {
        return $this->state([
            'answer' => fn (array $attributes): string => $this->correctAnswerFor($this->question((int) $attributes['quiz_question_id'])),
        ]);
    }

    /**
     * Indicate that the learner answered the question incorrectly.
     */
    public function incorrect(): static
    {
        return $this->state([
            'answer' => fn (array $attributes): string => $this->wrongAnswerFor($this->question((int) $attributes['quiz_question_id'])),
        ]);
    }

    /**
     * Get an answer the question accepts, in the stored learner-answer format.
     */
    private function correctAnswerFor(QuizQuestion $question): string
    {
        return $question->type === QuestionType::Identification
            ? $question->acceptedAnswers()[0] ?? throw new LogicException("Question [{$question->getKey()}] accepts no answer.")
            : (string) $question->correct_answer;
    }

    /**
     * Get an answer the question rejects, in the stored learner-answer format.
     */
    private function wrongAnswerFor(QuizQuestion $question): string
    {
        $candidates = $question->type === QuestionType::Identification
            ? self::WRONG_TYPED_ANSWERS
            : array_map(fn (int $index): string => (string) $index, array_keys($question->choices ?? []));

        $wrongAnswers = array_values(array_filter(
            $candidates,
            fn (string $candidate): bool => ! $question->isCorrectAnswer($candidate),
        ));

        if ($wrongAnswers === []) {
            throw new LogicException("Question [{$question->getKey()}] has no wrong answer to choose.");
        }

        return fake()->randomElement($wrongAnswers);
    }

    /**
     * Look up a question once per factory instance.
     */
    private function question(int $questionId): QuizQuestion
    {
        return $this->questions[$questionId] ??= QuizQuestion::query()->findOrFail($questionId);
    }
}
