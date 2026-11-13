<?php

namespace App\Http\Requests\Admin;

use App\Models\Reward;
use App\Services\Content\RewardAppearance;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Unique;

/**
 * Validates an edited reward: the same rules as a new one, except that the reward keeps its own name and may
 * keep an icon chosen before the curated set changed.
 */
class UpdateRewardRequest extends StoreRewardRequest
{
    /**
     * Get the rule that keeps reward names unique, ignoring this reward.
     */
    protected function uniqueNameRule(): Unique
    {
        return Rule::unique('rewards', 'name')->ignore($this->reward());
    }

    /**
     * Get the icons the reward may use: the curated set plus its current icon.
     *
     * @return list<string>
     */
    protected function allowedIcons(): array
    {
        return array_values(array_unique([...RewardAppearance::ICONS, $this->reward()->icon]));
    }

    /**
     * Get the reward being edited.
     */
    private function reward(): Reward
    {
        /** @var Reward */
        return $this->route('reward');
    }
}
