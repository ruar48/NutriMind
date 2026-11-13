<?php

namespace Database\Factories;

use App\Enums\ProgressStatus;
use App\Models\ActivityProgress;
use App\Models\LearningActivity;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<ActivityProgress>
 */
class ActivityProgressFactory extends Factory
{
    /**
     * Define the model's default state: an activity in progress without a score yet.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'learning_activity_id' => LearningActivity::factory(),
            'student_id' => User::factory()->student(),
            'status' => ProgressStatus::InProgress,
            'score' => null,
            'points_earned' => 0,
            'time_spent_seconds' => fake()->numberBetween(60, 900),
            'completed_at' => null,
            'synced_at' => now()->subMinutes(fake()->numberBetween(5, 4320)),
        ];
    }

    /**
     * Indicate that the learner completed the activity, scored on it and earned its points.
     */
    public function completed(): static
    {
        return $this->state([
            'status' => ProgressStatus::Completed,
            'score' => fn (): int => fake()->numberBetween(60, 100),
            'points_earned' => fn (array $attributes): int => (int) LearningActivity::query()
                ->whereKey($attributes['learning_activity_id'])
                ->value('points'),
            'completed_at' => fn (): Carbon => now()->subMinutes(fake()->numberBetween(30, 4320)),
            'synced_at' => fn (array $attributes): ?Carbon => $attributes['completed_at'] === null
                ? null
                : Carbon::parse($attributes['completed_at'])->addMinutes(fake()->numberBetween(0, 60))->min(now()),
        ]);
    }
}
