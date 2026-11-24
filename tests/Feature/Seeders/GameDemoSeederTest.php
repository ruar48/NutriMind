<?php

use App\Enums\GradeLevel;
use App\Enums\QuizStatus;
use App\Enums\RewardType;
use App\Models\Classroom;
use App\Models\Mission;
use App\Models\MissionProgress;
use App\Models\Quiz;
use App\Models\Reward;
use App\Models\SchoolYear;
use App\Models\Subject;
use App\Models\User;
use Database\Seeders\GameDemoSeeder;

/**
 * Create an active Grade 5 and Grade 6 classroom in the active school year for the demo learners to join.
 *
 * @return array{5: Classroom, 6: Classroom}
 */
function gameDemoClassrooms(): array
{
    $year = SchoolYear::factory()->active()->create();

    return [
        5 => Classroom::factory()->for($year)->create(['grade_level' => GradeLevel::Five]),
        6 => Classroom::factory()->for($year)->create(['grade_level' => GradeLevel::Six]),
    ];
}

it('maps the 18 game missions and their 72 rewards', function () {
    Subject::factory()->create(['code' => 'SCIENCE']);
    gameDemoClassrooms();

    $this->seed(GameDemoSeeder::class);

    expect(Mission::query()->whereNotNull('game_key')->count())->toBe(18)
        ->and(Mission::query()->where('game_key', 'G5-ENG-M1')->sole())
        ->title->toBe('The Golden Lantern Adventure')
        ->grade_level->toBe(GradeLevel::Five)
        ->points_reward->toBe(100)
        ->and(Mission::query()->where('game_key', 'G6-SCI-M3')->sole()->subject->code)->toBe('SCIENCE')
        ->and(Subject::query()->where('code', 'ENGLISH')->exists())->toBeTrue()
        ->and(Reward::query()->count())->toBe(72)
        ->and(Reward::query()->where('name', 'Writer’s Seal')->sole()->type)->toBe(RewardType::Badge)
        ->and(Reward::query()->where('name', 'Golden Lantern Badge')->sole()->type)->toBe(RewardType::Trophy);
});

it('creates demo learners who can sign in to the game and see their teacher\'s quiz', function () {
    $classrooms = gameDemoClassrooms();

    $this->seed(GameDemoSeeder::class);

    $login = $this->postJson(route('game.login'), ['username' => '9999-0000-0005', 'password' => 'password'])
        ->assertOk()
        ->assertJsonPath('data.username', 'gamedemo5')
        ->assertJsonPath('data.grade', 5);

    $quiz = Quiz::query()->where('title', sprintf(GameDemoSeeder::QUIZ_TITLE, 5))->sole();
    expect($quiz)
        ->status->toBe(QuizStatus::Published)
        ->teacher_id->toBe($classrooms[5]->teacher_id)
        ->and($quiz->questions()->count())->toBe(5);

    $this->app['auth']->forgetGuards();

    $this->withToken($login->json('token'))
        ->getJson(route('game.quizzes.index'))
        ->assertOk()
        ->assertJsonPath('data.0.id', $quiz->id)
        ->assertJsonPath('data.0.question_count', 5);
});

it('can run again without duplicates and resets the demo learners\' progress', function () {
    gameDemoClassrooms();
    $this->seed(GameDemoSeeder::class);

    $student = User::query()->where('username', 'gamedemo6')->sole();
    MissionProgress::factory()->for($student, 'student')->create();
    $student->rewards()->attach(Reward::query()->first(), ['awarded_at' => now()]);
    $student->studentProfile->forceFill(['total_points' => 900])->save();

    $this->seed(GameDemoSeeder::class);

    expect(Mission::query()->whereNotNull('game_key')->count())->toBe(18)
        ->and(Reward::query()->count())->toBe(72)
        ->and(User::query()->where('username', 'gamedemo6')->count())->toBe(1)
        ->and(Quiz::query()->where('title', sprintf(GameDemoSeeder::QUIZ_TITLE, 6))->sole()->questions()->count())->toBe(5)
        ->and($student->missionProgress()->count())->toBe(0)
        ->and($student->rewards()->count())->toBe(0)
        ->and($student->studentProfile->refresh()->total_points)->toBe(0);
});

it('still seeds the missions, rewards and learners when no classroom exists', function () {
    $this->seed(GameDemoSeeder::class);

    expect(Mission::query()->whereNotNull('game_key')->count())->toBe(18)
        ->and(User::query()->whereIn('username', array_keys(GameDemoSeeder::LEARNERS))->count())->toBe(2)
        ->and(Quiz::query()->count())->toBe(0);
});
