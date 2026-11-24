<?php

namespace App\Http\Resources\Game;

use App\Models\Classroom;
use App\Models\Quiz;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A quiz the learner may take in the game. The questions are included only when they were loaded (one quiz);
 * the correct answers never are.
 *
 * Expects the "attempts_used" and "questions_count" counts and the learner's open classrooms ("classrooms").
 *
 * @mixin Quiz
 */
class QuizResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $attemptsUsed = (int) ($this->attempts_used ?? 0);

        return [
            'id' => $this->id,
            'title' => $this->title,
            'instructions' => $this->instructions,
            'subject' => [
                'name' => $this->subject->name,
                'code' => $this->subject->code,
            ],
            'grade' => $this->grade_level->value,
            'time_limit_minutes' => $this->time_limit_minutes,
            'passing_score' => $this->passing_score,
            'max_attempts' => $this->max_attempts,
            'attempts_used' => $attemptsUsed,
            'attempts_left' => $this->max_attempts === null ? null : max(0, $this->max_attempts - $attemptsUsed),
            'question_count' => (int) $this->questions_count,
            'due_at' => $this->whenLoaded('classrooms', fn (): ?string => $this->classrooms
                ->map(fn (Classroom $classroom) => $classroom->pivot->due_at)
                ->filter()
                ->min()
                ?->toIso8601String()),
            'show_correct_answers' => $this->show_correct_answers,
            'questions' => QuestionResource::collection($this->whenLoaded('questions')),
        ];
    }
}
