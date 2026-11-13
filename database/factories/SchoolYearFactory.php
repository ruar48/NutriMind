<?php

namespace Database\Factories;

use App\Models\SchoolYear;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<SchoolYear>
 */
class SchoolYearFactory extends Factory
{
    /**
     * Define the model's default state: an inactive school year running from June to March.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startYear = fake()->unique()->numberBetween(2000, 2199);

        return [
            'name' => $startYear.'–'.($startYear + 1),
            'starts_on' => Carbon::create($startYear, 6, 16)->toDateString(),
            'ends_on' => Carbon::create($startYear + 1, 3, 31)->toDateString(),
            'is_active' => false,
        ];
    }

    /**
     * Indicate that the school year is the active one.
     */
    public function active(): static
    {
        return $this->state(['is_active' => true]);
    }
}
