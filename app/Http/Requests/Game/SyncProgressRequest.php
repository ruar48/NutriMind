<?php

namespace App\Http\Requests\Game;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates the save file the Unity game uploads (its StudentSave class, serialized with JsonUtility).
 *
 * Field names stay in the game's camelCase so the game can send its save file unchanged. Only the fields the
 * server stores are validated; the rest (totalPoints, quizHistory, writtenResponses, …) are accepted and ignored.
 * studentId must be the signed-in learner's ID, so a save left on a shared device by another learner is refused.
 */
class SyncProgressRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $checkpointsPerMission = (int) config('nutrimind.game.checkpoints_per_mission');
        $questionsPerCheckpoint = (int) config('nutrimind.game.questions_per_checkpoint');

        return [
            'studentId' => ['required', 'string', Rule::in([(string) $this->user()->getKey()])],
            'missions' => ['present', 'array', 'max:200'],
            'missions.*' => ['array'],
            'missions.*.missionId' => ['required', 'string', 'max:40', 'distinct'],
            'missions.*.finalChallengeComplete' => ['required', 'boolean'],
            'missions.*.checkpoints' => ['present', 'array', 'max:'.$checkpointsPerMission],
            'missions.*.checkpoints.*.checkpointId' => ['required', 'string', 'max:60'],
            'missions.*.checkpoints.*.completed' => ['required', 'boolean'],
            'missions.*.checkpoints.*.correctCount' => ['required', 'integer', 'between:0,'.$questionsPerCheckpoint],
            'collectedRewards' => ['present', 'array', 'max:500'],
            'collectedRewards.*' => ['string', 'max:255'],
        ];
    }

    /**
     * Get the field names used in validation messages.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'studentId' => 'student ID',
            'missions.*.missionId' => 'mission ID',
            'missions.*.checkpoints' => 'checkpoints',
            'missions.*.checkpoints.*.correctCount' => 'correct answer count',
        ];
    }

    /**
     * Get the custom validation messages.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'studentId.in' => 'This save belongs to another learner. Sign in as that learner to upload it.',
            'missions.*.missionId.distinct' => 'Each mission can appear only once in the save.',
        ];
    }
}
