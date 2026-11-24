<?php

use App\Enums\ProgressStatus;
use App\Models\Mission;
use App\Models\MissionProgress;
use App\Models\Reward;
use App\Models\User;
use Illuminate\Support\Facades\Date;
use Laravel\Sanctum\Sanctum;

/**
 * A save file as the Unity game serializes its StudentSave class.
 *
 * @param  list<array<string, mixed>>  $missions
 * @param  list<string>  $collectedRewards
 * @return array<string, mixed>
 */
function gameSave(User $student, array $missions = [], array $collectedRewards = []): array
{
    return [
        'studentId' => (string) $student->id,
        'grade' => 5,
        'totalPoints' => 999999,
        'collectedRewards' => $collectedRewards,
        'missions' => $missions,
        'quizHistory' => [['quizId' => 'ENG', 'score' => 8, 'totalQuestions' => 10, 'percentage' => 80, 'passed' => true, 'timestamp' => '2026-11-23T02:00:00Z']],
        'totalQuizzesTaken' => 1,
        'totalQuizzesPassed' => 1,
        'highestQuizScore' => 8,
        'lastSavedAt' => '2026-11-24T01:59:00Z',
        'needsSync' => true,
    ];
}

/**
 * One mission of a save file: the correct counts of its completed checkpoints, in order.
 *
 * @param  list<int>  $correctCounts
 * @return array<string, mixed>
 */
function gameSaveMission(string $missionId, array $correctCounts, bool $finalChallengeComplete = false): array
{
    return [
        'missionId' => $missionId,
        'finalChallengeComplete' => $finalChallengeComplete,
        'checkpoints' => array_map(fn (int $correctCount, int $index): array => [
            'checkpointId' => $missionId.'-CP'.($index + 1),
            'completed' => true,
            'correctCount' => $correctCount,
            'pointsEarned' => 5000,
            'rewardName' => '',
            'responses' => [['questionId' => 'Q1', 'selectedIndex' => 0, 'correct' => true]],
        ], $correctCounts, array_keys($correctCounts)),
        'writtenResponses' => [['stepId' => 'S1', 'text' => 'I eat vegetables.']],
    ];
}

it('stores mission progress, rewards and the recalculated points from the save', function () {
    Date::setTestNow('2026-11-24 10:00:00');
    $student = User::factory()->student()->create();
    $student->studentProfile->forceFill(['total_points' => 0, 'last_synced_at' => null])->save();
    $finished = Mission::factory()->create(['game_key' => 'G5-ENG-M1', 'points_reward' => 100]);
    $started = Mission::factory()->create(['game_key' => 'G5-ENG-M2']);
    Reward::factory()->create(['name' => 'Garden Clue Card']);
    Reward::factory()->inactive()->create(['name' => 'Retired Badge']);
    Sanctum::actingAs($student);

    $this->postJson(route('game.sync'), gameSave($student, [
        gameSaveMission('G5-ENG-M1', [5, 4, 3], finalChallengeComplete: true),
        gameSaveMission('G5-ENG-M2', [5]),
        gameSaveMission('G5-SCI-M1', [5, 5]),
    ], ['Garden Clue Card', 'Retired Badge', 'Mystery Medal']))
        ->assertOk()
        ->assertJsonPath('data.total_points', 270)
        ->assertJsonPath('data.rewards.0.name', 'Garden Clue Card')
        ->assertJsonPath('data.last_synced_at', '2026-11-24T10:00:00+08:00')
        ->assertJsonPath('ignored', [
            'missions' => ['G5-SCI-M1'],
            'rewards' => ['Retired Badge', 'Mystery Medal'],
        ]);

    expect(MissionProgress::query()->where('mission_id', $finished->id)->sole())
        ->student_id->toBe($student->id)
        ->status->toBe(ProgressStatus::Completed)
        ->progress_percent->toBe(100)
        ->points_earned->toBe(220)
        ->completed_at->toDateTimeString()->toBe('2026-11-24 10:00:00')
        ->synced_at->toDateTimeString()->toBe('2026-11-24 10:00:00')
        ->and(MissionProgress::query()->where('mission_id', $started->id)->sole())
        ->status->toBe(ProgressStatus::InProgress)
        ->progress_percent->toBe(25)
        ->points_earned->toBe(50)
        ->completed_at->toBeNull()
        ->and($student->studentProfile->refresh())
        ->total_points->toBe(270)
        ->last_synced_at->toDateTimeString()->toBe('2026-11-24 10:00:00');
});

it('counts the same save only once when the game uploads it again', function () {
    $student = User::factory()->student()->create();
    Mission::factory()->create(['game_key' => 'G5-ENG-M1', 'points_reward' => 100]);
    Reward::factory()->create(['name' => 'Garden Clue Card']);
    $save = gameSave($student, [gameSaveMission('G5-ENG-M1', [5, 5, 5], finalChallengeComplete: true)], ['Garden Clue Card']);
    Sanctum::actingAs($student);

    $this->postJson(route('game.sync'), $save)->assertOk();
    $this->postJson(route('game.sync'), $save)->assertOk()->assertJsonPath('data.total_points', 250);

    expect(MissionProgress::query()->count())->toBe(1)
        ->and($student->rewards()->count())->toBe(1);
});

it('never lowers progress already recorded', function () {
    $student = User::factory()->student()->create();
    $mission = Mission::factory()->create(['game_key' => 'G5-ENG-M1']);
    $progress = MissionProgress::factory()->for($mission)->for($student, 'student')->create([
        'status' => ProgressStatus::Completed,
        'progress_percent' => 100,
        'points_earned' => 220,
        'completed_at' => '2026-11-20 09:00:00',
    ]);
    Sanctum::actingAs($student);

    $this->postJson(route('game.sync'), gameSave($student, [gameSaveMission('G5-ENG-M1', [2])]))
        ->assertOk()
        ->assertJsonPath('data.total_points', 220);

    expect($progress->refresh())
        ->status->toBe(ProgressStatus::Completed)
        ->progress_percent->toBe(100)
        ->points_earned->toBe(220)
        ->completed_at->toDateTimeString()->toBe('2026-11-20 09:00:00');
});

it('refuses a save that belongs to another learner', function () {
    $student = User::factory()->student()->create();
    Mission::factory()->create(['game_key' => 'G5-ENG-M1']);
    Sanctum::actingAs($student);

    $this->postJson(route('game.sync'), gameSave(User::factory()->student()->create(), [gameSaveMission('G5-ENG-M1', [5])]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['studentId' => 'This save belongs to another learner. Sign in as that learner to upload it.']);

    expect(MissionProgress::query()->count())->toBe(0);
});

it('rejects a save the game could not have produced', function (array $mission, string $field, string $message) {
    $student = User::factory()->student()->create();
    Sanctum::actingAs($student);

    $this->postJson(route('game.sync'), gameSave($student, [$mission]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors([$field => $message]);

    expect(MissionProgress::query()->count())->toBe(0);
})->with([
    'more correct answers than questions' => [gameSaveMission('G5-ENG-M1', [6]), 'missions.0.checkpoints.0.correctCount', 'The correct answer count field must be between 0 and 5.'],
    'more checkpoints than a mission has' => [gameSaveMission('G5-ENG-M1', [5, 5, 5, 5]), 'missions.0.checkpoints', 'The checkpoints field must not have more than 3 items.'],
    'no mission ID' => [['finalChallengeComplete' => false, 'checkpoints' => []], 'missions.0.missionId', 'The mission ID field is required.'],
]);

it('requires the save fields', function () {
    Sanctum::actingAs(User::factory()->student()->create());

    $this->postJson(route('game.sync'), [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors([
            'studentId' => 'The student ID field is required.',
            'missions' => 'The missions field must be present.',
            'collectedRewards' => 'The collected rewards field must be present.',
        ]);
});
