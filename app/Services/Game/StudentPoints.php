<?php

namespace App\Services\Game;

use App\Models\QuizAttempt;
use App\Models\User;
use App\Services\LevelCalculator;
use Illuminate\Container\Attributes\Scoped;

/**
 * Recalculates a learner's total points (XP) and level from what the server has recorded.
 *
 * Total = the points of every mission progress record + the best submitted score of every quiz. The total is
 * rebuilt from the records instead of being added to, so a repeated game sync or a quiz retake never counts the
 * same points twice, and the number the game keeps on the device is never trusted.
 */
#[Scoped]
class StudentPoints
{
    public function __construct(private LevelCalculator $levels) {}

    /**
     * Store the learner's recalculated total points and level on their profile; a learner without one is skipped.
     */
    public function recalculate(User $student): void
    {
        $profile = $student->studentProfile()->first();

        if ($profile === null) {
            return;
        }

        $missionPoints = (int) $student->missionProgress()->sum('points_earned');

        $quizPoints = (int) QuizAttempt::query()
            ->where('student_id', $student->getKey())
            ->submitted()
            ->selectRaw('max(score) as best_score')
            ->groupBy('quiz_id')
            ->pluck('best_score')
            ->sum();

        $totalPoints = $missionPoints + $quizPoints;

        $profile->forceFill([
            'total_points' => $totalPoints,
            'level' => $this->levels->levelFor($totalPoints),
        ])->save();

        $student->setRelation('studentProfile', $profile);
    }
}
