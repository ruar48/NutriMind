<?php

namespace Database\Factories;

use App\Enums\ProgressStatus;
use App\Models\Mission;
use App\Models\MissionProgress;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<MissionProgress>
 */
class MissionProgressFactory extends Factory
{
    /**
     * Define the model's default state: a mission in progress, synced after it started.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'mission_id' => Mission::factory(),
            'student_id' => User::factory()->student(),
            'status' => ProgressStatus::InProgress,
            'progress_percent' => fake()->numberBetween(10, 90),
            'points_earned' => 0,
            'started_at' => now()->subDays(fake()->numberBetween(1, 14)),
            'completed_at' => null,
            'synced_at' => fn (array $attributes): ?Carbon => $this->after($attributes['started_at']),
        ];
    }

    /**
     * Indicate that the learner completed the mission and earned its full reward.
     */
    public function completed(): static
    {
        return $this->state([
            'status' => ProgressStatus::Completed,
            'progress_percent' => 100,
            'points_earned' => fn (array $attributes): int => (int) Mission::query()
                ->whereKey($attributes['mission_id'])
                ->value('points_reward'),
            'completed_at' => fn (array $attributes): ?Carbon => $this->after($attributes['started_at']),
            'synced_at' => fn (array $attributes): ?Carbon => $this->after($attributes['completed_at']),
        ]);
    }

    /**
     * Indicate that the learner is still working on the mission.
     */
    public function inProgress(): static
    {
        return $this->state([
            'status' => ProgressStatus::InProgress,
            'progress_percent' => fn (): int => fake()->numberBetween(10, 90),
            'points_earned' => 0,
            'completed_at' => null,
        ]);
    }

    /**
     * Pick a moment after the given one that is not in the future.
     */
    private function after(mixed $moment): ?Carbon
    {
        return $moment === null
            ? null
            : Carbon::parse($moment)->addMinutes(fake()->numberBetween(5, 600))->min(now());
    }
}
