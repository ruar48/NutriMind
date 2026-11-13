<?php

namespace Database\Seeders;

use App\Enums\LessonPlanStatus;
use App\Models\AcademicTerm;
use App\Models\LearningModule;
use App\Models\LessonPlan;
use App\Models\Mission;
use App\Models\Quiz;
use App\Models\SchoolYear;
use App\Models\Subject;
use App\Models\User;
use Database\Seeders\Support\BulkInsert;
use Database\Seeders\Support\Catalog\ContentCatalog;
use Database\Seeders\Support\Catalog\LessonPlanCatalog;
use Database\Seeders\Support\Catalog\PeopleCatalog;
use Database\Seeders\Support\Catalog\SchoolCatalog;
use Database\Seeders\Support\DemoClock;
use Database\Seeders\Support\DemoRandom;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Seeder;

/**
 * Seeds the teachers' weekly lesson plans and aligns most of them with game activities, missions and quizzes.
 *
 * A plan's status follows its week: completed before this week, ongoing this week and planned after it.
 */
class LessonPlanSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $clock = DemoClock::start();
        $random = DemoRandom::stream('lesson-plans');
        $teacherIds = User::query()->toBase()->pluck('id', 'email');
        $subjectIds = Subject::query()->pluck('id', 'code');
        $terms = AcademicTerm::query()
            ->whereBelongsTo(SchoolYear::query()->where('name', SchoolCatalog::ACTIVE_SCHOOL_YEAR)->sole())
            ->get(['id', 'sequence', 'starts_on'])
            ->keyBy('sequence');
        $modules = LearningModule::query()
            ->with(['activities' => fn (HasMany $query) => $query->published()])
            ->get(['id', 'competency_code'])
            ->keyBy('competency_code');
        $today = DemoClock::date($clock->now);
        $rows = [];

        foreach (LessonPlanCatalog::plans() as $plan) {
            $term = $terms[$plan['quarter']];
            $startsOn = $term->starts_on->getTimestamp() + ($plan['week'] - 1) * 7 * DemoClock::DAY;
            $endsOn = $startsOn + 4 * DemoClock::DAY;
            $status = match (true) {
                DemoClock::date($endsOn) < $today => LessonPlanStatus::Completed,
                DemoClock::date($startsOn) <= $today => LessonPlanStatus::Ongoing,
                default => LessonPlanStatus::Planned,
            };
            $createdAt = min(DemoClock::workTime($random, $startsOn - $random->int(3, 10) * DemoClock::DAY), $clock->now - DemoClock::DAY);
            $updatedAt = match ($status) {
                LessonPlanStatus::Completed => min(DemoClock::workTime($random, $endsOn + $random->int(0, 2) * DemoClock::DAY), $clock->now - DemoClock::HOUR),
                LessonPlanStatus::Ongoing => $clock->now - $random->int(1, 20) * DemoClock::HOUR,
                LessonPlanStatus::Planned => $createdAt,
            };
            $module = $plan['module'] === null ? null : ContentCatalog::moduleByCode($plan['module']);

            $rows[] = [
                'teacher_id' => $teacherIds[PeopleCatalog::teacherEmail($plan['teacher'])],
                'subject_id' => $subjectIds[$plan['subject']],
                'academic_term_id' => $term->id,
                'learning_module_id' => $module === null ? null : $modules[$module['code']]->id,
                'grade_level' => $plan['grade'],
                'title' => $plan['title'],
                'competency_code' => $plan['code'],
                'learning_competency' => $module['competency'] ?? $plan['competency'],
                'objectives' => "Learners will be able to:\n".implode("\n", array_map(
                    fn (int $index, string $objective): string => ($index + 1).'. '.$objective,
                    array_keys($plan['objectives']),
                    $plan['objectives'],
                )),
                'week_number' => $plan['week'],
                'starts_on' => DemoClock::date($startsOn),
                'ends_on' => DemoClock::date($endsOn),
                'status' => $status->value,
                'notes' => $plan['notes'],
                'created_at' => DemoClock::format($createdAt),
                'updated_at' => DemoClock::format(max($updatedAt, $createdAt)),
            ];
        }

        BulkInsert::models(LessonPlan::class, $rows);

        $this->align($random, $modules->all());
    }

    /**
     * Align the plans marked for alignment with two or three activities of their world, the world's story mission
     * and the teacher's quiz about the world.
     *
     * @param  array<string, LearningModule>  $modules
     */
    private function align(DemoRandom $random, array $modules): void
    {
        $planIds = [];

        foreach (LessonPlan::query()->toBase()->get(['id', 'teacher_id', 'title', 'created_at']) as $plan) {
            $planIds[$plan->teacher_id.'|'.$plan->title] = $plan;
        }

        $teacherIds = User::query()->toBase()->pluck('id', 'email');
        $missionIds = Mission::query()->whereNotNull('learning_module_id')->orderBy('id')->toBase()->get(['id', 'learning_module_id'])->groupBy('learning_module_id');
        $quizIds = Quiz::query()->whereNotNull('learning_module_id')->orderBy('created_at')->orderBy('id')->toBase()
            ->get(['id', 'teacher_id', 'learning_module_id'])
            ->groupBy(fn (object $quiz): string => $quiz->teacher_id.'|'.$quiz->learning_module_id);

        $activityRows = [];
        $missionRows = [];
        $quizRows = [];

        foreach (LessonPlanCatalog::plans() as $plan) {
            if (! $plan['align']) {
                continue;
            }

            $teacherId = $teacherIds[PeopleCatalog::teacherEmail($plan['teacher'])];
            $lessonPlan = $planIds[$teacherId.'|'.$plan['title']];
            $module = $modules[$plan['module']];
            $alignedAt = DemoClock::format(DemoClock::at($lessonPlan->created_at) + DemoClock::HOUR);
            $activityIds = $module->activities->modelKeys();
            $chosen = [...array_slice($activityIds, 0, 2), ...$random->shuffle(array_slice($activityIds, 2))];

            foreach (array_slice($chosen, 0, $random->int(2, 3)) as $activityId) {
                $activityRows[] = ['lesson_plan_id' => $lessonPlan->id, 'learning_activity_id' => $activityId, 'created_at' => $alignedAt, 'updated_at' => $alignedAt];
            }

            foreach ($missionIds[$module->id] ?? [] as $mission) {
                $missionRows[] = ['lesson_plan_id' => $lessonPlan->id, 'mission_id' => $mission->id, 'created_at' => $alignedAt, 'updated_at' => $alignedAt];
            }

            $quiz = ($quizIds[$teacherId.'|'.$module->id] ?? collect())->first();

            if ($quiz !== null) {
                $quizRows[] = ['lesson_plan_id' => $lessonPlan->id, 'quiz_id' => $quiz->id, 'created_at' => $alignedAt, 'updated_at' => $alignedAt];
            }
        }

        $lessonPlan = new LessonPlan;

        BulkInsert::pivot($lessonPlan->activities(), $activityRows);
        BulkInsert::pivot($lessonPlan->missions(), $missionRows);
        BulkInsert::pivot($lessonPlan->quizzes(), $quizRows);
    }
}
