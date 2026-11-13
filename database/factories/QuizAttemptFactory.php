<?php

namespace Database\Factories;

use App\Models\Classroom;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * @extends Factory<QuizAttempt>
 */
class QuizAttemptFactory extends Factory
{
    /**
     * The quizzes this factory instance already looked up, keyed by ID.
     *
     * @var array<int, Quiz>
     */
    private array $quizzes = [];

    /**
     * Define the model's default state: a submitted online attempt at a published quiz.
     *
     * The maximum score is the quiz's total question points (5–20 when it has no questions yet),
     * the percentage is score / max_score * 100 rounded to 2 decimals, and "passed" compares the
     * percentage with the quiz's passing score. The duration respects the quiz's time limit.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'quiz_id' => Quiz::factory()->published(),
            'student_id' => User::factory()->student(),
            'classroom_id' => null,
            'attempt_number' => 1,
            'max_score' => fn (array $attributes): int => $this->maxScoreFor((int) $attributes['quiz_id']),
            'score' => fn (array $attributes): int => fake()->numberBetween(0, (int) $attributes['max_score']),
            'percentage' => fn (array $attributes): float => $this->percentageFor((int) $attributes['score'], (int) $attributes['max_score']),
            'passed' => fn (array $attributes): bool => (float) $attributes['percentage'] >= $this->quiz((int) $attributes['quiz_id'])->passing_score,
            'duration_seconds' => fn (array $attributes): int => $this->durationFor((int) $attributes['quiz_id']),
            'submitted_at' => fn (): Carbon => now(),
            'started_at' => fn (array $attributes): Carbon => Carbon::parse($attributes['submitted_at'] ?? now())
                ->subSeconds((int) ($attributes['duration_seconds'] ?? 0)),
            'is_offline' => false,
            'synced_at' => fn (array $attributes): mixed => $attributes['submitted_at'],
        ];
    }

    /**
     * Configure the model factory.
     *
     * An attempt made in a classroom implies the learner is enrolled there and the quiz is assigned
     * to it; missing links are added and existing ones are left untouched.
     */
    public function configure(): static
    {
        return $this->afterCreating(function (QuizAttempt $attempt): void {
            if ($attempt->classroom_id === null) {
                return;
            }

            $classroom = Classroom::query()->findOrFail($attempt->classroom_id);
            $linkedAt = $attempt->started_at ?? now();

            if (! $classroom->students()->whereKey($attempt->student_id)->exists()) {
                $classroom->students()->attach($attempt->student_id, ['enrolled_at' => $linkedAt]);
            }

            if (! $classroom->quizzes()->whereKey($attempt->quiz_id)->exists()) {
                $classroom->quizzes()->attach($attempt->quiz_id, ['assigned_at' => $linkedAt]);
            }
        });
    }

    /**
     * Indicate that the learner reached the quiz's passing score.
     */
    public function passed(): static
    {
        return $this->state([
            'score' => fn (array $attributes): int => fake()->randomElement($this->scoresWithOutcome($attributes, true)),
        ]);
    }

    /**
     * Indicate that the learner scored below the quiz's passing score.
     */
    public function failed(): static
    {
        return $this->state([
            'score' => fn (array $attributes): int => fake()->randomElement($this->scoresWithOutcome($attributes, false)),
        ]);
    }

    /**
     * Indicate that the attempt was played offline and synced to the server later.
     */
    public function offline(): static
    {
        return $this->state([
            'is_offline' => true,
            'submitted_at' => fn (): Carbon => now()->subHours(fake()->numberBetween(2, 48)),
            'synced_at' => fn (): Carbon => now(),
        ]);
    }

    /**
     * Indicate that the learner started the attempt but has not submitted it.
     */
    public function inProgress(): static
    {
        return $this->state([
            'score' => 0,
            'percentage' => 0,
            'passed' => false,
            'duration_seconds' => null,
            'submitted_at' => null,
            'started_at' => fn (): Carbon => now()->subMinutes(fake()->numberBetween(1, 10)),
            'synced_at' => null,
        ]);
    }

    /**
     * Get the scores out of the attempt's maximum that pass (or fail) the quiz.
     *
     * @param  array<string, mixed>  $attributes
     * @return non-empty-list<int>
     */
    private function scoresWithOutcome(array $attributes, bool $passes): array
    {
        $maxScore = (int) $attributes['max_score'];
        $passingScore = $this->quiz((int) $attributes['quiz_id'])->passing_score;

        $scores = array_values(array_filter(
            range(0, $maxScore),
            fn (int $score): bool => ($this->percentageFor($score, $maxScore) >= $passingScore) === $passes,
        ));

        if ($scores === []) {
            throw new LogicException(sprintf(
                'No score out of %d %s a quiz with a passing score of %d.',
                $maxScore,
                $passes ? 'passes' : 'fails',
                $passingScore,
            ));
        }

        return $scores;
    }

    /**
     * Get the attempt's maximum score: the quiz's total question points.
     */
    private function maxScoreFor(int $quizId): int
    {
        $totalPoints = (int) $this->quiz($quizId)->questions()->sum('points');

        return $totalPoints > 0 ? $totalPoints : fake()->numberBetween(5, 20);
    }

    /**
     * Get the percentage score rounded to 2 decimals.
     */
    private function percentageFor(int $score, int $maxScore): float
    {
        return $maxScore > 0 ? round($score / $maxScore * 100, 2) : 0.0;
    }

    /**
     * Get a duration in seconds that fits within the quiz's time limit.
     */
    private function durationFor(int $quizId): int
    {
        $limitMinutes = $this->quiz($quizId)->time_limit_minutes;
        $longest = $limitMinutes === null ? 900 : $limitMinutes * 60;

        return fake()->numberBetween(min(60, $longest), $longest);
    }

    /**
     * Look up a quiz once per factory instance.
     */
    private function quiz(int $quizId): Quiz
    {
        return $this->quizzes[$quizId] ??= Quiz::query()->findOrFail($quizId);
    }
}
