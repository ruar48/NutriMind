<?php

namespace Database\Factories;

use App\Enums\ForumContentStatus;
use App\Models\ForumCategory;
use App\Models\ForumThread;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ForumThread>
 */
class ForumThreadFactory extends Factory
{
    /**
     * Thread titles.
     *
     * @var list<string>
     */
    private const TITLES = [
        'How do you get your child to eat vegetables?',
        'Quick and healthy breakfast ideas',
        'My child keeps asking for soft drinks',
        'Favorite malunggay recipes?',
        'How does offline mode work in the game?',
        'Budget-friendly baon for the whole week',
    ];

    /**
     * Define the model's default state: a published thread started by a parent.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'forum_category_id' => ForumCategory::factory(),
            'author_id' => User::factory()->parent(),
            'title' => fake()->randomElement(self::TITLES),
            'body' => fake()->paragraphs(2, true),
            'status' => ForumContentStatus::Published,
            'is_pinned' => false,
            'is_locked' => false,
            'last_activity_at' => now(),
        ];
    }

    /**
     * Indicate that a moderator pinned the thread.
     */
    public function pinned(): static
    {
        return $this->state(['is_pinned' => true]);
    }

    /**
     * Indicate that a moderator locked the thread against new replies.
     */
    public function locked(): static
    {
        return $this->state(['is_locked' => true]);
    }

    /**
     * Indicate that a moderator hid the thread.
     */
    public function hidden(): static
    {
        return $this->state(['status' => ForumContentStatus::Hidden]);
    }

    /**
     * Indicate that the thread awaits a moderator's approval.
     */
    public function pending(): static
    {
        return $this->state(['status' => ForumContentStatus::Pending]);
    }
}
