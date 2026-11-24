<?php

namespace App\Http\Resources\Game;

use App\Models\QuizAttempt;
use App\Models\QuizAttemptAnswer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A graded quiz attempt for the game's result screen.
 *
 * Every question's result is listed; the correct answer and explanation are included only when the teacher lets
 * learners see the correct answers ("show correct answers" on the quiz).
 *
 * @mixin QuizAttempt
 */
class QuizAttemptResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $this->resource->loadMissing(['quiz', 'answers.question']);
        $showsCorrectAnswers = $this->quiz->show_correct_answers;

        return [
            'id' => $this->id,
            'quiz_id' => $this->quiz_id,
            'attempt_number' => $this->attempt_number,
            'attempts_left' => $this->quiz->max_attempts === null ? null : max(0, $this->quiz->max_attempts - $this->attempt_number),
            'score' => $this->score,
            'max_score' => $this->max_score,
            'percentage' => (float) $this->percentage,
            'passed' => $this->passed,
            'submitted_at' => $this->submitted_at?->toIso8601String(),
            'duration_seconds' => $this->duration_seconds,
            'results' => $this->answers->map(fn (QuizAttemptAnswer $answer): array => [
                'question_id' => $answer->quiz_question_id,
                'answer' => $answer->answer,
                'is_correct' => $answer->is_correct,
                'points_awarded' => $answer->points_awarded,
                ...($showsCorrectAnswers ? [
                    'correct_choice' => $answer->question->type->usesChoices() ? (int) $answer->question->correct_answer : null,
                    'correct_answer_text' => $answer->question->correctAnswerText(),
                    'explanation' => $answer->question->explanation,
                ] : []),
            ])->values()->all(),
        ];
    }
}
