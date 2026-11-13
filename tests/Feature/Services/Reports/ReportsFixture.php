<?php

namespace Tests\Feature\Services\Reports;

use App\Enums\GradeLevel;
use App\Enums\MissionType;
use App\Models\Classroom;
use App\Models\Mission;
use App\Models\MissionProgress;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\Subject;
use App\Models\User;
use App\Services\Reports\Report;
use App\Services\Reports\ReportColumn;
use App\Services\Reports\ReportRow;
use App\Services\Reports\ReportStat;
use Carbon\CarbonImmutable;

/**
 * A small school with hand-computed report figures. Freeze time at NOW first.
 *
 * Teachers: Santos (Health + Science), Reyes (Health). Classrooms: Health 5 – Rizal and Science 5 – Rizal (Santos),
 * Health 6 – Mabini (Reyes), Health 5 – Old (Santos, archived; Ana's class last year).
 * Learners: Ana Cruz (G5; Health 5 – Rizal, Science 5 – Rizal, Health 5 – Old; 1,240 points → level 3; played 2 days ago),
 * Ben Diaz (G5; Health 5 – Rizal; played 10 days ago), Carla Santos (G6; Health 6 – Mabini; never played).
 * Quizzes: Healthy Plate (Health 5 – Rizal, published Nov 1, due Nov 10): Ana 60 % (Nov 3, failed) then 80 % (Nov 6,
 * passed), Ben 70 % (Nov 7, failed) plus an unsubmitted attempt. Digestion (Science 5 – Rizal, closed, due Nov 5): Ana
 * 90 % (Nov 4). Food Groups (Reyes, Health 6 – Mabini, published Nov 2, due Nov 20): Carla 95 % (Nov 8). Snack Draft
 * (draft, Health 5 – Rizal) has no attempts. Old Assessment (closed, only Health 5 – Old): Ana 50 % (Oct 1).
 * Missions: Rainbow Plate (Health G5, 100 XP): Ana completed Nov 6, Ben in progress since Nov 7. Body Builders (Health
 * G6, 80 XP): Carla completed Nov 8. Water Warrior (Science, both grades, 60 XP): Ana completed Nov 2. Old Quest
 * (Science G5, inactive, 40 XP): Ana completed Oct 20. Hidden Draft (Health G5, inactive, no progress).
 */
final class ReportsFixture
{
    /**
     * The moment the fixture's dates are relative to (Friday).
     */
    public const NOW = '2026-11-13 10:00:00';

    private function __construct(
        public readonly Subject $health,
        public readonly Subject $science,
        public readonly User $santos,
        public readonly User $reyes,
        public readonly Classroom $health5,
        public readonly Classroom $science5,
        public readonly Classroom $health6,
        public readonly Classroom $oldHealth5,
        public readonly User $ana,
        public readonly User $ben,
        public readonly User $carla,
        public readonly Quiz $healthyPlate,
        public readonly Quiz $digestion,
        public readonly Quiz $foodGroups,
        public readonly Quiz $snackDraft,
        public readonly Quiz $oldAssessment,
        public readonly Mission $rainbowPlate,
        public readonly Mission $bodyBuilders,
        public readonly Mission $waterWarrior,
        public readonly Mission $oldQuest,
        public readonly Mission $hiddenDraft,
    ) {}

    /**
     * Build the school.
     */
    public static function create(): self
    {
        $health = Subject::factory()->create(['name' => 'Health', 'code' => 'HEALTH', 'color' => 'berry', 'icon' => 'heart-pulse']);
        $science = Subject::factory()->create(['name' => 'Science', 'code' => 'SCIENCE', 'color' => 'teal', 'icon' => 'leaf']);

        $santos = User::factory()->teaching($health, $science)->create(['name' => 'Maricel Santos']);
        $reyes = User::factory()->teaching($health)->create(['name' => 'Lorna Reyes']);

        $health5 = Classroom::factory()->for($santos, 'teacher')->for($health)->create(['name' => 'Health 5 – Rizal', 'grade_level' => GradeLevel::Five, 'section' => 'Rizal']);
        $science5 = Classroom::factory()->for($santos, 'teacher')->for($science)->create(['name' => 'Science 5 – Rizal', 'grade_level' => GradeLevel::Five, 'section' => 'Rizal']);
        $health6 = Classroom::factory()->for($reyes, 'teacher')->for($health)->create(['name' => 'Health 6 – Mabini', 'grade_level' => GradeLevel::Six, 'section' => 'Mabini']);
        $oldHealth5 = Classroom::factory()->archived()->for($santos, 'teacher')->for($health)->create(['name' => 'Health 5 – Old', 'grade_level' => GradeLevel::Five, 'section' => 'Old']);

        $ana = User::factory()->student(GradeLevel::Five)->enrolledIn($health5, $science5, $oldHealth5)->create(['name' => 'Ana Cruz']);
        $ana->studentProfile->forceFill(['lrn' => '136742210001', 'total_points' => 1240, 'last_active_at' => now()->subDays(2)])->save();
        $ben = User::factory()->student(GradeLevel::Five)->enrolledIn($health5)->create(['name' => 'Ben Diaz']);
        $ben->studentProfile->forceFill(['lrn' => '136742210002', 'total_points' => 300, 'last_active_at' => now()->subDays(10)])->save();
        $carla = User::factory()->student(GradeLevel::Six)->enrolledIn($health6)->create(['name' => 'Carla Santos']);
        $carla->studentProfile->forceFill(['lrn' => '136742200003', 'total_points' => 2600, 'last_active_at' => null])->save();

        $healthyPlate = Quiz::factory()->published()->for($santos, 'teacher')->for($health)
            ->create(['title' => 'Healthy Plate Quiz', 'grade_level' => GradeLevel::Five, 'passing_score' => 75, 'published_at' => CarbonImmutable::parse('2026-11-01 08:00')]);
        $digestion = Quiz::factory()->closed()->for($santos, 'teacher')->for($science)
            ->create(['title' => 'Digestion Quiz', 'grade_level' => GradeLevel::Five, 'passing_score' => 75, 'published_at' => CarbonImmutable::parse('2026-10-25 08:00')]);
        $foodGroups = Quiz::factory()->published()->for($reyes, 'teacher')->for($health)
            ->create(['title' => 'Food Groups Quiz', 'grade_level' => GradeLevel::Six, 'passing_score' => 75, 'published_at' => CarbonImmutable::parse('2026-11-02 08:00')]);
        $snackDraft = Quiz::factory()->for($santos, 'teacher')->for($health)
            ->create(['title' => 'Snack Draft Quiz', 'grade_level' => GradeLevel::Five]);
        $oldAssessment = Quiz::factory()->closed()->for($santos, 'teacher')->for($health)
            ->create(['title' => 'Old Assessment', 'grade_level' => GradeLevel::Five, 'passing_score' => 75, 'published_at' => CarbonImmutable::parse('2026-03-01 08:00')]);

        $health5->quizzes()->attach($healthyPlate, ['due_at' => CarbonImmutable::parse('2026-11-10 17:00')]);
        $health5->quizzes()->attach($snackDraft, ['due_at' => CarbonImmutable::parse('2026-11-09 17:00')]);
        $science5->quizzes()->attach($digestion, ['due_at' => CarbonImmutable::parse('2026-11-05 17:00')]);
        $health6->quizzes()->attach($foodGroups, ['due_at' => CarbonImmutable::parse('2026-11-20 17:00')]);
        $oldHealth5->quizzes()->attach($oldAssessment, ['due_at' => CarbonImmutable::parse('2026-03-20 17:00')]);

        self::attempt($healthyPlate, $ana, $health5, 6, 60.0, false, '2026-11-03 09:00');
        self::attempt($healthyPlate, $ana, $health5, 8, 80.0, true, '2026-11-06 09:00', attemptNumber: 2);
        self::attempt($healthyPlate, $ben, $health5, 7, 70.0, false, '2026-11-07 09:00');
        QuizAttempt::factory()->inProgress()->for($healthyPlate)->for($ben, 'student')->for($health5)->create(['attempt_number' => 2]);
        self::attempt($digestion, $ana, $science5, 9, 90.0, true, '2026-11-04 09:00');
        self::attempt($foodGroups, $carla, $health6, 19, 95.0, true, '2026-11-08 09:00', maxScore: 20);
        self::attempt($oldAssessment, $ana, $oldHealth5, 5, 50.0, false, '2026-10-01 09:00');

        $rainbowPlate = Mission::factory()->for($health)->create(['title' => 'Rainbow Plate', 'grade_level' => GradeLevel::Five, 'type' => MissionType::Main, 'points_reward' => 100, 'sort_order' => 1, 'is_active' => true]);
        $bodyBuilders = Mission::factory()->for($health)->create(['title' => 'Body Builders', 'grade_level' => GradeLevel::Six, 'type' => MissionType::Weekly, 'points_reward' => 80, 'sort_order' => 2, 'is_active' => true]);
        $hiddenDraft = Mission::factory()->for($health)->create(['title' => 'Hidden Draft', 'grade_level' => GradeLevel::Five, 'type' => MissionType::Daily, 'points_reward' => 30, 'sort_order' => 3, 'is_active' => false]);
        $waterWarrior = Mission::factory()->for($science)->create(['title' => 'Water Warrior', 'grade_level' => null, 'type' => MissionType::Challenge, 'points_reward' => 60, 'sort_order' => 1, 'is_active' => true]);
        $oldQuest = Mission::factory()->for($science)->create(['title' => 'Old Quest', 'grade_level' => GradeLevel::Five, 'type' => MissionType::Main, 'points_reward' => 40, 'sort_order' => 2, 'is_active' => false]);

        self::completed($rainbowPlate, $ana, '2026-11-06 10:00', 100);
        MissionProgress::factory()->inProgress()->for($rainbowPlate)->for($ben, 'student')->create(['started_at' => CarbonImmutable::parse('2026-11-07 10:00'), 'progress_percent' => 40]);
        self::completed($bodyBuilders, $carla, '2026-11-08 10:00', 80);
        self::completed($waterWarrior, $ana, '2026-11-02 10:00', 60);
        self::completed($oldQuest, $ana, '2026-10-20 10:00', 40);

        return new self(
            $health, $science, $santos, $reyes, $health5, $science5, $health6, $oldHealth5, $ana, $ben, $carla,
            $healthyPlate, $digestion, $foodGroups, $snackDraft, $oldAssessment,
            $rainbowPlate, $bodyBuilders, $waterWarrior, $oldQuest, $hiddenDraft,
        );
    }

    /**
     * Get every row of a report as its displayed texts keyed by column key.
     *
     * @return list<array<string, string>>
     */
    public static function texts(Report $report): array
    {
        return $report->rows
            ->map(fn (ReportRow $row): array => collect($report->columns)
                ->mapWithKeys(fn (ReportColumn $column): array => [$column->key => $column->display($row)])
                ->all())
            ->all();
    }

    /**
     * Get the displayed summary tiles of a report, label => value.
     *
     * @return array<string, string>
     */
    public static function summary(Report $report): array
    {
        return collect($report->summary)->mapWithKeys(fn (ReportStat $stat): array => [$stat->label => $stat->value])->all();
    }

    /**
     * Record a submitted attempt with an exact score.
     */
    private static function attempt(Quiz $quiz, User $student, Classroom $classroom, int $score, float $percentage, bool $passed, string $submittedAt, int $maxScore = 10, int $attemptNumber = 1): void
    {
        QuizAttempt::factory()->for($quiz)->for($student, 'student')->for($classroom)->create([
            'attempt_number' => $attemptNumber,
            'score' => $score,
            'max_score' => $maxScore,
            'percentage' => $percentage,
            'passed' => $passed,
            'duration_seconds' => 600,
            'submitted_at' => CarbonImmutable::parse($submittedAt),
            'started_at' => CarbonImmutable::parse($submittedAt)->subMinutes(10),
            'synced_at' => CarbonImmutable::parse($submittedAt),
        ]);
    }

    /**
     * Record a completed mission.
     */
    private static function completed(Mission $mission, User $student, string $completedAt, int $points): void
    {
        MissionProgress::factory()->completed()->for($mission)->for($student, 'student')->create([
            'started_at' => CarbonImmutable::parse($completedAt)->subDay(),
            'completed_at' => CarbonImmutable::parse($completedAt),
            'synced_at' => CarbonImmutable::parse($completedAt),
            'points_earned' => $points,
        ]);
    }
}
