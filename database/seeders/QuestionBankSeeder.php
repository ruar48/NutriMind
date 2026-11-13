<?php

namespace Database\Seeders;

use App\Models\LearningModule;
use App\Models\QuestionBankItem;
use App\Models\User;
use Database\Seeders\Support\BulkInsert;
use Database\Seeders\Support\Catalog\ContentCatalog;
use Database\Seeders\Support\Catalog\PeopleCatalog;
use Database\Seeders\Support\Catalog\QuestionCatalog;
use Database\Seeders\Support\DemoClock;
use Database\Seeders\Support\DemoRandom;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Seeds the question bank: about eighty reviewed-by-the-teacher nutrition questions, many shared within a subject.
 *
 * The demo teacher wrote her Grade 5 Health items last school year (they appear in last year's quiz); the items of
 * the module still in preparation are new; every other item was written during the first quarter. How often each
 * item was used is filled in by the quiz seeder.
 */
class QuestionBankSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $clock = DemoClock::start();
        $random = DemoRandom::stream('question-bank');
        $modules = LearningModule::query()->get(['id', 'subject_id', 'grade_level', 'competency_code', 'is_published'])->keyBy('competency_code');
        $teacherIds = User::query()->toBase()->pluck('id', 'email');
        $rows = [];

        foreach (QuestionCatalog::all() as $question) {
            if (! $question['bank']) {
                continue;
            }

            $module = $modules[$question['module']];
            $createdAt = $this->createdAt($random, $question, $module->is_published);
            $reviewedAt = $question['reviewed'] ? min($createdAt + $random->int(1, 6) * DemoClock::DAY, $clock->now - DemoClock::HOUR) : null;
            $content = QuestionCatalog::content($question);

            $rows[] = [
                'teacher_id' => $teacherIds[PeopleCatalog::teacherEmail($question['owner'])],
                'subject_id' => $module->subject_id,
                'learning_module_id' => $module->id,
                'grade_level' => $module->grade_level->value,
                'topic' => Str::ucfirst(ContentCatalog::moduleByCode($question['module'])['topic']),
                'competency_code' => $question['module'],
                'type' => $content['type'],
                'prompt' => $content['prompt'],
                'choices' => $content['choices'] === null ? null : json_encode($content['choices']),
                'correct_answer' => $content['correct_answer'],
                'explanation' => $content['explanation'],
                'image_path' => null,
                'video_path' => null,
                'video_url' => null,
                'difficulty' => $question['difficulty'],
                'is_shared' => $question['shared'],
                'reviewed_at' => $reviewedAt === null ? null : DemoClock::format($reviewedAt),
                'times_used' => 0,
                'created_at' => DemoClock::format($createdAt),
                'updated_at' => DemoClock::format($reviewedAt ?? $createdAt),
            ];
        }

        BulkInsert::models(QuestionBankItem::class, $rows);
    }

    /**
     * Get when a bank item was written.
     *
     * @param  array{owner: string, module: string}  $question
     */
    private function createdAt(DemoRandom $random, array $question, bool $modulePublished): int
    {
        [$from, $days] = match (true) {
            ! $modulePublished => ['2026-10-19', 22],
            $question['owner'] === 'santos' && str_starts_with($question['module'], 'H5N') => ['2026-02-16', 14],
            default => ['2026-06-22', 67],
        };

        return DemoClock::workTime($random, DemoClock::at($from) + $random->int(0, $days) * DemoClock::DAY);
    }
}
