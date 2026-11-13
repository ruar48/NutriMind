<?php

namespace Database\Factories;

use App\Enums\ActivityType;
use App\Models\LearningActivity;
use App\Models\LearningModule;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LearningActivity>
 */
class LearningActivityFactory extends Factory
{
    /**
     * Activity titles.
     *
     * @var list<string>
     */
    private const TITLES = [
        'Sort the Food Groups',
        'Build a Healthy Plate',
        'Market Day Challenge',
        'Label Detective',
        'Hydration Station',
        'Snack Swap',
        'Rainbow Fruit Hunt',
        'Kitchen Safety Check',
    ];

    /**
     * Define the model's default state: a published activity of a random type.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'learning_module_id' => LearningModule::factory(),
            'title' => fake()->randomElement(self::TITLES),
            'type' => fake()->randomElement(ActivityType::cases()),
            'description' => fake()->sentence(),
            'content_url' => null,
            'estimated_minutes' => fake()->numberBetween(5, 20),
            'points' => fake()->randomElement([10, 15, 20, 25]),
            'sort_order' => fake()->numberBetween(0, 20),
            'is_published' => true,
        ];
    }

    /**
     * Indicate that the activity is not published in the game.
     */
    public function unpublished(): static
    {
        return $this->state(['is_published' => false]);
    }
}
