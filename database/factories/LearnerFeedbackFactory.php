<?php

namespace Database\Factories;

use App\Enums\FeedbackType;
use App\Models\LearnerFeedback;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LearnerFeedback>
 */
class LearnerFeedbackFactory extends Factory
{
    /**
     * A sample message for each feedback type.
     *
     * @var array<string, list<string>>
     */
    private const MESSAGES = [
        'praise' => [
            'Great job on your food groups quiz! You clearly know your Go, Grow and Glow foods.',
            'Excellent work finishing every mission in Veggie Valley this week.',
        ],
        'encouragement' => [
            'You are improving every week. Keep practicing the mini-games and you will level up soon!',
            'Nice effort on the snacks quiz. One more try and you will pass it.',
        ],
        'guidance' => [
            'Review the lesson on reading food labels before you try the quiz again.',
            'Take your time on identification questions and check your spelling.',
        ],
        'concern' => [
            'Your scores dropped this week. Let us talk about what made the last quiz difficult.',
            'You have not played NutriMind for a while. Is everything okay?',
        ],
    ];

    /**
     * Define the model's default state: unread feedback that parents and guardians may read.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'teacher_id' => User::factory()->teacher(),
            'student_id' => User::factory()->student(),
            'classroom_id' => null,
            'quiz_attempt_id' => null,
            'type' => fake()->randomElement(FeedbackType::cases()),
            'message' => fn (array $attributes): string => fake()->randomElement(
                self::MESSAGES[$attributes['type'] instanceof FeedbackType ? $attributes['type']->value : $attributes['type']],
            ),
            'is_visible_to_guardians' => true,
            'read_at' => null,
        ];
    }

    /**
     * Indicate that the feedback is only for the teacher's records.
     */
    public function hiddenFromGuardians(): static
    {
        return $this->state(['is_visible_to_guardians' => false]);
    }

    /**
     * Indicate that a parent or guardian already read the feedback.
     */
    public function read(): static
    {
        return $this->state(fn (array $attributes): array => [
            'read_at' => now(),
        ]);
    }
}
