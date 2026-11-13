<?php

namespace Database\Factories;

use App\Enums\Difficulty;
use App\Enums\GradeLevel;
use App\Enums\MissionType;
use App\Models\LearningModule;
use App\Models\Mission;
use App\Models\Subject;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Mission>
 */
class MissionFactory extends Factory
{
    /**
     * Mission titles paired with their objective.
     *
     * @var list<array{title: string, objective: string}>
     */
    private const MISSIONS = [
        ['title' => 'Breakfast Champion', 'objective' => 'Eat a healthy breakfast five days in a row.'],
        ['title' => 'Rainbow Plate', 'objective' => 'Collect fruits and vegetables of every color.'],
        ['title' => 'Water Warrior', 'objective' => 'Log eight glasses of water today.'],
        ['title' => 'Snack Swap Hero', 'objective' => 'Swap three junk-food snacks for healthy ones.'],
        ['title' => 'Market Explorer', 'objective' => 'Find five Grow foods at the virtual market.'],
        ['title' => 'Label Reader', 'objective' => 'Read the Nutrition Facts label of three products.'],
    ];

    /**
     * Define the model's default state: an active mission without a module.
     *
     * When a module is given, the mission takes the module's subject and grade level.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $mission = fake()->randomElement(self::MISSIONS);

        return [
            'learning_module_id' => null,
            'subject_id' => fn (array $attributes): mixed => $attributes['learning_module_id'] === null
                ? Subject::factory()
                : LearningModule::query()->whereKey($attributes['learning_module_id'])->value('subject_id'),
            'grade_level' => fn (array $attributes): mixed => $attributes['learning_module_id'] === null
                ? fake()->randomElement(GradeLevel::cases())
                : LearningModule::query()->whereKey($attributes['learning_module_id'])->value('grade_level'),
            'title' => $mission['title'],
            'description' => fake()->sentence(14),
            'objective' => $mission['objective'],
            'type' => fake()->randomElement(MissionType::cases()),
            'difficulty' => fake()->randomElement(Difficulty::cases()),
            'points_reward' => fake()->randomElement([50, 75, 100, 150, 200]),
            'starts_on' => null,
            'ends_on' => null,
            'sort_order' => fake()->numberBetween(0, 20),
            'is_active' => true,
        ];
    }

    /**
     * Indicate that the mission is inactive.
     */
    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }
}
