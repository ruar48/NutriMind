<?php

namespace Tests\Fixtures;

use App\Enums\GradeLevel;
use App\Enums\MissionType;
use App\Enums\ProgressStatus;
use App\Models\ActivityProgress;
use App\Models\Classroom;
use App\Models\LearningActivity;
use App\Models\LearningModule;
use App\Models\Mission;
use App\Models\MissionProgress;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\Reward;
use App\Models\StudentProfile;
use App\Models\Subject;
use App\Models\User;
use Carbon\CarbonImmutable;

/**
 * A Grade 5 learner with a hand-computed history, for learner progress tests. Freeze time at NOW first.
 *
 * Classrooms: Health 5 – Sampaguita and Science 5 – Rosal (active), Math 5 – Mabini (archived).
 * Best attempts: Healthy Plate 80 % (passed; an earlier 60 % failed), Healthy Snacks 70 % (failed, offline),
 * Digestion 95 % (passed), Fractions 50 % (failed; an older 40 % before the 8-week window). Food Safety has
 * only an unsubmitted attempt. Overall: 4 quizzes taken, 2 passed, average 73.8 (73.75), Did Not Meet Expectations;
 * Health only: 2 taken, 1 passed, average 75.0.
 * Missions: Rainbow Plate (Health) completed, Water Warrior (Science) completed, Snack Swap Hero (Health) in progress,
 * Market Explorer (Math) completed, Label Reader (Science) not started.
 * Activities the learner can play: Health A1 (completed) + A2 (in progress), Science A6 (completed) + A7; completed
 * activities that do not count: unpublished, Grade 6, unpublished module, Math.
 * Rewards: Hydration Hero (Nov 10) then Breakfast Champion (Nov 1). 1,240 points → level 3, 240 / 500 XP.
 * Another learner has progress on the same content.
 */
final class LearnerProgressFixture
{
    /**
     * The moment the fixture's dates are relative to (a Friday; the ISO week started Monday, Nov 9).
     */
    public const NOW = '2026-11-13 10:00:00';

    private function __construct(
        public readonly User $student,
        public readonly Subject $health,
        public readonly Subject $science,
        public readonly Subject $math,
        public readonly Classroom $healthClassroom,
        public readonly Classroom $scienceClassroom,
        public readonly Quiz $healthyPlateQuiz,
        public readonly Quiz $healthySnacksQuiz,
        public readonly Quiz $digestionQuiz,
        public readonly Quiz $fractionsQuiz,
        /** @var array<string, QuizAttempt> */
        public readonly array $attempts,
        /** @var array<string, MissionProgress> */
        public readonly array $missions,
        /** @var array<string, Reward> */
        public readonly array $rewards,
    ) {}

    /**
     * Build the learner and their history.
     */
    public static function create(): self
    {
        $health = Subject::factory()->create(['name' => 'Health', 'code' => 'HEALTH', 'color' => 'leaf', 'icon' => 'apple']);
        $science = Subject::factory()->create(['name' => 'Science', 'code' => 'SCI', 'color' => 'sky', 'icon' => 'sprout']);
        $math = Subject::factory()->create(['name' => 'Mathematics', 'code' => 'MATH', 'color' => 'grape', 'icon' => 'shapes']);

        $healthClassroom = Classroom::factory()->for($health)->create(['grade_level' => GradeLevel::Five, 'name' => 'Health 5 – Sampaguita']);
        $scienceClassroom = Classroom::factory()->for($science)->create(['grade_level' => GradeLevel::Five, 'name' => 'Science 5 – Rosal']);
        $mathClassroom = Classroom::factory()->archived()->for($math)->create(['grade_level' => GradeLevel::Five, 'name' => 'Math 5 – Mabini']);

        $student = User::factory()->studentWithoutProfile()
            ->enrolledIn($healthClassroom, $scienceClassroom, $mathClassroom)
            ->create(['name' => 'Juan Dela Cruz', 'username' => 'jdelacruz']);
        StudentProfile::factory()->for($student, 'user')->create([
            'lrn' => '100000000001',
            'grade_level' => GradeLevel::Five,
            'total_points' => 1240,
            'level' => 3,
            'last_active_at' => '2026-11-12 18:00:00',
            'last_synced_at' => '2026-11-12 18:05:00',
        ]);
        $classmate = User::factory()->student(GradeLevel::Five)->enrolledIn($healthClassroom)->create();

        $healthyPlateQuiz = self::quiz($health, 'Healthy Plate');
        $healthySnacksQuiz = self::quiz($health, 'Healthy Snacks');
        $foodSafetyQuiz = self::quiz($health, 'Food Safety');
        $digestionQuiz = self::quiz($science, 'Digestion');
        $fractionsQuiz = self::quiz($math, 'Fractions of a Plate');

        $attempts = [
            'healthyPlateFirst' => self::attempt($healthyPlateQuiz, $student, 6, 10, '2026-10-20 09:00:00'),
            'healthyPlateBest' => self::attempt($healthyPlateQuiz, $student, 8, 10, '2026-11-10 09:00:00', attemptNumber: 2),
            'healthySnacks' => self::attempt($healthySnacksQuiz, $student, 7, 10, '2026-11-11 15:00:00', offline: true),
            'digestion' => self::attempt($digestionQuiz, $student, 19, 20, '2026-11-03 10:00:00'),
            'fractionsBest' => self::attempt($fractionsQuiz, $student, 5, 10, '2026-09-22 08:00:00'),
            'fractionsBeforeWindow' => self::attempt($fractionsQuiz, $student, 4, 10, '2026-09-20 08:00:00'),
        ];
        QuizAttempt::factory()->inProgress()->for($foodSafetyQuiz)->for($student, 'student')->create();
        self::attempt($healthyPlateQuiz, $classmate, 10, 10, '2026-11-12 09:00:00');

        $rainbowPlate = self::mission($health, 'Rainbow Plate', MissionType::Main);
        $waterWarrior = self::mission($science, 'Water Warrior', MissionType::Daily);
        $snackSwap = self::mission($health, 'Snack Swap Hero', MissionType::Challenge);
        $marketExplorer = self::mission($math, 'Market Explorer', MissionType::Weekly);
        $labelReader = self::mission($science, 'Label Reader', MissionType::Daily);

        $missions = [
            'rainbowPlate' => MissionProgress::factory()->completed()->for($rainbowPlate)->for($student, 'student')
                ->create(['started_at' => '2026-11-11 08:00:00', 'completed_at' => '2026-11-12 08:00:00', 'updated_at' => '2026-11-12 08:00:00']),
            'waterWarrior' => MissionProgress::factory()->completed()->for($waterWarrior)->for($student, 'student')
                ->create(['started_at' => '2026-11-04 08:00:00', 'completed_at' => '2026-11-05 08:00:00', 'updated_at' => '2026-11-05 08:00:00']),
            'snackSwap' => MissionProgress::factory()->inProgress()->for($snackSwap)->for($student, 'student')
                ->create(['progress_percent' => 40, 'started_at' => '2026-11-12 07:00:00', 'updated_at' => '2026-11-13 07:00:00']),
            'marketExplorer' => MissionProgress::factory()->completed()->for($marketExplorer)->for($student, 'student')
                ->create(['started_at' => '2026-09-30 08:00:00', 'completed_at' => '2026-10-01 08:00:00', 'updated_at' => '2026-10-01 08:00:00']),
            'labelReader' => MissionProgress::factory()->for($labelReader)->for($student, 'student')
                ->create(['status' => ProgressStatus::NotStarted, 'progress_percent' => 0, 'started_at' => null, 'updated_at' => '2026-11-01 08:00:00']),
        ];
        MissionProgress::factory()->completed()->for($snackSwap)->for($classmate, 'student')->create();

        $healthModule = LearningModule::factory()->for($health)->create(['grade_level' => GradeLevel::Five]);
        $healthGradeSixModule = LearningModule::factory()->for($health)->create(['grade_level' => GradeLevel::Six]);
        $healthDraftModule = LearningModule::factory()->unpublished()->for($health)->create(['grade_level' => GradeLevel::Five]);
        $scienceModule = LearningModule::factory()->for($science)->create(['grade_level' => GradeLevel::Five]);
        $mathModule = LearningModule::factory()->for($math)->create(['grade_level' => GradeLevel::Five]);

        $sortFoodGroups = LearningActivity::factory()->for($healthModule, 'module')->create();
        $buildPlate = LearningActivity::factory()->for($healthModule, 'module')->create();
        $hiddenHealthActivity = LearningActivity::factory()->unpublished()->for($healthModule, 'module')->create();
        $gradeSixActivity = LearningActivity::factory()->for($healthGradeSixModule, 'module')->create();
        $draftModuleActivity = LearningActivity::factory()->for($healthDraftModule, 'module')->create();
        $digestionActivity = LearningActivity::factory()->for($scienceModule, 'module')->create();
        LearningActivity::factory()->for($scienceModule, 'module')->create();
        $mathActivity = LearningActivity::factory()->for($mathModule, 'module')->create();

        foreach ([$sortFoodGroups, $hiddenHealthActivity, $gradeSixActivity, $draftModuleActivity, $digestionActivity, $mathActivity] as $activity) {
            ActivityProgress::factory()->completed()->for($activity, 'activity')->for($student, 'student')->create();
        }
        ActivityProgress::factory()->for($buildPlate, 'activity')->for($student, 'student')->create();
        ActivityProgress::factory()->completed()->for($buildPlate, 'activity')->for($classmate, 'student')->create();

        $breakfastChampion = Reward::factory()->create(['name' => 'Breakfast Champion', 'icon' => 'sun', 'color' => 'sun']);
        $hydrationHero = Reward::factory()->create(['name' => 'Hydration Hero', 'icon' => 'droplet', 'color' => 'sky']);
        $student->rewards()->attach($breakfastChampion, ['awarded_at' => '2026-11-01 08:00:00']);
        $student->rewards()->attach($hydrationHero, ['awarded_at' => '2026-11-10 12:00:00']);
        $classmate->rewards()->attach($breakfastChampion, ['awarded_at' => '2026-11-02 08:00:00']);

        return new self(
            student: $student,
            health: $health,
            science: $science,
            math: $math,
            healthClassroom: $healthClassroom,
            scienceClassroom: $scienceClassroom,
            healthyPlateQuiz: $healthyPlateQuiz,
            healthySnacksQuiz: $healthySnacksQuiz,
            digestionQuiz: $digestionQuiz,
            fractionsQuiz: $fractionsQuiz,
            attempts: $attempts,
            missions: $missions,
            rewards: ['breakfastChampion' => $breakfastChampion, 'hydrationHero' => $hydrationHero],
        );
    }

    /**
     * Create a Grade 5 learner who has a profile but no classrooms, points, progress or rewards yet.
     *
     * @param  array<string, mixed>  $profile  Learner profile attributes to override.
     */
    public static function newcomer(array $profile = [], string $name = 'Bea Santos'): User
    {
        $student = User::factory()->studentWithoutProfile()->create(['name' => $name, 'username' => 'bsantos']);

        StudentProfile::factory()->for($student, 'user')->create([
            'lrn' => null,
            'grade_level' => GradeLevel::Five,
            'total_points' => 0,
            'level' => 1,
            'last_active_at' => null,
            'last_synced_at' => null,
            ...$profile,
        ]);

        return $student;
    }

    /**
     * Create a published Grade 5 quiz in the subject.
     */
    private static function quiz(Subject $subject, string $title): Quiz
    {
        return Quiz::factory()->published()->for($subject)->create(['title' => $title, 'grade_level' => GradeLevel::Five]);
    }

    /**
     * Create a submitted attempt with the given score; it passes at 75 %.
     */
    private static function attempt(Quiz $quiz, User $student, int $score, int $maxScore, string $submittedAt, bool $offline = false, int $attemptNumber = 1): QuizAttempt
    {
        $percentage = round($score / $maxScore * 100, 2);
        $submitted = CarbonImmutable::parse($submittedAt);

        return QuizAttempt::factory()->for($quiz)->for($student, 'student')->create([
            'attempt_number' => $attemptNumber,
            'score' => $score,
            'max_score' => $maxScore,
            'percentage' => $percentage,
            'passed' => $percentage >= 75,
            'submitted_at' => $submitted,
            'is_offline' => $offline,
            'synced_at' => $offline ? $submitted->addHours(5) : $submitted,
        ]);
    }

    /**
     * Create an active mission in the subject.
     */
    private static function mission(Subject $subject, string $title, MissionType $type): Mission
    {
        return Mission::factory()->for($subject)->create(['title' => $title, 'type' => $type, 'points_reward' => 100, 'grade_level' => null]);
    }
}
