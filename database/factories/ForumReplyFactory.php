<?php

namespace Database\Factories;

use App\Enums\ForumContentStatus;
use App\Models\ForumReply;
use App\Models\ForumThread;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ForumReply>
 */
class ForumReplyFactory extends Factory
{
    /**
     * Define the model's default state: a published reply by a parent.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'forum_thread_id' => ForumThread::factory(),
            'author_id' => User::factory()->parent(),
            'body' => fake()->paragraph(),
            'status' => ForumContentStatus::Published,
        ];
    }

    /**
     * Indicate that a moderator hid the reply.
     */
    public function hidden(): static
    {
        return $this->state(['status' => ForumContentStatus::Hidden]);
    }
}
