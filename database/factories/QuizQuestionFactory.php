<?php

namespace Database\Factories;

use App\Models\Quiz;
use App\Models\QuizQuestion;
use Database\Factories\Concerns\GeneratesQuestionContent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<QuizQuestion>
 */
class QuizQuestionFactory extends Factory
{
    use GeneratesQuestionContent;

    /**
     * The last sort order handed out per quiz by this factory instance.
     *
     * Models of one create() call are all made before any is saved, so the database alone cannot number them.
     *
     * @var array<int, int>
     */
    private array $lastSortOrders = [];

    /**
     * Define the model's default state: a one-point multiple choice question placed after the quiz's other questions.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'quiz_id' => Quiz::factory(),
            'question_bank_item_id' => null,
            ...$this->multipleChoiceContent(),
            'image_path' => null,
            'video_path' => null,
            'video_url' => null,
            'points' => 1,
            'sort_order' => fn (array $attributes): int => $this->nextSortOrder((int) $attributes['quiz_id']),
        ];
    }

    /**
     * Indicate that the question is multiple choice.
     */
    public function multipleChoice(): static
    {
        return $this->state(fn (array $attributes): array => $this->multipleChoiceContent());
    }

    /**
     * Indicate that the question is true or false.
     */
    public function trueFalse(): static
    {
        return $this->state(fn (array $attributes): array => $this->trueFalseContent());
    }

    /**
     * Indicate that the question is an identification question.
     */
    public function identification(): static
    {
        return $this->state(fn (array $attributes): array => $this->identificationContent());
    }

    /**
     * Get the sort order that places a new question after the quiz's existing questions.
     */
    private function nextSortOrder(int $quizId): int
    {
        $this->lastSortOrders[$quizId] ??= (int) QuizQuestion::query()->where('quiz_id', $quizId)->max('sort_order');

        return ++$this->lastSortOrders[$quizId];
    }
}
