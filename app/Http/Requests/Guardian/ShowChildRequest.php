<?php

namespace App\Http\Requests\Guardian;

use App\Enums\FeedbackType;
use App\Enums\ProgressStatus;
use App\Enums\RewardType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * The tab and the filters of a child's progress page.
 *
 * Every value comes from the page's own tabs and filter forms, so a value that breaks its rule (a tampered or
 * outdated link) is ignored instead of reported: an unknown tab shows the overview and an invalid filter does
 * not filter.
 */
class ShowChildRequest extends FormRequest
{
    /**
     * The tabs of the child page, the first one being the default.
     */
    public const TABS = ['overview', 'quizzes', 'missions', 'feedback', 'rewards'];

    /**
     * The values of the quiz result filter.
     */
    public const RESULT_PASSED = 'passed';

    public const RESULT_NOT_PASSED = 'not_passed';

    /**
     * The mission statuses the missions tab can filter by.
     */
    public const MISSION_STATUSES = [ProgressStatus::InProgress, ProgressStatus::Completed];

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
        return [
            'tab' => ['nullable', 'string', Rule::in(self::TABS)],
            'subject' => ['nullable', 'integer', 'min:1'],
            'result' => ['nullable', 'string', Rule::in([self::RESULT_PASSED, self::RESULT_NOT_PASSED])],
            'status' => ['nullable', Rule::enum(ProgressStatus::class)->only(self::MISSION_STATUSES)],
            'type' => ['nullable', Rule::enum(FeedbackType::class)],
            'reward_type' => ['nullable', Rule::enum(RewardType::class)],
        ];
    }

    /**
     * Ignore invalid tab and filter values instead of redirecting back with errors (see the class description).
     */
    protected function failedValidation(Validator $validator): void {}

    /**
     * Get the tab to show.
     */
    public function tab(): string
    {
        return $this->validFilter('tab') ?? self::TABS[0];
    }

    /**
     * Get the ID of the subject to filter by, or null; the caller checks it against the subjects it offers.
     */
    public function subjectId(): ?int
    {
        $subjectId = $this->validFilter('subject');

        return $subjectId === null ? null : (int) $subjectId;
    }

    /**
     * Get whether to show passed (true) or not passed (false) quiz attempts, or null for both.
     */
    public function passed(): ?bool
    {
        return match ($this->validFilter('result')) {
            self::RESULT_PASSED => true,
            self::RESULT_NOT_PASSED => false,
            default => null,
        };
    }

    /**
     * Get the mission status to filter by, or null for every status.
     */
    public function missionStatus(): ?ProgressStatus
    {
        $status = $this->validFilter('status');

        return $status === null ? null : ProgressStatus::from($status);
    }

    /**
     * Get the feedback type to filter by, or null for every type.
     */
    public function feedbackType(): ?FeedbackType
    {
        $type = $this->validFilter('type');

        return $type === null ? null : FeedbackType::from($type);
    }

    /**
     * Get the reward type to filter by, or null for every type.
     */
    public function rewardType(): ?RewardType
    {
        $type = $this->validFilter('reward_type');

        return $type === null ? null : RewardType::from($type);
    }

    /**
     * Get a value that passed its rule, or null.
     */
    private function validFilter(string $key): mixed
    {
        $value = $this->validator->valid()[$key] ?? null;

        return filled($value) ? $value : null;
    }
}
