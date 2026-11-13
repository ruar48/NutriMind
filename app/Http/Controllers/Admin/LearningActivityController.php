<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreLearningActivityRequest;
use App\Http\Requests\Admin\UpdateLearningActivityRequest;
use App\Models\LearningActivity;
use App\Models\LearningModule;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Administrator › Learning modules › Activities (shallow resource under a module).
 *
 * Admin-only: the route group applies role:admin (administrators manage content without a policy).
 */
class LearningActivityController extends Controller
{
    /**
     * Shown when an activity cannot be deleted because learners played it.
     */
    public const string HAS_PROGRESS_MESSAGE = 'Learners have already played this activity. Unpublish it instead.';

    /**
     * Show the form for a new activity of the module, placed after its last activity.
     */
    public function create(LearningModule $module): View
    {
        $module->load('subject');
        $lastPosition = LearningActivity::query()->where('learning_module_id', $module->getKey())->max('sort_order');

        return view('admin.activities.form', [
            'module' => $module,
            'activity' => new LearningActivity([
                'points' => 10,
                'sort_order' => min(999, $lastPosition === null ? 1 : (int) $lastPosition + 1),
                'is_published' => true,
            ]),
        ]);
    }

    /**
     * Store a new activity in the module.
     */
    public function store(StoreLearningActivityRequest $request, LearningModule $module): RedirectResponse
    {
        $activity = $module->activities()->create($request->validated());

        return redirect()->route('admin.modules.show', $module)
            ->with('status', "Activity “{$activity->title}” added to {$module->title}.");
    }

    /**
     * Show the form for editing an activity.
     */
    public function edit(LearningActivity $activity): View
    {
        $activity->load('module.subject');

        return view('admin.activities.form', [
            'module' => $activity->module,
            'activity' => $activity,
        ]);
    }

    /**
     * Update an activity.
     */
    public function update(UpdateLearningActivityRequest $request, LearningActivity $activity): RedirectResponse
    {
        $activity->update($request->validated());

        return redirect()->route('admin.modules.show', $activity->learning_module_id)->with('status', 'Activity updated.');
    }

    /**
     * Delete an activity, unless learners already played it.
     */
    public function destroy(LearningActivity $activity): RedirectResponse
    {
        if ($activity->progress()->exists()) {
            return redirect()->route('admin.modules.show', $activity->learning_module_id)->with('error', self::HAS_PROGRESS_MESSAGE);
        }

        $activity->delete();

        return redirect()->route('admin.modules.show', $activity->learning_module_id)
            ->with('status', "Activity “{$activity->title}” deleted.");
    }
}
