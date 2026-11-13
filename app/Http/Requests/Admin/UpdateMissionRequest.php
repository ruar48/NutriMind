<?php

namespace App\Http\Requests\Admin;

use App\Models\Mission;
use Illuminate\Database\Query\Builder;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;

/**
 * Validates an edited mission: the same rules as a new one, except that the mission may keep its current
 * subject after that subject was deactivated.
 */
class UpdateMissionRequest extends StoreMissionRequest
{
    /**
     * Get the rule the subject must pass: an active subject or the mission's current one.
     */
    protected function subjectRule(): Exists
    {
        /** @var Mission $mission */
        $mission = $this->route('mission');

        return Rule::exists('subjects', 'id')->where(function (Builder $query) use ($mission): void {
            $query->where('is_active', true)->orWhere('id', $mission->subject_id);
        });
    }
}
