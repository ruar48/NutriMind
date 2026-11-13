<?php

namespace Database\Factories;

use App\Enums\GradeLevel;
use App\Enums\QuizStatus;
use App\Models\LearningModule;
use App\Models\Quiz;
use App\Models\Subject;
use App\Models\User;
use Database\Factories\Concerns\AssignsSubjectToTeacher;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Quiz>
 */
class QuizFactory extends Factory
{
    use AssignsSubjectToTeacher;

    /**
     * Quiz titles.
     *
     * @var list<string>
     */
    private const TITLES = [
        'Go, Grow and Glow Foods Quiz',
        'Balanced Plate Check',
        'Healthy Snacks Quiz',
        'Food Label Challenge',
        'Hydration Quick Quiz',
        'Vitamins and Minerals Review',
    ];

    /**
     * Define the model's default state: a draft quiz whose teacher teaches its subject.
     *
     * When a module is given, the quiz takes the module's subject and grade level.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'teacher_id' => User::factory()->teacher(),
            'learning_module_id' => null,
            'subject_id' => fn (array $attributes): mixed => $attributes['learning_module_id'] === null
                ? Subject::factory()
                : LearningModule::query()->whereKey($attributes['learning_module_id'])->value('subject_id'),
            'academic_term_id' => null,
            'grade_level' => fn (array $attributes): mixed => $attributes['learning_module_id'] === null
                ? fake()->randomElement(GradeLevel::cases())
                : LearningModule::query()->whereKey($attributes['learning_module_id'])->value('grade_level'),
            'title' => fake()->randomElement(self::TITLES),
            'instructions' => fake()->sentence(),
            'time_limit_minutes' => fake()->randomElement([null, 10, 15, 20, 30]),
            'passing_score' => 75,
            'max_attempts' => 2,
            'shuffle_questions' => false,
            'show_correct_answers' => true,
            'status' => QuizStatus::Draft,
            'reviewed_at' => null,
            'published_at' => null,
        ];
    }

    /**
     * Configure the model factory.
     */
    public function configure(): static
    {
        return $this->afterCreating(function (Quiz $quiz): void {
            $this->assignSubjectToTeacher($quiz);
        });
    }

    /**
     * Indicate that the quiz was reviewed and published to learners.
     */
    public function published(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => QuizStatus::Published,
            'reviewed_at' => now(),
            'published_at' => now(),
        ]);
    }

    /**
     * Indicate that the quiz was published and is now closed to new attempts.
     */
    public function closed(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => QuizStatus::Closed,
            'reviewed_at' => now(),
            'published_at' => now(),
        ]);
    }

    /**
     * Indicate that the teacher reviewed the quiz (its status is unchanged).
     */
    public function reviewed(): static
    {
        return $this->state(fn (array $attributes): array => [
            'reviewed_at' => now(),
        ]);
    }
}
