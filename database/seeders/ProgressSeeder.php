<?php

namespace Database\Seeders;

use App\Enums\LearningMode;
use App\Enums\MissionType;
use App\Enums\ProgressStatus;
use App\Models\AcademicTerm;
use App\Models\ActivityProgress;
use App\Models\Classroom;
use App\Models\LearningActivity;
use App\Models\LearningModule;
use App\Models\Mission;
use App\Models\MissionProgress;
use App\Models\QuizAttempt;
use App\Models\Reward;
use App\Models\SchoolYear;
use App\Models\StudentProfile;
use App\Models\Subject;
use App\Models\User;
use App\Services\Settings;
use Database\Seeders\Support\BulkInsert;
use Database\Seeders\Support\Catalog\ContentCatalog;
use Database\Seeders\Support\Catalog\SchoolCatalog;
use Database\Seeders\Support\DemoClock;
use Database\Seeders\Support\DemoRandom;
use Database\Seeders\Support\LearnerArchetype;
use Database\Seeders\Support\LearnerProfile;
use Database\Seeders\Support\LearnerProfiles;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Seeder;

/**
 * Seeds the learners' game progress: activity and mission progress, total points and levels, the last time each
 * learner played and synced, and the rewards they earned.
 *
 * Learners finished most of the past quarters' worlds and are partway through the current quarter, faster or slower
 * depending on their profile; a few curious learners in "learner's choice" classrooms explore the next quarter.
 * Total points are the sum of the points earned, levels follow the level rule, and every reward was earned by
 * meeting its criteria.
 */
class ProgressSeeder extends Seeder
{
    /**
     * The share of activity and mission progress played offline and synced later.
     */
    private const OFFLINE_SHARE = 0.1;

    /**
     * How the score of each activity type differs from the learner's ability (videos and lessons are easier).
     */
    private const SCORE_ADJUSTMENT = ['lesson' => 4, 'video' => 6, 'reading' => 2, 'practice' => -2, 'mini_game' => 0];

    /**
     * How likely learners are to complete a mission of each difficulty, compared with an easy one.
     */
    private const DIFFICULTY_FACTOR = ['easy' => 1.0, 'medium' => 0.85, 'hard' => 0.65];

    /**
     * How many activity progress rows are buffered before the buffers are written.
     */
    private const ROWS_PER_FLUSH = 400;

    /**
     * The seeded random stream of this seeder.
     */
    private DemoRandom $random;

    /**
     * The anchored clock of this run.
     */
    private DemoClock $clock;

    /**
     * The quarters of the active school year: first and last moment, keyed by sequence.
     *
     * @var array<int, array{0: int, 1: int}>
     */
    private array $quarters = [];

    /**
     * The sequence of the current quarter.
     */
    private int $currentQuarter = 1;

    /**
     * The number of published activities of each published module, keyed by module ID.
     *
     * @var array<int, int>
     */
    private array $activityCounts = [];

    /**
     * Run the database seeds.
     */
    public function run(Settings $settings): void
    {
        $this->random = DemoRandom::stream('progress');
        $this->clock = DemoClock::start();

        $activeYear = SchoolYear::query()->where('name', SchoolCatalog::ACTIVE_SCHOOL_YEAR)->sole();

        foreach (AcademicTerm::query()->whereBelongsTo($activeYear)->orderBy('sequence')->get() as $term) {
            $this->quarters[$term->sequence] = [$term->starts_on->getTimestamp(), $term->ends_on->getTimestamp() + DemoClock::DAY - 1];

            if ($term->is_active) {
                $this->currentQuarter = $term->sequence;
            }
        }

        $profiles = LearnerProfiles::load($this->clock->now);
        $grades = StudentProfile::query()->orderBy('user_id')->get(['user_id', 'grade_level'])
            ->mapWithKeys(fn (StudentProfile $profile): array => [$profile->user_id => $profile->grade_level->value]);
        $subjects = $this->subjectsByLearner($activeYear);
        $modules = $this->modulesBySubjectAndGrade();
        $missions = Mission::query()->orderBy('sort_order')->get();
        $attempts = QuizAttempt::query()->orderBy('submitted_at')->orderBy('id')->toBase()
            ->get(['student_id', 'quiz_id', 'score', 'max_score', 'passed', 'submitted_at', 'synced_at'])
            ->groupBy('student_id');
        $subjectCodes = Subject::query()->pluck('code', 'id')->all();
        $rewards = $this->rewards();

        $activityRows = [];
        $missionRows = [];
        $awardRows = [];

        foreach ($grades as $studentId => $grade) {
            $profile = $profiles[$studentId];

            if (! $profile->hasPlayed) {
                continue;
            }

            $activities = $this->activityProgress($profile, $modules, $subjects[$studentId] ?? [], $grade);
            $learnerMissions = $this->missionProgress($profile, $missions, $activities, $subjects[$studentId] ?? [], $grade);
            $learnerAttempts = ($attempts[$studentId] ?? collect())->all();

            foreach ($activities as $activity) {
                $activityRows[] = ['learning_activity_id' => $activity['activity_id'], 'student_id' => $studentId, ...$activity['row']];
            }

            foreach ($learnerMissions as $mission) {
                $missionRows[] = ['mission_id' => $mission['mission_id'], 'student_id' => $studentId, ...$mission['row']];
            }

            foreach ($this->earnedRewards($rewards, $activities, $learnerMissions, $learnerAttempts, $subjectCodes) as $rewardId => $awardedAt) {
                $awardRows[] = [
                    'reward_id' => $rewardId,
                    'student_id' => $studentId,
                    'awarded_at' => DemoClock::format($awardedAt),
                    'created_at' => DemoClock::format($awardedAt),
                    'updated_at' => DemoClock::format($awardedAt),
                ];
            }

            $this->updateProfile($settings, $studentId, $profile, $activities, $learnerMissions, $learnerAttempts);

            if (count($activityRows) >= self::ROWS_PER_FLUSH) {
                $this->flush($activityRows, $missionRows, $awardRows);
            }
        }

        $this->flush($activityRows, $missionRows, $awardRows);
    }

    /**
     * Insert the buffered progress and award rows and empty the buffers (flushing as it goes keeps memory use flat).
     *
     * @param  list<array<string, mixed>>  $activityRows
     * @param  list<array<string, mixed>>  $missionRows
     * @param  list<array<string, mixed>>  $awardRows
     */
    private function flush(array &$activityRows, array &$missionRows, array &$awardRows): void
    {
        BulkInsert::models(ActivityProgress::class, $activityRows);
        BulkInsert::models(MissionProgress::class, $missionRows);
        BulkInsert::pivot((new User)->rewards(), $awardRows);

        $activityRows = $missionRows = $awardRows = [];
    }

    /**
     * Get each learner's subjects this school year with the learning mode and enrollment time of the classroom.
     *
     * @return array<int, array<int, array{mode: LearningMode, enrolled_at: int}>>
     */
    private function subjectsByLearner(SchoolYear $activeYear): array
    {
        $classrooms = Classroom::query()->active()->whereBelongsTo($activeYear)->get(['id', 'subject_id', 'learning_mode'])->keyBy('id');
        $subjects = [];

        $enrollments = (new Classroom)->students()->newPivotStatement()
            ->whereIn('classroom_id', $classrooms->modelKeys())
            ->orderBy('student_id')
            ->orderBy('classroom_id')
            ->get(['classroom_id', 'student_id', 'enrolled_at']);

        foreach ($enrollments as $enrollment) {
            $classroom = $classrooms[$enrollment->classroom_id];

            $subjects[(int) $enrollment->student_id][$classroom->subject_id] = [
                'mode' => $classroom->learning_mode,
                'enrolled_at' => DemoClock::at($enrollment->enrolled_at),
            ];
        }

        return $subjects;
    }

    /**
     * Get the published modules with their published activities, by subject, grade and quarter, in path order.
     *
     * @return array<int, array<int, array<int, list<LearningModule>>>>
     */
    private function modulesBySubjectAndGrade(): array
    {
        $modules = LearningModule::query()
            ->published()
            ->with(['activities' => fn (HasMany $query) => $query->published()])
            ->orderBy('subject_id')
            ->orderBy('grade_level')
            ->orderBy('quarter')
            ->orderBy('sort_order')
            ->get();

        $grouped = [];

        foreach ($modules as $module) {
            $grouped[$module->subject_id][$module->grade_level->value][$module->quarter][] = $module;
            $this->activityCounts[$module->id] = $module->activities->count();
        }

        return $grouped;
    }

    /**
     * Get the rewards with the rule that awards them and the moment they became available.
     *
     * @return list<array{id: int, rule: array{0: string, 1: int|string, 2?: int}, since: int}>
     */
    private function rewards(): array
    {
        $rules = array_column(ContentCatalog::rewards(), 'rule', 'name');

        return Reward::query()->orderBy('id')->get(['id', 'name', 'created_at'])
            ->map(fn (Reward $reward): array => ['id' => $reward->id, 'rule' => $rules[$reward->name], 'since' => $reward->created_at->getTimestamp()])
            ->all();
    }

    /**
     * Simulate a learner's progress through the activities of their subjects' worlds.
     *
     * @param  array<int, array<int, array<int, list<LearningModule>>>>  $modules
     * @param  array<int, array{mode: LearningMode, enrolled_at: int}>  $subjects
     * @return list<array{activity_id: int, module_id: int, subject_id: int, completed: bool, points: int, at: int, synced_at: int, row: array<string, mixed>}>
     */
    private function activityProgress(LearnerProfile $profile, array $modules, array $subjects, int $grade): array
    {
        $progress = [];

        foreach ($subjects as $subjectId => $subject) {
            foreach ($modules[$subjectId][$grade] ?? [] as $quarter => $quarterModules) {
                $path = [];

                foreach ($quarterModules as $module) {
                    foreach ($module->activities as $activity) {
                        $path[] = $activity;
                    }
                }

                [$from, $until] = $this->window($quarter, $subject['enrolled_at'], $profile);
                $completed = $this->completedCount($profile, $subject['mode'], $quarter, count($path), $until);

                if ($until - $from < 2 * DemoClock::HOUR || ($completed === 0 && $quarter > $this->currentQuarter)) {
                    continue;
                }

                $startsNext = $completed < count($path) && $this->random->chance(0.55);
                $moments = [];

                for ($index = 0; $index < $completed + ($startsNext ? 1 : 0); $index++) {
                    $moments[] = DemoClock::playTime($this->random, $from, $until);
                }

                sort($moments);

                foreach ($moments as $position => $moment) {
                    $progress[] = $this->activityRow($profile, $path[$position], $subjectId, $moment, $position < $completed);
                }
            }
        }

        return $progress;
    }

    /**
     * Get the time window in which the learner played a quarter's worlds.
     *
     * @return array{0: int, 1: int}
     */
    private function window(int $quarter, int $enrolledAt, LearnerProfile $profile): array
    {
        $latest = min($profile->activeUntil($this->clock->now), $this->clock->now - 10 * 60);

        if ($quarter > $this->currentQuarter) {
            return [max($this->clock->now - 21 * DemoClock::DAY, $enrolledAt + DemoClock::HOUR), $latest];
        }

        [$startsAt, $endsAt] = $this->quarters[$quarter];

        return [max($startsAt + DemoClock::DAY, $enrolledAt + 2 * DemoClock::HOUR), min($endsAt, $latest)];
    }

    /**
     * Get how many activities of a quarter's path the learner completed.
     */
    private function completedCount(LearnerProfile $profile, LearningMode $mode, int $quarter, int $pathLength, int $until): int
    {
        if ($quarter < $this->currentQuarter) {
            $share = $profile->archetype === LearnerArchetype::Star
                ? 1.0
                : max(0.3, min(1.0, $this->random->normal($profile->archetype->pastModuleCompletion(), 0.06)));

            return (int) round($share * $pathLength);
        }

        if ($quarter === $this->currentQuarter) {
            [$startsAt, $endsAt] = $this->quarters[$quarter];
            $elapsed = max(0.0, min(1.0, ($until - $startsAt) / ($endsAt - $startsAt)));

            return (int) floor(min(1.0, $elapsed * $profile->archetype->pace() * $this->random->between(0.85, 1.15)) * $pathLength);
        }

        $explores = $mode === LearningMode::LearnerChoice
            && in_array($profile->archetype, [LearnerArchetype::Star, LearnerArchetype::Strong], true)
            && $this->random->chance(0.55);

        return $explores ? min($pathLength, $this->random->int(1, 2)) : 0;
    }

    /**
     * Build an activity progress row: completed with a score and points, or still in progress.
     *
     * @return array{activity_id: int, module_id: int, subject_id: int, completed: bool, points: int, at: int, synced_at: int, row: array<string, mixed>}
     */
    private function activityRow(LearnerProfile $profile, LearningActivity $activity, int $subjectId, int $moment, bool $completed): array
    {
        $estimate = ($activity->estimated_minutes ?? 10) * 60;
        $timeSpent = (int) round($estimate * ($completed ? $this->random->between(0.8, 1.5) : $this->random->between(0.2, 0.6)));
        $syncedAt = $this->syncedAt($profile, $moment);
        $score = (int) round($this->random->normal($profile->abilityAt($moment, $this->clock->now) * 100 + self::SCORE_ADJUSTMENT[$activity->type->value], 7));

        return [
            'activity_id' => $activity->id,
            'module_id' => $activity->learning_module_id,
            'subject_id' => $subjectId,
            'completed' => $completed,
            'points' => $completed ? $activity->points : 0,
            'at' => $moment,
            'synced_at' => $syncedAt,
            'row' => [
                'status' => ($completed ? ProgressStatus::Completed : ProgressStatus::InProgress)->value,
                'score' => $completed ? max(40, min(100, $score)) : null,
                'points_earned' => $completed ? $activity->points : 0,
                'time_spent_seconds' => $timeSpent,
                'completed_at' => $completed ? DemoClock::format($moment) : null,
                'synced_at' => DemoClock::format($syncedAt),
                'created_at' => DemoClock::format($moment - $timeSpent),
                'updated_at' => DemoClock::format($syncedAt),
            ],
        ];
    }

    /**
     * Simulate a learner's mission progress.
     *
     * Story missions follow the learner's progress in their world; dated missions run only inside their dates;
     * daily and weekly missions show the current day's or week's progress. Missions that are retired without dates,
     * or that have not started yet, have no progress.
     *
     * @param  iterable<Mission>  $missions
     * @param  list<array{module_id: int, completed: bool, at: int, synced_at: int, row: array<string, mixed>}>  $activities
     * @param  array<int, array{mode: LearningMode, enrolled_at: int}>  $subjects
     * @return list<array{mission_id: int, title: string, completed: bool, points: int, at: int, synced_at: int, row: array<string, mixed>}>
     */
    private function missionProgress(LearnerProfile $profile, iterable $missions, array $activities, array $subjects, int $grade): array
    {
        $progress = [];
        $today = DemoClock::date($this->clock->now);

        foreach ($missions as $mission) {
            $subject = $subjects[$mission->subject_id] ?? null;

            if ($subject === null
                || ($mission->grade_level !== null && $mission->grade_level->value !== $grade)
                || ($mission->starts_on !== null && $mission->starts_on->toDateString() > $today)
                || (! $mission->is_active && $mission->ends_on === null)) {
                continue;
            }

            $entry = match (true) {
                $mission->type === MissionType::Main && $mission->learning_module_id !== null => $this->storyMission($mission, $activities),
                $mission->starts_on !== null => $this->datedMission($profile, $mission, $subject['enrolled_at']),
                default => $this->recurringMission($profile, $mission, $subject['enrolled_at']),
            };

            if ($entry !== null) {
                $progress[] = $entry;
            }
        }

        return $progress;
    }

    /**
     * Build a story mission's progress from the learner's progress in its world.
     *
     * @param  list<array{module_id: int, completed: bool, at: int, synced_at: int, row: array<string, mixed>}>  $activities
     * @return array{mission_id: int, title: string, completed: bool, points: int, at: int, synced_at: int, row: array<string, mixed>}|null
     */
    private function storyMission(Mission $mission, array $activities): ?array
    {
        $worldActivities = array_values(array_filter($activities, fn (array $activity): bool => $activity['module_id'] === $mission->learning_module_id));

        if ($worldActivities === []) {
            return null;
        }

        $total = $this->activityCounts[$mission->learning_module_id];
        $completedCount = count(array_filter($worldActivities, fn (array $activity): bool => $activity['completed']));
        $startedAt = min(array_map(fn (array $activity): int => DemoClock::at($activity['row']['created_at']), $worldActivities));
        $lastMoment = max(array_column($worldActivities, 'at'));
        $lastSync = max(array_column($worldActivities, 'synced_at'));

        if ($completedCount === $total) {
            $completedAt = min($lastMoment + $this->random->int(1, 10) * 60, $lastSync);

            return $this->missionRow($mission, ProgressStatus::Completed, 100, $startedAt, max($completedAt, $lastMoment), $lastSync);
        }

        $percent = max(1, min(99, (int) round(($completedCount + 0.5 * (count($worldActivities) - $completedCount)) / $total * 100)));

        return $this->missionRow($mission, ProgressStatus::InProgress, $percent, $startedAt, null, $lastSync);
    }

    /**
     * Build the progress of a mission that runs between two dates.
     *
     * @return array{mission_id: int, title: string, completed: bool, points: int, at: int, synced_at: int, row: array<string, mixed>}|null
     */
    private function datedMission(LearnerProfile $profile, Mission $mission, int $enrolledAt): ?array
    {
        $opensAt = DemoClock::at($mission->starts_on->toDateString().' 07:00:00');
        $closesAt = DemoClock::at($mission->ends_on->toDateString().' 21:00:00');
        $from = max($opensAt, $enrolledAt + DemoClock::HOUR);
        $until = min($closesAt, $profile->activeUntil($this->clock->now), $this->clock->now - 10 * 60);

        if ($until - $from < 2 * DemoClock::HOUR) {
            return null;
        }

        $hasEnded = $closesAt < $this->clock->now;
        $elapsed = $hasEnded ? 1.0 : max(0.0, min(1.0, ($this->clock->now - $opensAt) / ($closesAt - $opensAt)));

        if (! $this->random->chance($profile->engagement * 0.85 * ($hasEnded ? 1.0 : max(0.3, sqrt($elapsed))))) {
            return null;
        }

        $startedAt = DemoClock::playTime($this->random, $from, $from + (int) (0.4 * ($until - $from)));
        $completes = $this->random->chance(min(0.95, $profile->ability * self::DIFFICULTY_FACTOR[$mission->difficulty->value] * ($hasEnded ? 1.05 : min(1.0, $elapsed * 1.4))));

        return $this->missionWithin($profile, $mission, $completes, $startedAt, $until);
    }

    /**
     * Build the current day's or week's progress of a daily or weekly mission.
     *
     * @return array{mission_id: int, title: string, completed: bool, points: int, at: int, synced_at: int, row: array<string, mixed>}|null
     */
    private function recurringMission(LearnerProfile $profile, Mission $mission, int $enrolledAt): ?array
    {
        $isDaily = $mission->type === MissionType::Daily;
        $until = min($profile->activeUntil($this->clock->now), $this->clock->now - 10 * 60);
        $from = max($until - ($isDaily ? 2 : 7) * DemoClock::DAY, $enrolledAt + DemoClock::HOUR);

        if ($until - $from < 2 * DemoClock::HOUR || ! $this->random->chance($profile->engagement * ($isDaily ? 0.8 : 0.7))) {
            return null;
        }

        $startedAt = DemoClock::playTime($this->random, $from, $from + (int) (0.6 * ($until - $from)));
        $completes = $this->random->chance(($isDaily ? 0.35 : 0.3) + ($isDaily ? 0.55 : 0.5) * $profile->ability);

        return $this->missionWithin($profile, $mission, $completes, $startedAt, $until);
    }

    /**
     * Build a mission row completed (or still in progress) between its start and the end of its window.
     *
     * @return array{mission_id: int, title: string, completed: bool, points: int, at: int, synced_at: int, row: array<string, mixed>}
     */
    private function missionWithin(LearnerProfile $profile, Mission $mission, bool $completes, int $startedAt, int $until): array
    {
        if ($completes && $until - $startedAt >= 2 * DemoClock::HOUR) {
            $completedAt = DemoClock::playTime($this->random, $startedAt + DemoClock::HOUR, $until);

            return $this->missionRow($mission, ProgressStatus::Completed, 100, $startedAt, $completedAt, $this->syncedAt($profile, $completedAt));
        }

        $touchedAt = DemoClock::playTime($this->random, $startedAt, $until);

        return $this->missionRow($mission, ProgressStatus::InProgress, $this->random->int(15, 85), $startedAt, null, $this->syncedAt($profile, $touchedAt));
    }

    /**
     * Build a mission progress row; a completed mission earns its reward points.
     *
     * @return array{mission_id: int, title: string, completed: bool, points: int, at: int, synced_at: int, row: array<string, mixed>}
     */
    private function missionRow(Mission $mission, ProgressStatus $status, int $percent, int $startedAt, ?int $completedAt, int $syncedAt): array
    {
        $completed = $status === ProgressStatus::Completed;

        return [
            'mission_id' => $mission->id,
            'title' => $mission->title,
            'completed' => $completed,
            'points' => $completed ? $mission->points_reward : 0,
            'at' => $completedAt ?? $syncedAt,
            'synced_at' => $syncedAt,
            'row' => [
                'status' => $status->value,
                'progress_percent' => $percent,
                'points_earned' => $completed ? $mission->points_reward : 0,
                'started_at' => DemoClock::format($startedAt),
                'completed_at' => $completedAt === null ? null : DemoClock::format($completedAt),
                'synced_at' => DemoClock::format($syncedAt),
                'created_at' => DemoClock::format($startedAt),
                'updated_at' => DemoClock::format($syncedAt),
            ],
        ];
    }

    /**
     * Get when progress made at a moment reached the server: right away, or later for offline play.
     */
    private function syncedAt(LearnerProfile $profile, int $moment): int
    {
        if (! $this->random->chance(self::OFFLINE_SHARE)) {
            return $moment;
        }

        $latest = min($this->clock->now - 60, $profile->activeUntil($this->clock->now));

        return max($moment, min($moment + $this->random->int(60, 30 * 60) * 60, $latest));
    }

    /**
     * Get the rewards the learner earned, with the moment each one was awarded.
     *
     * @param  list<array{id: int, rule: array{0: string, 1: int|string, 2?: int}, since: int}>  $rewards
     * @param  list<array{module_id: int, subject_id: int, completed: bool, points: int, at: int}>  $activities
     * @param  list<array{title: string, completed: bool, points: int, at: int}>  $missions
     * @param  list<object>  $attempts
     * @param  array<int, string>  $subjectCodes
     * @return array<int, int>
     */
    private function earnedRewards(array $rewards, array $activities, array $missions, array $attempts, array $subjectCodes): array
    {
        $completedActivities = array_values(array_filter($activities, fn (array $activity): bool => $activity['completed']));
        $completedMissions = array_values(array_filter($missions, fn (array $mission): bool => $mission['completed']));
        $earned = [];

        foreach ($rewards as $reward) {
            $since = $reward['since'];
            $after = fn (array $moments): array => array_values(array_filter($moments, fn (int $moment): bool => $moment >= $since));

            $moment = match ($reward['rule'][0]) {
                'activities' => $this->nth($after(array_column($completedActivities, 'at')), (int) $reward['rule'][1]),
                'points' => $this->momentReachingPoints([...$completedActivities, ...$completedMissions], $since, (int) $reward['rule'][1]),
                'module' => $this->nth($after($this->worldCompletions($activities)), (int) $reward['rule'][1]),
                'missions' => $this->nth($after(array_column($completedMissions, 'at')), (int) $reward['rule'][1]),
                'quizzes_passed' => $this->nth($after($this->firstPasses($attempts)), (int) $reward['rule'][1]),
                'perfect' => $this->nth($after(array_map(
                    fn (object $attempt): int => DemoClock::at($attempt->submitted_at),
                    array_filter($attempts, fn (object $attempt): bool => (int) $attempt->score === (int) $attempt->max_score),
                )), (int) $reward['rule'][1]),
                'mission' => $this->nth($after(array_column(array_filter($completedMissions, fn (array $mission): bool => $mission['title'] === $reward['rule'][1]), 'at')), 1),
                'subject_activities' => $this->nth($after(array_column(array_filter(
                    $completedActivities,
                    fn (array $activity): bool => $subjectCodes[$activity['subject_id']] === $reward['rule'][1],
                ), 'at')), (int) $reward['rule'][2]),
            };

            if ($moment !== null) {
                $earned[$reward['id']] = min($moment + $this->random->int(1, 10) * 60, $this->clock->now - 60);
            }
        }

        return $earned;
    }

    /**
     * Get the n-th earliest moment, or null when there are fewer moments.
     *
     * @param  list<int>  $moments
     */
    private function nth(array $moments, int $count): ?int
    {
        sort($moments);

        return $moments[$count - 1] ?? null;
    }

    /**
     * Get the moment the points earned since a moment first reached the goal, or null if they never did.
     *
     * @param  list<array{points: int, at: int}>  $events
     */
    private function momentReachingPoints(array $events, int $since, int $goal): ?int
    {
        usort($events, fn (array $a, array $b): int => $a['at'] <=> $b['at']);
        $points = 0;

        foreach ($events as $event) {
            $points += $event['points'];

            if ($points >= $goal) {
                return max($event['at'], $since);
            }
        }

        return null;
    }

    /**
     * Get the moments the learner completed every activity of a world.
     *
     * @param  list<array{module_id: int, completed: bool, at: int}>  $activities
     * @return list<int>
     */
    private function worldCompletions(array $activities): array
    {
        $byModule = [];

        foreach ($activities as $activity) {
            $byModule[$activity['module_id']][] = $activity;
        }

        $moments = [];

        foreach ($byModule as $moduleId => $moduleActivities) {
            $completed = array_filter($moduleActivities, fn (array $activity): bool => $activity['completed']);

            if (count($completed) === $this->activityCounts[$moduleId]) {
                $moments[] = max(array_column($completed, 'at'));
            }
        }

        return $moments;
    }

    /**
     * Get the moment the learner first passed each quiz.
     *
     * @param  list<object>  $attempts
     * @return list<int>
     */
    private function firstPasses(array $attempts): array
    {
        $passes = [];

        foreach ($attempts as $attempt) {
            if ((bool) $attempt->passed && ! isset($passes[$attempt->quiz_id])) {
                $passes[$attempt->quiz_id] = DemoClock::at($attempt->submitted_at);
            }
        }

        return array_values($passes);
    }

    /**
     * Store the learner's total points, level and the last time they played and synced.
     *
     * @param  list<array{completed: bool, points: int, at: int, synced_at: int}>  $activities
     * @param  list<array{completed: bool, points: int, at: int, synced_at: int}>  $missions
     * @param  list<object>  $attempts
     */
    private function updateProfile(Settings $settings, int $studentId, LearnerProfile $profile, array $activities, array $missions, array $attempts): void
    {
        $points = array_sum(array_column($activities, 'points')) + array_sum(array_column($missions, 'points'));
        $level = min((int) $settings->get('max_level'), intdiv($points, max(1, (int) $settings->get('points_per_level'))) + 1);

        $moments = [...array_column($activities, 'at'), ...array_column($missions, 'at'), ...array_map(fn (object $attempt): int => DemoClock::at($attempt->submitted_at), $attempts)];
        $syncs = [...array_column($activities, 'synced_at'), ...array_column($missions, 'synced_at'), ...array_map(fn (object $attempt): int => DemoClock::at($attempt->synced_at), $attempts)];

        $lastActiveAt = $moments === [] ? null : max($moments);

        if (! $profile->isInactive()) {
            $lastSession = $this->clock->now - $this->random->int(12 * 60, $profile->archetype->hoursSinceLastSession() * 3600);
            $lastActiveAt = max($lastActiveAt ?? $lastSession, $lastSession);
        }

        $lastSyncedAt = $lastActiveAt === null ? null : max([...$syncs, $lastActiveAt + ($profile->isInactive() ? 0 : $this->random->int(0, 15 * 60))]);
        $lastSyncedAt = $lastSyncedAt === null ? null : min($lastSyncedAt, $this->clock->now);

        StudentProfile::query()->where('user_id', $studentId)->toBase()->update([
            'total_points' => $points,
            'level' => $level,
            'last_active_at' => $lastActiveAt === null ? null : DemoClock::format($lastActiveAt),
            'last_synced_at' => $lastSyncedAt === null ? null : DemoClock::format($lastSyncedAt),
            'updated_at' => DemoClock::format($lastSyncedAt ?? $this->clock->now),
        ]);
    }
}
