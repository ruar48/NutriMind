<?php

namespace Database\Factories;

use App\Enums\GradeLevel;
use App\Enums\LessonPlanStatus;
use App\Models\LearningModule;
use App\Models\LessonPlan;
use App\Models\Subject;
use App\Models\User;
use Database\Factories\Concerns\AssignsSubjectToTeacher;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<LessonPlan>
 */
class LessonPlanFactory extends Factory
{
    use AssignsSubjectToTeacher;

    /**
     * Lesson titles paired with the competency they cover.
     *
     * @var list<array{title: string, competency: string, objectives: string}>
     */
    private const LESSONS = [
        [
            'title' => 'Sorting Go, Grow and Glow Foods',
            'competency' => 'Classifies foods into Go, Grow and Glow groups.',
            'objectives' => 'Learners name two foods from each group and explain what each group does for the body.',
        ],
        [
            'title' => 'Planning a Balanced Plate',
            'competency' => 'Plans a balanced meal using the Pinggang Pinoy guide.',
            'objectives' => 'Learners draw a balanced plate and justify the portion of each food group.',
        ],
        [
            'title' => 'Smart Snacking',
            'competency' => 'Chooses nutritious snacks over junk food.',
            'objectives' => 'Learners compare two snacks and choose the healthier one using the Nutrition Facts label.',
        ],
        [
            'title' => 'Keeping Food Safe',
            'competency' => 'Practices safe food handling and preparation.',
            'objectives' => 'Learners demonstrate proper hand washing and safe food storage.',
        ],
    ];

    /**
     * Define the model's default state: a planned lesson whose teacher teaches its subject.
     *
     * When a module is given, the lesson plan takes the module's subject and grade level.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $lesson = fake()->randomElement(self::LESSONS);
        $startsOn = Carbon::parse(fake()->dateTimeBetween('-4 weeks', '+4 weeks'))->startOfWeek();

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
            'title' => $lesson['title'],
            'competency_code' => fn (array $attributes): string => sprintf(
                'H%dN-%s%s-%d',
                $attributes['grade_level'] instanceof GradeLevel ? $attributes['grade_level']->value : $attributes['grade_level'],
                fake()->randomElement(['I', 'II', 'III', 'IV']),
                fake()->randomElement(['a', 'b', 'c', 'd']),
                fake()->numberBetween(1, 30),
            ),
            'learning_competency' => $lesson['competency'],
            'objectives' => $lesson['objectives'],
            'week_number' => fake()->numberBetween(1, 10),
            'starts_on' => $startsOn->toDateString(),
            'ends_on' => $startsOn->copy()->addDays(4)->toDateString(),
            'status' => LessonPlanStatus::Planned,
            'notes' => fake()->sentence(),
        ];
    }

    /**
     * Configure the model factory.
     */
    public function configure(): static
    {
        return $this->afterCreating(function (LessonPlan $lessonPlan): void {
            $this->assignSubjectToTeacher($lessonPlan);
        });
    }
}
