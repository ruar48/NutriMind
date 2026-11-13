<?php

namespace Tests\Feature\Services\Analytics;

use App\Enums\GradeLevel;
use App\Enums\ProgressStatus;
use App\Models\ActivityProgress;
use App\Models\Classroom;
use App\Models\LearnerFeedback;
use App\Models\LearningActivity;
use App\Models\LearningModule;
use App\Models\Mission;
use App\Models\MissionProgress;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\QuizAttemptAnswer;
use App\Models\QuizQuestion;
use App\Models\Subject;
use App\Models\User;
use Carbon\CarbonImmutable;

/**
 * A small school with hand-computed analytics, for the insights tests. Travel to NOW before create().
 *
 * Teachers: Teresa (Health + Science; classrooms Health 5 – Rizal and Science 5 – Rizal, Grade 5) and Ramon
 * (Health; Health 6 – Mabini, Grade 6). Learners: Ana and Ben (both Grade 5 classrooms), Carlo (Grade 6),
 * Ella (Health 5, never played) and Dina (Health 5, deactivated account — excluded everywhere).
 *
 * Last 30 days (Oct 15 – Nov 13): results Ana-Q1 90 (best of 60, 90; passed), Ben-Q1 70, Ana-Q2 80 (passed),
 * Ben-Q2 50 (offline), Carlo-Q3 85 (passed) → average 75.0, pass rate 60.0, 6 attempts; missions completed:
 * Ana (Nov 12), Ben (Oct 16); activities completed: Ana (Nov 9); active learners Ana, Ben, Carlo.
 * Previous 30 days (Sep 15 – Oct 14): Ana-Q1 40, Carlo-Q3 75 (passed) → average 57.5, pass rate 50.0, 2 attempts,
 * 1 mission completed (Ana, Oct 10), active learners Ana and Carlo.
 * Learner averages: Ana 85 (VS), Ben 60 (DNME), Carlo 85 (VS), Ella none.
 * Profiles: Ana 1,240 XP, active Nov 12, synced Nov 12; Ben 600 XP, active Nov 11, synced Nov 1 (overdue);
 * Carlo 1,240 XP, active Oct 30, never synced; Ella 0 XP, never played; Dina 5,000 XP (inactive account).
 * "Which food is a GO food?" (Rice) was missed by Ana's 60% attempt and by Ben, both answering Fruit (3 answers,
 * 2 missed); "Which food is a GROW food?" (Fish) was missed by Ben with Candy (3 answers, 1 missed); the water
 * question has only 2 answers. Dina's attempt (40%) misses both plate questions but is excluded.
 * Teacher activity in the last 30 days: Teresa created 1 quiz, published 2, sent 1 feedback; Ramon created 1,
 * published 1, sent none (his feedback is from Sep 20).
 */
final class AnalyticsSchoolFixture
{
    /**
     * The moment the fixture's dates are relative to (a Friday; the ISO week started Monday, Nov 9).
     */
    public const NOW = '2026-11-13 10:00:00';

    private function __construct(
        public readonly Subject $health,
        public readonly Subject $science,
        public readonly User $teresa,
        public readonly User $ramon,
        public readonly Classroom $health5,
        public readonly Classroom $science5,
        public readonly Classroom $health6,
        public readonly User $ana,
        public readonly User $ben,
        public readonly User $carlo,
        public readonly User $ella,
        public readonly User $dina,
        public readonly LearningModule $plateModule,
        public readonly LearningModule $snackModule,
        public readonly Quiz $plateQuiz,
        public readonly Quiz $waterQuiz,
        public readonly Quiz $snackQuiz,
        public readonly QuizQuestion $goFoodQuestion,
        public readonly QuizQuestion $growFoodQuestion,
        public readonly QuizQuestion $waterQuestion,
    ) {}

    /**
     * Build the school.
     */
    public static function create(): self
    {
        $health = Subject::factory()->create(['name' => 'Health', 'code' => 'HEALTH', 'color' => 'leaf']);
        $science = Subject::factory()->create(['name' => 'Science', 'code' => 'SCI', 'color' => 'sky']);

        $teresa = User::factory()->teaching($health, $science)->create(['name' => 'Teresa Santos']);
        $ramon = User::factory()->teaching($health)->create(['name' => 'Ramon Cruz']);

        $health5 = Classroom::factory()->for($teresa, 'teacher')->for($health)->create(['name' => 'Health 5 – Rizal', 'grade_level' => GradeLevel::Five, 'section' => 'Rizal']);
        $science5 = Classroom::factory()->for($teresa, 'teacher')->for($science)->create(['name' => 'Science 5 – Rizal', 'grade_level' => GradeLevel::Five, 'section' => 'Rizal']);
        $health6 = Classroom::factory()->for($ramon, 'teacher')->for($health)->create(['name' => 'Health 6 – Mabini', 'grade_level' => GradeLevel::Six, 'section' => 'Mabini']);

        $ana = self::learner('Ana Reyes', GradeLevel::Five, [$health5, $science5], 1240, '2026-11-12 08:00:00', '2026-11-12 08:05:00');
        $ben = self::learner('Ben Garcia', GradeLevel::Five, [$health5, $science5], 600, '2026-11-11 15:00:00', '2026-11-01 15:00:00');
        $carlo = self::learner('Carlo Mendoza', GradeLevel::Six, [$health6], 1240, '2026-10-30 09:00:00', null);
        $ella = self::learner('Ella Ramos', GradeLevel::Five, [$health5], 0, null, null);
        $dina = self::learner('Dina Lopez', GradeLevel::Five, [$health5], 5000, '2026-11-13 08:00:00', '2026-11-13 08:00:00', active: false);

        $plateModule = LearningModule::factory()->for($health)->create(['title' => 'The Healthy Plate', 'grade_level' => GradeLevel::Five, 'quarter' => 2, 'sort_order' => 1]);
        $snackModule = LearningModule::factory()->for($health)->create(['title' => 'Smart Snacks', 'grade_level' => GradeLevel::Six, 'quarter' => 2, 'sort_order' => 1]);

        $plateQuiz = Quiz::factory()->published()->for($teresa, 'teacher')->for($plateModule, 'module')
            ->create(['title' => 'Healthy Plate Check', 'created_at' => '2026-10-01 08:00:00', 'published_at' => '2026-10-16 08:00:00']);
        $waterQuiz = Quiz::factory()->published()->for($teresa, 'teacher')->for($science)
            ->create(['title' => 'Water Cycle Quiz', 'grade_level' => GradeLevel::Five, 'created_at' => '2026-10-18 08:00:00', 'published_at' => '2026-10-19 08:00:00']);
        $snackQuiz = Quiz::factory()->published()->for($ramon, 'teacher')->for($snackModule, 'module')
            ->create(['title' => 'Snack Smarts', 'created_at' => '2026-10-20 08:00:00', 'published_at' => '2026-11-01 08:00:00']);

        $goFoodQuestion = QuizQuestion::factory()->multipleChoice()->for($plateQuiz)->create([
            'prompt' => 'Which food is a GO food?',
            'choices' => ['Rice', 'Fish', 'Fruit', 'Candy'],
            'correct_answer' => '0',
        ]);
        $growFoodQuestion = QuizQuestion::factory()->multipleChoice()->for($plateQuiz)->create([
            'prompt' => 'Which food is a GROW food?',
            'choices' => ['Rice', 'Fish', 'Fruit', 'Candy'],
            'correct_answer' => '1',
        ]);
        $waterQuestion = QuizQuestion::factory()->identification()->for($waterQuiz)->create([
            'prompt' => 'What do plants release through their leaves?',
            'correct_answer' => 'Water vapor|Water',
        ]);

        $anaFirstTry = self::attempt($plateQuiz, $ana, $health5, 60, '2026-11-10 09:00:00');
        self::answer($anaFirstTry, $goFoodQuestion, '2', false);
        self::answer($anaFirstTry, $growFoodQuestion, '1', true);

        $anaBest = self::attempt($plateQuiz, $ana, $health5, 90, '2026-11-12 09:00:00', attemptNumber: 2);
        self::answer($anaBest, $goFoodQuestion, '0', true);
        self::answer($anaBest, $growFoodQuestion, '1', true);

        $benPlate = self::attempt($plateQuiz, $ben, $health5, 70, '2026-11-11 10:00:00');
        self::answer($benPlate, $goFoodQuestion, '2', false);
        self::answer($benPlate, $growFoodQuestion, '3', false);

        $anaWater = self::attempt($waterQuiz, $ana, $science5, 80, '2026-10-20 10:00:00');
        self::answer($anaWater, $waterQuestion, 'water', true);

        $benWater = self::attempt($waterQuiz, $ben, $science5, 50, '2026-11-02 10:00:00', offline: true);
        self::answer($benWater, $waterQuestion, 'juice', false);

        self::attempt($snackQuiz, $carlo, $health6, 85, '2026-11-13 09:00:00');

        $dinaAttempt = self::attempt($plateQuiz, $dina, $health5, 40, '2026-11-12 11:00:00');
        self::answer($dinaAttempt, $goFoodQuestion, '3', false);
        self::answer($dinaAttempt, $growFoodQuestion, '0', false);

        QuizAttempt::factory()->inProgress()->for($plateQuiz)->for($ella, 'student')->for($health5)->create();

        self::attempt($plateQuiz, $ana, $health5, 40, '2026-10-14 23:00:00');
        self::attempt($snackQuiz, $carlo, $health6, 75, '2026-10-01 09:00:00');

        $plateMission = Mission::factory()->for($health)->create(['title' => 'Rainbow Plate', 'grade_level' => null]);
        $waterMission = Mission::factory()->for($science)->create(['title' => 'Water Warrior', 'grade_level' => null]);

        self::missionProgress($plateMission, $ana, '2026-11-05 08:00:00', '2026-11-12 16:00:00');
        self::missionProgress($waterMission, $ben, '2026-10-01 08:00:00', '2026-10-16 16:00:00');
        self::missionProgress($plateMission, $carlo, '2026-11-01 08:00:00', null);
        self::missionProgress($waterMission, $ana, '2026-10-05 08:00:00', '2026-10-10 16:00:00');
        self::missionProgress($plateMission, $dina, '2026-11-01 08:00:00', '2026-11-02 08:00:00');

        $plateActivity = LearningActivity::factory()->for($plateModule, 'module')->create(['title' => 'Build a plate']);
        ActivityProgress::factory()->completed()->for($plateActivity, 'activity')->for($ana, 'student')->create([
            'created_at' => '2026-11-09 08:00:00',
            'completed_at' => '2026-11-09 09:00:00',
            'synced_at' => '2026-11-09 09:00:00',
        ]);

        LearnerFeedback::factory()->for($teresa, 'teacher')->for($ben, 'student')->create(['classroom_id' => $health5->id, 'created_at' => '2026-11-05 08:00:00']);
        LearnerFeedback::factory()->for($ramon, 'teacher')->for($carlo, 'student')->create(['classroom_id' => $health6->id, 'created_at' => '2026-09-20 08:00:00']);

        return new self(
            $health, $science, $teresa, $ramon, $health5, $science5, $health6,
            $ana, $ben, $carlo, $ella, $dina, $plateModule, $snackModule, $plateQuiz, $waterQuiz, $snackQuiz,
            $goFoodQuestion, $growFoodQuestion, $waterQuestion,
        );
    }

    /**
     * Create an active (or deactivated) learner enrolled in the classrooms with the given game profile.
     *
     * @param  list<Classroom>  $classrooms
     */
    private static function learner(string $name, GradeLevel $grade, array $classrooms, int $points, ?string $lastActiveAt, ?string $lastSyncedAt, bool $active = true): User
    {
        $factory = User::factory()->student($grade)->enrolledIn(...$classrooms);
        $student = ($active ? $factory : $factory->inactive())->create(['name' => $name]);

        $student->studentProfile->forceFill([
            'total_points' => $points,
            'level' => intdiv($points, 500) + 1,
            'last_active_at' => $lastActiveAt,
            'last_synced_at' => $lastSyncedAt,
        ])->save();

        return $student;
    }

    /**
     * Create a submitted attempt with the given percentage (out of 10 points).
     */
    private static function attempt(Quiz $quiz, User $student, Classroom $classroom, int $percentage, string $submittedAt, int $attemptNumber = 1, bool $offline = false): QuizAttempt
    {
        return QuizAttempt::factory()->for($quiz)->for($student, 'student')->for($classroom)->create([
            'attempt_number' => $attemptNumber,
            'score' => intdiv($percentage, 10),
            'max_score' => 10,
            'percentage' => $percentage,
            'passed' => $percentage >= $quiz->passing_score,
            'submitted_at' => $submittedAt,
            'started_at' => CarbonImmutable::parse($submittedAt)->subMinutes(10),
            'is_offline' => $offline,
            'synced_at' => $submittedAt,
        ]);
    }

    /**
     * Store a learner's answer to a question of an attempt.
     */
    private static function answer(QuizAttempt $attempt, QuizQuestion $question, string $answer, bool $correct): void
    {
        QuizAttemptAnswer::factory()->for($attempt, 'attempt')->for($question, 'question')->create([
            'answer' => $answer,
            'is_correct' => $correct,
            'points_awarded' => $correct ? $question->points : 0,
        ]);
    }

    /**
     * Store a learner's mission progress, completed when a completion moment is given.
     */
    private static function missionProgress(Mission $mission, User $student, string $startedAt, ?string $completedAt): void
    {
        MissionProgress::factory()->for($mission)->for($student, 'student')->create([
            'status' => $completedAt === null ? ProgressStatus::InProgress : ProgressStatus::Completed,
            'progress_percent' => $completedAt === null ? 40 : 100,
            'points_earned' => $completedAt === null ? 0 : $mission->points_reward,
            'started_at' => $startedAt,
            'completed_at' => $completedAt,
            'synced_at' => $completedAt ?? $startedAt,
            'created_at' => $startedAt,
        ]);
    }
}
