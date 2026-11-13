<?php

namespace Database\Factories;

use App\Enums\RewardType;
use App\Models\Reward;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Reward>
 */
class RewardFactory extends Factory
{
    /**
     * Reward names paired with the criteria for earning them.
     *
     * @var list<array{name: string, criteria: string}>
     */
    private const REWARDS = [
        ['name' => 'Veggie Champion', 'criteria' => 'Complete every mission in Veggie Valley.'],
        ['name' => 'Fruit Explorer', 'criteria' => 'Collect ten different fruits.'],
        ['name' => 'Water Warrior', 'criteria' => 'Finish the hydration challenge.'],
        ['name' => 'Breakfast Hero', 'criteria' => 'Log a healthy breakfast for a week.'],
        ['name' => 'Snack Smart', 'criteria' => 'Pass the healthy snacks quiz.'],
        ['name' => 'Rainbow Plate', 'criteria' => 'Build a plate with five colors.'],
    ];

    /**
     * Define the model's default state: an active reward with a unique name.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $reward = fake()->randomElement(self::REWARDS);

        return [
            'name' => $reward['name'].' '.fake()->unique()->numberBetween(1, 9999),
            'description' => fake()->sentence(),
            'type' => fake()->randomElement(RewardType::cases()),
            'icon' => fake()->randomElement(['award', 'trophy', 'medal', 'crown', 'star', 'sparkles', 'gift']),
            'color' => fake()->randomElement(['leaf', 'carrot', 'sun', 'berry', 'grape', 'sky', 'teal', 'lime', 'pink', 'indigo', 'slate']),
            'criteria' => $reward['criteria'],
            'points_required' => fake()->randomElement([null, 100, 250, 500, 1000]),
            'is_active' => true,
        ];
    }

    /**
     * Indicate that the reward is inactive.
     */
    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }
}
