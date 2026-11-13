<?php

namespace Database\Factories;

use App\Models\ForumCategory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ForumCategory>
 */
class ForumCategoryFactory extends Factory
{
    /**
     * Category names paired with their description.
     *
     * @var list<array{name: string, description: string}>
     */
    private const CATEGORIES = [
        ['name' => 'Healthy Recipes', 'description' => 'Share simple, budget-friendly meals your family loves.'],
        ['name' => 'Picky Eaters', 'description' => 'Tips for getting children to try new fruits and vegetables.'],
        ['name' => 'School Lunch Ideas', 'description' => 'Baon ideas that are healthy, filling and easy to pack.'],
        ['name' => 'Learning at Home', 'description' => 'Ways to support what learners do in NutriMind at home.'],
        ['name' => 'Game Help', 'description' => 'Questions about missions, levels, rewards and offline play.'],
    ];

    /**
     * Define the model's default state: an active category with a unique slug.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $category = fake()->randomElement(self::CATEGORIES);

        return [
            'name' => $category['name'],
            'slug' => Str::slug($category['name']).'-'.fake()->unique()->numberBetween(1, 9999),
            'description' => $category['description'],
            'color' => fake()->randomElement(['leaf', 'carrot', 'sun', 'berry', 'grape', 'sky', 'teal', 'lime', 'pink', 'indigo', 'slate']),
            'icon' => fake()->randomElement(['messages-square', 'salad', 'utensils', 'house-heart', 'gamepad-2', 'megaphone']),
            'sort_order' => fake()->numberBetween(0, 10),
            'is_active' => true,
        ];
    }

    /**
     * Indicate that the category is inactive.
     */
    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }
}
