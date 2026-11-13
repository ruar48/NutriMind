<?php

namespace App\Services;

use Illuminate\Container\Attributes\Scoped;

/**
 * Turns a learner's total points (XP) into a game level with the administrator's gamification settings.
 *
 * Rule: level = min(max_level, intdiv(points, points_per_level) + 1). Every level spans points_per_level points,
 * so level 1 covers 0 … points_per_level − 1, level 2 starts at points_per_level, and so on up to max_level.
 * Settings are read on every call, so a change saved through App\Services\Settings applies immediately.
 */
#[Scoped]
class LevelCalculator
{
    public function __construct(private Settings $settings) {}

    /**
     * Get the number of points each level spans (at least 1).
     */
    public function pointsPerLevel(): int
    {
        return max(1, (int) $this->settings->get('points_per_level'));
    }

    /**
     * Get the highest level a learner can reach (at least 1).
     */
    public function maxLevel(): int
    {
        return max(1, (int) $this->settings->get('max_level'));
    }

    /**
     * Get the level for the total points; negative totals count as zero.
     */
    public function levelFor(int $points): int
    {
        return min($this->maxLevel(), intdiv(max(0, $points), $this->pointsPerLevel()) + 1);
    }

    /**
     * Get the level for the total points and the progress inside it.
     *
     * "into" is the number of points earned inside the current level, "needed" the number of points the level
     * spans (the XP bar's maximum) and "percent" into / needed × 100 rounded to 1 decimal. At the maximum level
     * there is no next level: into and needed are 0 and percent is 100.0.
     *
     * @return array{level: int, into: int, needed: int, percent: float}
     */
    public function progress(int $points): array
    {
        $level = $this->levelFor($points);

        if ($level >= $this->maxLevel()) {
            return ['level' => $level, 'into' => 0, 'needed' => 0, 'percent' => 100.0];
        }

        $needed = $this->pointsPerLevel();
        $into = max(0, $points) - ($level - 1) * $needed;

        return [
            'level' => $level,
            'into' => $into,
            'needed' => $needed,
            'percent' => round($into / $needed * 100, 1),
        ];
    }
}
