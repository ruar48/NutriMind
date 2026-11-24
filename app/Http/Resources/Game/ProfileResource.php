<?php

namespace App\Http\Resources\Game;

use App\Models\Reward;
use App\Models\User;
use App\Services\LevelCalculator;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The signed-in learner as the game shows them: name, grade, points, level and rewards.
 *
 * Points and level are the server's (see App\Services\Game\StudentPoints), so the game should show these numbers
 * instead of the ones in its local save file.
 *
 * @mixin User
 */
class ProfileResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $this->resource->loadMissing(['studentProfile', 'rewards']);

        $levels = app(LevelCalculator::class);
        $profile = $this->studentProfile;
        $totalPoints = (int) ($profile?->total_points ?? 0);
        $levelProgress = $levels->progress($totalPoints);

        return [
            'id' => $this->id,
            'name' => $this->name,
            'username' => $this->username,
            'grade' => $profile?->grade_level?->value,
            'total_points' => $totalPoints,
            'level' => $levelProgress['level'],
            'max_level' => $levels->maxLevel(),
            'level_progress' => [
                'points_into_level' => $levelProgress['into'],
                'points_per_level' => $levelProgress['needed'],
                'percent' => $levelProgress['percent'],
            ],
            'rewards' => $this->rewards->map(fn (Reward $reward): array => [
                'name' => $reward->name,
                'description' => $reward->description,
                'awarded_at' => $reward->pivot->awarded_at?->toIso8601String(),
            ])->values()->all(),
            'last_synced_at' => $profile?->last_synced_at?->toIso8601String(),
        ];
    }
}
