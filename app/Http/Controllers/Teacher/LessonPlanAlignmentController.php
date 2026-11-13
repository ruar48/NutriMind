<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Http\Requests\Teacher\UpdateLessonPlanAlignmentRequest;
use App\Models\LessonPlan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Attributes\Controllers\Authorize;
use Illuminate\Support\Facades\DB;

/**
 * Teacher › Lesson plan › Alignment: the activities, quizzes and missions the plan uses.
 */
class LessonPlanAlignmentController extends Controller
{
    /**
     * Replace the plan's aligned activities, quizzes and missions with the submitted selection.
     */
    #[Authorize('update', 'lesson_plan')]
    public function update(UpdateLessonPlanAlignmentRequest $request, LessonPlan $lessonPlan): RedirectResponse
    {
        $activityIds = array_map('intval', $request->validated('activities') ?? []);
        $quizIds = array_map('intval', $request->validated('quizzes') ?? []);
        $missionIds = array_map('intval', $request->validated('missions') ?? []);

        DB::transaction(function () use ($lessonPlan, $activityIds, $quizIds, $missionIds): void {
            $lessonPlan->activities()->sync($activityIds);
            $lessonPlan->quizzes()->sync($quizIds);
            $lessonPlan->missions()->sync($missionIds);
        });

        $summary = collect([
            trans_choice('{0} no activities|{1} :count activity|[2,*] :count activities', count($activityIds)),
            trans_choice('{0} no quizzes|{1} :count quiz|[2,*] :count quizzes', count($quizIds)),
            trans_choice('{0} no missions|{1} :count mission|[2,*] :count missions', count($missionIds)),
        ])->join(', ', ' and ');

        return redirect()->route('teacher.lesson-plans.show', $lessonPlan)->with('status', "Alignment saved: {$summary}.");
    }
}
