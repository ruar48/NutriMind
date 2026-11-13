<?php

namespace Database\Factories;

use App\Enums\ForumReportReason;
use App\Models\ForumReply;
use App\Models\ForumReport;
use App\Models\ForumThread;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ForumReport>
 */
class ForumReportFactory extends Factory
{
    /**
     * Define the model's default state: an unresolved report about a thread, filed by a parent.
     *
     * A report about a reply points at the reply's thread.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'forum_reply_id' => null,
            'forum_thread_id' => fn (array $attributes): mixed => $attributes['forum_reply_id'] === null
                ? ForumThread::factory()
                : ForumReply::query()->whereKey($attributes['forum_reply_id'])->value('forum_thread_id'),
            'reporter_id' => User::factory()->parent(),
            'reason' => fake()->randomElement(ForumReportReason::cases()),
            'details' => fake()->sentence(),
            'resolved_at' => null,
            'resolved_by' => null,
            'resolution' => null,
        ];
    }

    /**
     * Indicate that an administrator dismissed the report.
     */
    public function resolved(): static
    {
        return $this->state(fn (array $attributes): array => [
            'resolved_at' => now(),
            'resolved_by' => User::factory()->admin(),
            'resolution' => ForumReport::RESOLUTION_DISMISSED,
        ]);
    }
}
