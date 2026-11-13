<?php

namespace Database\Seeders;

use Database\Seeders\Support\DemoRandom;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seeds the NutriMind demo school: a Philippine public elementary school in the middle of its 2nd Quarter.
 *
 * Every run produces the same school. The demo data draws from seeded streams, and PHP's global generator and
 * Faker are seeded as well so any code that relies on them is deterministic too; the global generator is
 * reseeded randomly afterwards. Dates are relative to the moment of seeding, everything is written in one
 * transaction, and the seeders run in dependency order.
 */
class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        fake()->seed(DemoRandom::SEED);
        mt_srand(DemoRandom::SEED);

        try {
            DB::transaction(fn () => $this->call([
                SchoolSetupSeeder::class,
                StaffSeeder::class,
                ClassroomSeeder::class,
                LearnerSeeder::class,
                ContentSeeder::class,
                QuestionBankSeeder::class,
                QuizSeeder::class,
                QuizAttemptSeeder::class,
                ProgressSeeder::class,
                LessonPlanSeeder::class,
                FeedbackSeeder::class,
                ForumSeeder::class,
            ]));
        } finally {
            mt_srand();
        }
    }
}
