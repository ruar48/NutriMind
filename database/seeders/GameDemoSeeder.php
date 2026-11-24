<?php

namespace Database\Seeders;

use App\Enums\Difficulty;
use App\Enums\GradeLevel;
use App\Enums\MissionType;
use App\Enums\QuestionType;
use App\Enums\QuizStatus;
use App\Enums\RewardType;
use App\Enums\Sex;
use App\Enums\UserRole;
use App\Models\Classroom;
use App\Models\Mission;
use App\Models\Quiz;
use App\Models\Reward;
use App\Models\StudentProfile;
use App\Models\Subject;
use App\Models\User;
use Database\Seeders\Support\Catalog\PeopleCatalog;
use Database\Seeders\Support\DemoPassword;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seeds what a demo of the Unity game connection needs, on top of the normal demo data:
 *
 * - the game's 18 missions, each with its "Game mission ID" (G5-ENG-M1 …), so progress synced from the game lands
 *   on them; an "English" subject is added for the game's English world when the school has none;
 * - the game's 72 rewards under their exact in-game names (checkpoint rewards as badges, final-challenge rewards
 *   as trophies);
 * - two demo learners (Grade 5 and Grade 6) with a known username, LRN and password, enrolled in a classroom;
 * - one published quiz per demo learner, assigned to that classroom, with multiple choice, true/false and
 *   identification questions.
 *
 * Run it after the normal seed: `php artisan db:seed --class=GameDemoSeeder`. It is safe to run again: missions,
 * rewards, learners and quizzes are updated in place, and the two demo learners start over with no progress,
 * attempts, rewards or game sign-ins, so every demo starts from the same state.
 */
class GameDemoSeeder extends Seeder
{
    /**
     * The game's worlds: world code => [subject code, subject name used when the school has no such subject].
     */
    private const WORLDS = [
        'ENG' => ['ENGLISH', 'English'],
        'SCI' => ['SCIENCE', 'Science'],
        'PEH' => ['PE', 'Physical Education'],
    ];

    /**
     * The game's missions (Assets/Resources/Content/*.json): game key => [title, checkpoint rewards, final reward].
     */
    public const MISSIONS = [
        'G5-ENG-M1' => ['The Golden Lantern Adventure', ['Garden Clue Card', 'Footprint Clue Card', 'Lantern Location Card'], 'Golden Lantern Badge'],
        'G5-ENG-M2' => ['The Chronicles of Wisdom', ['Mirror Shard', 'Wisdom Book Page', 'Writer’s Seal'], 'Wisdom Crystal'],
        'G5-ENG-M3' => ['The Explorer’s Atlas', ['Atlas Page 1', 'Atlas Page 2', 'Atlas Page 3'], 'Explorer’s Atlas Badge'],
        'G6-ENG-M1' => ['The Critical Reader Quest', ['Leaf Symbol', 'Historical Plaque Piece', 'Reader’s Compass'], 'Critical Reader Badge'],
        'G6-ENG-M2' => ['The Persuasion Investigator', ['Campaign Ribbon', 'Technique Lens', 'Evidence Badge'], 'Persuasion Investigator Badge'],
        'G6-ENG-M3' => ['The Evidence-Based Communicator', ['Survey Token', 'Argument Page', 'Communicator Badge Piece'], 'Evidence-Based Communicator Badge'],
        'G5-SCI-M1' => ['The Missing Matter Museum', ['Matter Label 1', 'Research Note 2', 'Nature Exhibit Pass'], 'Museum Research Badge'],
        'G5-SCI-M2' => ['The Nature Rescue Expedition', ['Clinic Badge', 'Life Cycle Card', 'Force Explorer Seal'], 'Nature Rescue Badge'],
        'G5-SCI-M3' => ['The Power of Earth and Sky', ['Energy Core', 'Earth Explorer Token', 'Sky Watch Badge'], 'Earth & Sky Badge'],
        'G6-SCI-M1' => ['The Materials Investigation', ['Material Inspector Card', 'Reliable Research Seal', 'Life Science Token'], 'Materials Investigator Badge'],
        'G6-SCI-M2' => ['The Ecosystem Engineering Quest', ['Forest Web Token', 'Habitat Balance Seal', 'Engineer Medal'], 'Ecosystem Engineer Badge'],
        'G6-SCI-M3' => ['The Sky and Safety Expedition', ['Wave Research Badge', 'Safety Ranger Token', 'Sky Explorer Medal'], 'Sky & Safety Badge'],
        'G5-PEH-M1' => ['Strong Mind, Smart Moves', ['Calm Leaf', 'Field Flag', 'Kindness Shield'], 'Kindness and Movement Badge'],
        'G5-PEH-M2' => ['Growing Well, Playing Together', ['Growth Leaf', 'Net Play Star', 'Healthy Team Ribbon'], 'Growth and Teamwork Badge'],
        'G5-PEH-M3' => ['Safe Choices, Active Rhythms', ['Safety Token', 'Rhythm Ribbon', 'Community Safety Seal'], 'Active Safety Badge'],
        'G6-PEH-M1' => ['Whole-Self Wellness Challenge', ['Wellness Ring', 'Game Sense Pin', 'Balance Medal'], 'Whole-Self Wellness Badge'],
        'G6-PEH-M2' => ['Healthy Decisions and Game Strategy', ['Trusted Guide Card', 'Team Strategy Star', 'Fair Choice Emblem'], 'Respect and Strategy Badge'],
        'G6-PEH-M3' => ['Healthy Community, Active Environment', ['Health Watch Shield', 'Green Activity Leaf', 'Community Champion Badge'], 'Healthy Community Badge'],
    ];

    /**
     * The bonus a mission's final challenge is worth (the game's GameConfig.missionCompletePoints).
     */
    private const FINAL_CHALLENGE_POINTS = 100;

    /**
     * The demo learners: username => [name, grade, LRN, sex]. They sign in with PeopleCatalog::PASSWORD.
     */
    public const LEARNERS = [
        'gamedemo5' => ['Paolo Reyes', 5, '999900000005', 'male'],
        'gamedemo6' => ['Bea Santiago', 6, '999900000006', 'female'],
    ];

    /**
     * The title of each demo learner's quiz, by grade.
     */
    public const QUIZ_TITLE = 'Game Demo Quiz: Healthy Food Choices (Grade %d)';

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::transaction(function (): void {
            $this->seedMissions();
            $this->seedRewards();

            foreach (self::LEARNERS as $username => [$name, $grade, $lrn, $sex]) {
                $student = $this->seedLearner($username, $name, $grade, $lrn, $sex);
                $classroom = $this->classroomFor($grade);

                if ($classroom === null) {
                    $this->command?->warn("No active Grade {$grade} classroom: {$username} has no classroom or quiz.");

                    continue;
                }

                $classroom->students()->syncWithoutDetaching([$student->id => ['enrolled_at' => now()]]);
                $this->seedQuiz($classroom, $grade);
            }
        });

        $this->command?->info('Game demo data is ready. Sign in to the game with (password: '.PeopleCatalog::PASSWORD.'):');
        $this->command?->table(
            ['Username', 'LRN', 'Name', 'Grade'],
            collect(self::LEARNERS)->map(fn (array $learner, string $username): array => [$username, $learner[2], $learner[0], $learner[1]])->values()->all(),
        );
    }

    /**
     * Create or update the game's 18 missions under their game keys.
     */
    private function seedMissions(): void
    {
        $subjectIds = collect(self::WORLDS)->map(fn (array $subject): int => $this->subjectId(...$subject));

        foreach (self::MISSIONS as $gameKey => [$title]) {
            [$gradeCode, $world, $missionCode] = explode('-', $gameKey);
            $number = (int) substr($missionCode, 1);

            Mission::query()->updateOrCreate(['game_key' => $gameKey], [
                'subject_id' => $subjectIds[$world],
                'learning_module_id' => null,
                'grade_level' => GradeLevel::from((int) substr($gradeCode, 1)),
                'title' => $title,
                'description' => "Mission {$number} of the NutriMind game. Learners finish three checkpoints and a final challenge; the game sends their progress here.",
                'objective' => 'Finish all three checkpoints and the final challenge.',
                'type' => MissionType::Main,
                'difficulty' => [1 => Difficulty::Easy, 2 => Difficulty::Medium, 3 => Difficulty::Hard][$number],
                'points_reward' => self::FINAL_CHALLENGE_POINTS,
                'starts_on' => null,
                'ends_on' => null,
                'sort_order' => $number,
                'is_active' => true,
            ]);
        }
    }

    /**
     * Get the subject a game world belongs to, adding it when the school does not have it.
     */
    private function subjectId(string $code, string $name): int
    {
        return Subject::query()->where('code', $code)->value('id')
            ?? Subject::query()->create(['name' => $name, 'code' => $code, 'description' => 'Added for the NutriMind game demo.', 'is_active' => true])->id;
    }

    /**
     * Create or update the game's rewards under their exact in-game names.
     */
    private function seedRewards(): void
    {
        foreach (self::MISSIONS as $gameKey => [$title, $checkpointRewards, $finalReward]) {
            foreach ($checkpointRewards as $index => $name) {
                Reward::query()->updateOrCreate(['name' => $name], [
                    'description' => 'Found at checkpoint '.($index + 1)." of “{$title}”.",
                    'type' => RewardType::Badge,
                    'criteria' => 'Complete checkpoint '.($index + 1)." of {$gameKey} in the game.",
                    'is_active' => true,
                ]);
            }

            Reward::query()->updateOrCreate(['name' => $finalReward], [
                'description' => "Earned by finishing the final challenge of “{$title}”.",
                'type' => RewardType::Trophy,
                'criteria' => "Complete the final challenge of {$gameKey} in the game.",
                'is_active' => true,
            ]);
        }
    }

    /**
     * Create or reset a demo learner: active, with a known password and LRN, and no game progress.
     */
    private function seedLearner(string $username, string $name, int $grade, string $lrn, string $sex): User
    {
        $student = User::query()->firstOrNew(['username' => $username]);
        $student->forceFill([
            'name' => $name,
            'email' => null,
            'role' => UserRole::Student,
            'password' => DemoPassword::hash(),
            'is_active' => true,
        ])->save();

        $profile = StudentProfile::query()->firstOrNew(['user_id' => $student->id]);
        $profile->fill([
            'lrn' => $lrn,
            'grade_level' => GradeLevel::from($grade),
            'sex' => Sex::from($sex),
            'birthdate' => now()->subYears($grade + 5)->startOfYear()->toDateString(),
        ]);
        $profile->forceFill(['total_points' => 0, 'level' => 1, 'last_active_at' => null, 'last_synced_at' => null])->save();

        $student->missionProgress()->delete();
        $student->activityProgress()->delete();
        $student->quizAttempts()->delete();
        $student->rewards()->detach();
        $student->tokens()->delete();

        return $student;
    }

    /**
     * Get the active classroom of the grade (in the active school year when there is one) the demo learner joins.
     */
    private function classroomFor(int $grade): ?Classroom
    {
        $classrooms = fn () => Classroom::query()->active()->where('grade_level', $grade)->orderBy('id');

        return $classrooms()->whereHas('schoolYear', fn (Builder $years) => $years->active())->first()
            ?? $classrooms()->first();
    }

    /**
     * Create or replace the demo quiz of the classroom's teacher, published and assigned to the classroom.
     */
    private function seedQuiz(Classroom $classroom, int $grade): void
    {
        $quiz = Quiz::query()->firstOrNew(['title' => sprintf(self::QUIZ_TITLE, $grade)]);
        $quiz->forceFill([
            'teacher_id' => $classroom->teacher_id,
            'subject_id' => $classroom->subject_id,
            'learning_module_id' => null,
            'academic_term_id' => null,
            'grade_level' => GradeLevel::from($grade),
            'instructions' => 'A short quiz to show the NutriMind game and web app working together. Answer every question, then submit.',
            'time_limit_minutes' => 10,
            'passing_score' => 75,
            'max_attempts' => 3,
            'shuffle_questions' => false,
            'show_correct_answers' => true,
            'status' => QuizStatus::Published,
            'reviewed_at' => now(),
            'published_at' => now(),
        ])->save();

        $quiz->attempts()->delete();
        $quiz->questions()->delete();
        $quiz->classrooms()->sync([$classroom->id => ['assigned_at' => now(), 'available_from' => null, 'due_at' => now()->addWeeks(2)]]);

        foreach ($this->quizQuestions() as $position => $question) {
            $quiz->questions()->create([...$question, 'sort_order' => $position + 1]);
        }
    }

    /**
     * The demo quiz's questions: two multiple choice (one worth 2 points), two true/false and one identification.
     *
     * @return list<array{type: QuestionType, prompt: string, choices: list<string>|null, correct_answer: string, explanation: string, points: int}>
     */
    private function quizQuestions(): array
    {
        return [
            [
                'type' => QuestionType::MultipleChoice,
                'prompt' => 'Which food group helps build and repair the body?',
                'choices' => ['Go foods like rice and bread', 'Grow foods like fish, eggs and beans', 'Glow foods like fruits and vegetables', 'Sweets and junk food'],
                'correct_answer' => '1',
                'explanation' => 'Grow foods are rich in protein, which builds and repairs muscles and tissues.',
                'points' => 2,
            ],
            [
                'type' => QuestionType::MultipleChoice,
                'prompt' => 'How many glasses of water should a Grade 5 or 6 learner drink each day?',
                'choices' => ['1 to 2 glasses', '3 to 4 glasses', '6 to 8 glasses', '15 or more glasses'],
                'correct_answer' => '2',
                'explanation' => 'Six to eight glasses a day keeps the body hydrated.',
                'points' => 1,
            ],
            [
                'type' => QuestionType::TrueFalse,
                'prompt' => 'Fruits and vegetables are Glow foods because they help keep the body healthy.',
                'choices' => ['True', 'False'],
                'correct_answer' => '0',
                'explanation' => 'Fruits and vegetables give vitamins and minerals that protect the body.',
                'points' => 1,
            ],
            [
                'type' => QuestionType::TrueFalse,
                'prompt' => 'Skipping breakfast helps you concentrate better in class.',
                'choices' => ['True', 'False'],
                'correct_answer' => '1',
                'explanation' => 'Breakfast gives the brain energy for the morning; skipping it makes focusing harder.',
                'points' => 1,
            ],
            [
                'type' => QuestionType::Identification,
                'prompt' => 'Which vitamin do calamansi and oranges have plenty of?',
                'choices' => null,
                'correct_answer' => 'Vitamin C|C|ascorbic acid',
                'explanation' => 'Citrus fruits are rich in vitamin C, which helps the body fight infections.',
                'points' => 1,
            ],
        ];
    }
}
