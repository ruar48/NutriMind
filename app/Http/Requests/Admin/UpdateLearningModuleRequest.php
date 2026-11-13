<?php

namespace App\Http\Requests\Admin;

use App\Models\LearningModule;
use Illuminate\Database\Query\Builder;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;

/**
 * Validates an edited learning module: the same rules as a new one, except that the module may keep its
 * current subject after that subject was deactivated.
 */
class UpdateLearningModuleRequest extends StoreLearningModuleRequest
{
    /**
     * Get the rule the subject must pass: an active subject or the module's current one.
     */
    protected function subjectRule(): Exists
    {
        /** @var LearningModule $module */
        $module = $this->route('module');

        return Rule::exists('subjects', 'id')->where(function (Builder $query) use ($module): void {
            $query->where('is_active', true)->orWhere('id', $module->subject_id);
        });
    }
}
