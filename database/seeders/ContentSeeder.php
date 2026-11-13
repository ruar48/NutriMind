<?php

namespace Database\Seeders;

use App\Models\LearningActivity;
use App\Models\LearningModule;
use App\Models\Mission;
use App\Models\Reward;
use App\Models\Subject;
use Database\Seeders\Support\BulkInsert;
use Database\Seeders\Support\Catalog\ContentCatalog;
use Database\Seeders\Support\DemoClock;
use Illuminate\Database\Seeder;

/**
 * Seeds the NutriMind game content: learning modules with their activities, missions and rewards.
 */
class ContentSeeder extends Seeder
{
    /**
     * When the content team published the content of the school year.
     */
    private const CONTENT_RELEASED_AT = '2026-05-20 09:00:00';

    /**
     * When the 3rd Quarter module still in preparation was started.
     */
    private const DRAFT_MODULE_CREATED_AT = '2026-10-30 14:10:00';

    /**
     * When the missions and rewards of the school year were set up.
     */
    private const MISSIONS_RELEASED_AT = '2026-05-25 10:00:00';

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $clock = DemoClock::start();
        $subjectIds = Subject::query()->pluck('id', 'code');

        $this->seedModules($subjectIds->all());
        $this->seedMissions($subjectIds->all(), $clock);
        $this->seedRewards();
    }

    /**
     * Seed the learning modules and their activities.
     *
     * @param  array<string, int>  $subjectIds
     */
    private function seedModules(array $subjectIds): void
    {
        $modules = ContentCatalog::modules();
        $released = DemoClock::at(self::CONTENT_RELEASED_AT);

        $createdAt = fn (int $index, array $module): string => $module['published']
            ? DemoClock::format($released + $index * 20 * 60)
            : self::DRAFT_MODULE_CREATED_AT;

        BulkInsert::models(LearningModule::class, array_map(fn (array $module, int $index): array => [
            'subject_id' => $subjectIds[$module['subject']],
            'grade_level' => $module['grade'],
            'quarter' => $module['quarter'],
            'title' => $module['title'],
            'world_name' => $module['world'],
            'description' => $module['description'],
            'competency_code' => $module['code'],
            'learning_competency' => $module['competency'],
            'sort_order' => $module['sort'],
            'is_published' => $module['published'],
            'created_at' => $createdAt($index, $module),
            'updated_at' => $createdAt($index, $module),
        ], $modules, array_keys($modules)));

        $moduleIds = LearningModule::query()->pluck('id', 'competency_code');
        $activities = [];

        foreach ($modules as $index => $module) {
            foreach ($module['activities'] as $position => $activity) {
                $activities[] = [
                    'learning_module_id' => $moduleIds[$module['code']],
                    'title' => $activity['title'],
                    'type' => $activity['type'],
                    'description' => $activity['description'],
                    'content_url' => null,
                    'estimated_minutes' => $activity['minutes'],
                    'points' => ContentCatalog::ACTIVITY_POINTS[$activity['type']],
                    'sort_order' => $position + 1,
                    'is_published' => $module['published'],
                    'created_at' => $createdAt($index, $module),
                    'updated_at' => $createdAt($index, $module),
                ];
            }
        }

        BulkInsert::models(LearningActivity::class, $activities);
    }

    /**
     * Seed the missions; dated missions were created a week before they start (or recently, for upcoming ones).
     *
     * @param  array<string, int>  $subjectIds
     */
    private function seedMissions(array $subjectIds, DemoClock $clock): void
    {
        $moduleIds = LearningModule::query()->pluck('id', 'competency_code');
        $released = DemoClock::at(self::MISSIONS_RELEASED_AT);
        $rows = [];

        foreach (ContentCatalog::missions() as $index => $mission) {
            $createdAt = match (true) {
                $mission['starts_on'] !== null => min(DemoClock::at($mission['starts_on'].' 09:00:00') - 7 * DemoClock::DAY, $clock->now - 2 * DemoClock::DAY),
                ! $mission['active'] => $clock->now - 8 * DemoClock::DAY,
                default => $released + $index * 15 * 60,
            };

            $updatedAt = $mission['active'] || $mission['ends_on'] === null
                ? $createdAt
                : DemoClock::at($mission['ends_on'].' 16:00:00') + 3 * DemoClock::DAY;

            $rows[] = [
                'subject_id' => $subjectIds[$mission['subject']],
                'learning_module_id' => $mission['module'] === null ? null : $moduleIds[$mission['module']],
                'grade_level' => $mission['grade'],
                'title' => $mission['title'],
                'description' => $mission['description'],
                'objective' => $mission['objective'],
                'type' => $mission['type'],
                'difficulty' => $mission['difficulty'],
                'points_reward' => $mission['points'],
                'starts_on' => $mission['starts_on'],
                'ends_on' => $mission['ends_on'],
                'sort_order' => $index + 1,
                'is_active' => $mission['active'],
                'created_at' => DemoClock::format($createdAt),
                'updated_at' => DemoClock::format($updatedAt),
            ];
        }

        BulkInsert::models(Mission::class, $rows);
    }

    /**
     * Seed the rewards; the seasonal Nutrition Month badge was retired after July.
     */
    private function seedRewards(): void
    {
        $released = DemoClock::at(self::MISSIONS_RELEASED_AT);

        BulkInsert::models(Reward::class, array_map(fn (array $reward, int $index): array => [
            'name' => $reward['name'],
            'description' => $reward['description'],
            'type' => $reward['type'],
            'icon' => $reward['icon'],
            'color' => $reward['color'],
            'criteria' => $reward['criteria'],
            'points_required' => $reward['points_required'],
            'is_active' => $reward['active'],
            'created_at' => DemoClock::format($released + $index * 10 * 60),
            'updated_at' => $reward['active'] ? DemoClock::format($released + $index * 10 * 60) : '2026-08-03 10:30:00',
        ], ContentCatalog::rewards(), array_keys(ContentCatalog::rewards())));
    }
}
