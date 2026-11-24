<?php

namespace App\Services\Game;

use App\Enums\ProgressStatus;
use App\Models\Mission;
use App\Models\MissionProgress;
use App\Models\Reward;
use App\Models\User;
use Illuminate\Container\Attributes\Scoped;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Stores the progress the Unity game uploads: the learner's whole save file (StudentSave), sent as-is.
 *
 * - Missions are found by their game key ("G5-ENG-M1"); a mission no administrator gave that key is ignored.
 * - A mission's points are recomputed from its completed checkpoints (correct answers × points per correct
 *   answer) plus the mission's reward points once its final challenge is complete. Progress is 25% per completed
 *   checkpoint and 100% once the final challenge is done.
 * - A sync never takes progress away: points and percentage only grow, and a completed mission stays completed,
 *   so an older save uploaded from another device cannot undo newer progress.
 * - Rewards are matched to active rewards by name and are only ever added.
 * - Written answers and the game's own practice quizzes are not stored; quizzes reach the server as attempts.
 */
#[Scoped]
class ProgressSync
{
    public function __construct(private StudentPoints $points) {}

    /**
     * Store the save's progress and return what the server could not match.
     *
     * @param  array{missions?: list<array{missionId: string, finalChallengeComplete?: bool, checkpoints?: list<array{checkpointId: string, completed?: bool, correctCount?: int}>}>, collectedRewards?: list<string>}  $save
     * @return array{missions: list<string>, rewards: list<string>} the unmatched game mission IDs and reward names
     */
    public function sync(User $student, array $save): array
    {
        return DB::transaction(function () use ($student, $save): array {
            $ignoredMissions = $this->syncMissions($student, $save['missions'] ?? []);
            $ignoredRewards = $this->syncRewards($student, $save['collectedRewards'] ?? []);

            $student->studentProfile()->update(['last_synced_at' => now(), 'last_active_at' => now()]);
            $this->points->recalculate($student);

            return ['missions' => $ignoredMissions, 'rewards' => $ignoredRewards];
        });
    }

    /**
     * Create or advance the learner's mission progress records.
     *
     * @param  list<array{missionId: string, finalChallengeComplete?: bool, checkpoints?: list<array{checkpointId: string, completed?: bool, correctCount?: int}>}>  $entries
     * @return list<string>
     */
    private function syncMissions(User $student, array $entries): array
    {
        $missions = Mission::query()
            ->whereIn('game_key', array_column($entries, 'missionId'))
            ->get()
            ->keyBy('game_key');

        $existingProgress = $student->missionProgress()
            ->whereIn('mission_id', $missions->modelKeys())
            ->get()
            ->keyBy('mission_id');

        $ignored = [];

        foreach ($entries as $entry) {
            $mission = $missions->get($entry['missionId']);

            if ($mission === null) {
                $ignored[] = $entry['missionId'];

                continue;
            }

            $progress = $existingProgress->get($mission->id) ?? new MissionProgress(['mission_id' => $mission->id]);
            $this->advance($progress, $mission, $entry);
            $student->missionProgress()->save($progress);
        }

        return $ignored;
    }

    /**
     * Apply one mission entry of the save to the progress record without lowering anything already recorded.
     *
     * @param  array{missionId: string, finalChallengeComplete?: bool, checkpoints?: list<array{checkpointId: string, completed?: bool, correctCount?: int}>}  $entry
     */
    private function advance(MissionProgress $progress, Mission $mission, array $entry): void
    {
        $checkpointsPerMission = (int) config('nutrimind.game.checkpoints_per_mission');
        $questionsPerCheckpoint = (int) config('nutrimind.game.questions_per_checkpoint');
        $pointsPerCorrectAnswer = (int) config('nutrimind.game.points_per_correct_answer');

        /** @var Collection<int, array{checkpointId: string, completed?: bool, correctCount?: int}> $completedCheckpoints */
        $completedCheckpoints = collect($entry['checkpoints'] ?? [])
            ->filter(fn (array $checkpoint): bool => (bool) ($checkpoint['completed'] ?? false))
            ->unique('checkpointId')
            ->take($checkpointsPerMission);

        $isFinished = (bool) ($entry['finalChallengeComplete'] ?? false);

        $points = (int) $completedCheckpoints->sum(
            fn (array $checkpoint): int => min($questionsPerCheckpoint, max(0, (int) ($checkpoint['correctCount'] ?? 0))) * $pointsPerCorrectAnswer,
        ) + ($isFinished ? $mission->points_reward : 0);

        $percent = $isFinished ? 100 : intdiv($completedCheckpoints->count() * 100, $checkpointsPerMission + 1);

        $wasCompleted = $progress->status === ProgressStatus::Completed;

        $progress->fill([
            'status' => $isFinished || $wasCompleted ? ProgressStatus::Completed : ProgressStatus::InProgress,
            'progress_percent' => max((int) $progress->progress_percent, $percent),
            'points_earned' => max((int) $progress->points_earned, $points),
            'started_at' => $progress->started_at ?? now(),
            'completed_at' => $progress->completed_at ?? ($isFinished ? now() : null),
            'synced_at' => now(),
        ]);
    }

    /**
     * Give the learner the active rewards named in the save that they do not have yet.
     *
     * @param  list<string>  $names
     * @return list<string>
     */
    private function syncRewards(User $student, array $names): array
    {
        $names = array_values(array_unique($names));

        $rewards = Reward::query()->active()->whereIn('name', $names)->get();
        $ownedRewardIds = $student->rewards()->pluck('rewards.id')->all();

        $student->rewards()->attach(
            $rewards->reject(fn (Reward $reward): bool => in_array($reward->id, $ownedRewardIds, true))
                ->mapWithKeys(fn (Reward $reward): array => [$reward->id => ['awarded_at' => now()]])
                ->all(),
        );

        return array_values(array_diff($names, $rewards->pluck('name')->all()));
    }
}
