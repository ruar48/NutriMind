<?php

namespace Database\Factories;

use App\Enums\GradeLevel;
use App\Models\LearningModule;
use App\Models\Subject;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LearningModule>
 */
class LearningModuleFactory extends Factory
{
    /**
     * Module titles paired with the learning competency they develop.
     *
     * @var list<array{title: string, competency: string}>
     */
    private const MODULES = [
        ['title' => 'Go, Grow and Glow Foods', 'competency' => 'Classifies foods into Go, Grow and Glow groups.'],
        ['title' => 'The Balanced Plate', 'competency' => 'Plans a balanced meal using the Pinggang Pinoy guide.'],
        ['title' => 'Healthy Snacks', 'competency' => 'Chooses nutritious snacks over junk food.'],
        ['title' => 'Reading Food Labels', 'competency' => 'Reads the Nutrition Facts label of packaged food.'],
        ['title' => 'Food Safety at Home', 'competency' => 'Practices safe food handling and preparation.'],
        ['title' => 'Water and Hydration', 'competency' => 'Explains why the body needs enough water every day.'],
        ['title' => 'Vitamins and Minerals', 'competency' => 'Identifies food sources of important vitamins and minerals.'],
    ];

    /**
     * Game world names.
     *
     * @var list<string>
     */
    private const WORLDS = ['Veggie Valley', 'Fruit Forest', 'Grain Grove', 'Protein Peak', 'Hydration Harbor', 'Snack Shack'];

    /**
     * Define the model's default state: a published module with a DepEd-style competency code.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $module = fake()->randomElement(self::MODULES);

        return [
            'subject_id' => Subject::factory(),
            'grade_level' => fake()->randomElement(GradeLevel::cases()),
            'quarter' => fake()->numberBetween(1, 4),
            'title' => $module['title'],
            'world_name' => fake()->randomElement(self::WORLDS),
            'description' => fake()->sentence(12),
            'competency_code' => fn (array $attributes): string => $this->competencyCode($attributes['grade_level'], (int) $attributes['quarter']),
            'learning_competency' => $module['competency'],
            'sort_order' => fake()->numberBetween(0, 20),
            'is_published' => true,
        ];
    }

    /**
     * Indicate that the module is not published in the game.
     */
    public function unpublished(): static
    {
        return $this->state(['is_published' => false]);
    }

    /**
     * Build a competency code such as "H5N-IIb-7" (Health, grade, nutrition strand, quarter, week, number).
     */
    private function competencyCode(GradeLevel|int $grade, int $quarter): string
    {
        $gradeValue = $grade instanceof GradeLevel ? $grade->value : $grade;
        $quarterNumeral = ['I', 'II', 'III', 'IV'][min(max($quarter, 1), 4) - 1];

        return sprintf('H%dN-%s%s-%d', $gradeValue, $quarterNumeral, fake()->randomElement(['a', 'b', 'c', 'd']), fake()->numberBetween(1, 30));
    }
}
