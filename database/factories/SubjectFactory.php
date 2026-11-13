<?php

namespace Database\Factories;

use App\Models\Subject;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Subject>
 */
class SubjectFactory extends Factory
{
    /**
     * Subject names the generated subjects are based on.
     *
     * @var list<string>
     */
    private const NAMES = ['Health', 'Science', 'Home Economics', 'Physical Education', 'Nutrition', 'Values Education'];

    /**
     * Define the model's default state: an active subject with a unique name and code.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->randomElement(self::NAMES);
        $number = fake()->unique()->numberBetween(100, 999);

        return [
            'name' => "{$name} {$number}",
            'code' => Str::upper(Str::substr(Str::remove(' ', $name), 0, 4))."-{$number}",
            'description' => fake()->sentence(),
            'color' => fake()->randomElement(['leaf', 'carrot', 'sun', 'berry', 'grape', 'sky', 'teal', 'lime', 'pink', 'indigo', 'slate']),
            'icon' => fake()->randomElement(['book-open', 'apple', 'carrot', 'salad', 'heart-pulse', 'leaf', 'brain', 'utensils']),
            'is_active' => true,
        ];
    }

    /**
     * Indicate that the subject is inactive.
     */
    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }
}
