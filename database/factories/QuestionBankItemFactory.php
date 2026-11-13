<?php

namespace Database\Factories;

use App\Enums\Difficulty;
use App\Enums\GradeLevel;
use App\Models\LearningModule;
use App\Models\QuestionBankItem;
use App\Models\Subject;
use App\Models\User;
use Database\Factories\Concerns\AssignsSubjectToTeacher;
use Database\Factories\Concerns\GeneratesQuestionContent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<QuestionBankItem>
 */
class QuestionBankItemFactory extends Factory
{
    use AssignsSubjectToTeacher, GeneratesQuestionContent;

    /**
     * Question topics.
     *
     * @var list<string>
     */
    private const TOPICS = ['Food groups', 'Balanced meals', 'Healthy snacks', 'Food labels', 'Hydration', 'Vitamins and minerals', 'Food safety'];

    /**
     * Define the model's default state: a private, unreviewed multiple choice item whose teacher teaches its subject.
     *
     * When a module is given, the item takes the module's subject and grade level.
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
            'grade_level' => fn (array $attributes): mixed => $attributes['learning_module_id'] === null
                ? fake()->randomElement(GradeLevel::cases())
                : LearningModule::query()->whereKey($attributes['learning_module_id'])->value('grade_level'),
            'topic' => fake()->randomElement(self::TOPICS),
            'competency_code' => null,
            ...$this->multipleChoiceContent(),
            'image_path' => null,
            'video_path' => null,
            'video_url' => null,
            'difficulty' => fake()->randomElement(Difficulty::cases()),
            'is_shared' => false,
            'reviewed_at' => null,
            'times_used' => 0,
        ];
    }

    /**
     * Configure the model factory.
     */
    public function configure(): static
    {
        return $this->afterCreating(function (QuestionBankItem $item): void {
            $this->assignSubjectToTeacher($item);
        });
    }

    /**
     * Indicate that the item is shared with the other teachers of its subject.
     */
    public function shared(): static
    {
        return $this->state(['is_shared' => true]);
    }

    /**
     * Indicate that a teacher reviewed the item.
     */
    public function reviewed(): static
    {
        return $this->state(fn (array $attributes): array => [
            'reviewed_at' => now(),
        ]);
    }

    /**
     * Indicate that the item is multiple choice.
     */
    public function multipleChoice(): static
    {
        return $this->state(fn (array $attributes): array => $this->multipleChoiceContent());
    }

    /**
     * Indicate that the item is true or false.
     */
    public function trueFalse(): static
    {
        return $this->state(fn (array $attributes): array => $this->trueFalseContent());
    }

    /**
     * Indicate that the item is an identification question.
     */
    public function identification(): static
    {
        return $this->state(fn (array $attributes): array => $this->identificationContent());
    }
}
