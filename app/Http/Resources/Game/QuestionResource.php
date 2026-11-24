<?php

namespace App\Http\Resources\Game;

use App\Models\QuizQuestion;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A quiz question as the game shows it, without its correct answer.
 *
 * For multiple choice and true/false questions the game answers with the index of the chosen choice ("0" for the
 * first); for identification questions ("choices" is null) it answers with the typed text.
 *
 * @mixin QuizQuestion
 */
class QuestionResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type->value,
            'prompt' => $this->prompt,
            'choices' => $this->type->usesChoices() ? array_values($this->choices ?? []) : null,
            'points' => $this->points,
            'image_url' => $this->imageUrl(),
            'video_url' => $this->videoFileUrl() ?? $this->videoLinkUrl(),
        ];
    }
}
