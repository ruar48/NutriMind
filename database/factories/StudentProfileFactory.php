<?php

namespace Database\Factories;

use App\Enums\GradeLevel;
use App\Enums\Sex;
use App\Models\StudentProfile;
use App\Models\User;
use App\Services\Settings;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<StudentProfile>
 */
class StudentProfileFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * The birthdate matches the grade (Grade 5 learners are 10, Grade 6 learners are 11) and the
     * level follows the configured level rule for the total points.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory()->studentWithoutProfile(),
            'lrn' => fake()->unique()->numerify('1###########'),
            'grade_level' => fake()->randomElement(GradeLevel::cases()),
            'sex' => fake()->randomElement(Sex::cases()),
            'birthdate' => fn (array $attributes): string => $this->birthdateFor($attributes['grade_level']),
            'total_points' => fake()->numberBetween(0, 4000),
            'level' => fn (array $attributes): int => $this->levelFor((int) $attributes['total_points']),
            'last_active_at' => now()->subMinutes(fake()->numberBetween(30, 20160)),
            'last_synced_at' => fn (array $attributes): ?Carbon => $attributes['last_active_at'] === null
                ? null
                : Carbon::parse($attributes['last_active_at'])->addMinutes(fake()->numberBetween(0, 120))->min(now()),
        ];
    }

    /**
     * Pick a birthdate that matches the grade level.
     */
    private function birthdateFor(GradeLevel|int $grade): string
    {
        $age = (($grade instanceof GradeLevel ? $grade : GradeLevel::from($grade)) === GradeLevel::Five) ? 10 : 11;

        return fake()->dateTimeBetween('-'.($age + 1).' years +1 day', "-{$age} years")->format('Y-m-d');
    }

    /**
     * Calculate the level for the points with the configured level rule.
     */
    private function levelFor(int $points): int
    {
        $settings = app(Settings::class);

        $pointsPerLevel = max(1, (int) $settings->get('points_per_level'));

        return min((int) $settings->get('max_level'), intdiv($points, $pointsPerLevel) + 1);
    }
}
