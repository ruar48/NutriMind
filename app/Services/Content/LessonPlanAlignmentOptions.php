<?php

namespace App\Services\Content;

use App\Models\LearningActivity;
use App\Models\LearningModule;
use App\Models\LessonPlan;
use App\Models\Mission;
use App\Models\Quiz;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;

/**
 * The game content a teacher may align to a lesson plan.
 *
 * Activities: published activities of published modules with the plan's subject and grade. Quizzes: the plan
 * teacher's own quizzes with the plan's subject and grade (any status). Missions: active missions of the plan's
 * subject for its grade or for both grades. Content already aligned stays available while it still matches the
 * plan's subject and grade (e.g. after it was unpublished), so saving the editor never drops it silently.
 */
class LessonPlanAlignmentOptions
{
    /**
     * Get the activities that may be aligned, grouped by module: suggested modules first (the plan's own module
     * or a module with the plan's competency code), then by quarter, display order and title.
     *
     * @return Collection<int, array{module: LearningModule, activities: Collection<int, LearningActivity>, suggested: bool}>
     */
    public function activityGroups(LessonPlan $lessonPlan): Collection
    {
        return $this->activities($lessonPlan)
            ->groupBy('learning_module_id')
            ->map(fn (EloquentCollection $activities): array => [
                'module' => $activities->first()->module,
                'activities' => $activities->sortBy([['sort_order', 'asc'], ['id', 'asc']])->values(),
                'suggested' => $this->isSuggestedModule($lessonPlan, $activities->first()->module),
            ])
            ->sortBy([
                fn (array $a, array $b): int => $b['suggested'] <=> $a['suggested'],
                fn (array $a, array $b): int => ($a['module']->quarter ?? 5) <=> ($b['module']->quarter ?? 5),
                fn (array $a, array $b): int => $a['module']->sort_order <=> $b['module']->sort_order,
                fn (array $a, array $b): int => strcasecmp($a['module']->title, $b['module']->title),
                fn (array $a, array $b): int => $a['module']->id <=> $b['module']->id,
            ])
            ->values();
    }

    /**
     * Get the activities that may be aligned, with their module.
     *
     * @return EloquentCollection<int, LearningActivity>
     */
    public function activities(LessonPlan $lessonPlan): EloquentCollection
    {
        $alignedIds = $lessonPlan->activities()->pluck('learning_activities.id')->all();

        return LearningActivity::query()
            ->with('module')
            ->whereHas('module', fn (Builder $query) => $this->constrainModuleToPlan($query, $lessonPlan))
            ->where(function (Builder $query) use ($alignedIds): void {
                $query
                    ->where(function (Builder $query): void {
                        $query->where('is_published', true)
                            ->whereHas('module', fn (Builder $query) => $query->where('is_published', true));
                    })
                    ->orWhereIn('id', $alignedIds);
            })
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
    }

    /**
     * Get the teacher's own quizzes that may be aligned, newest first, with their question counts.
     *
     * @return EloquentCollection<int, Quiz>
     */
    public function quizzes(LessonPlan $lessonPlan): EloquentCollection
    {
        return Quiz::query()
            ->where('teacher_id', $lessonPlan->teacher_id)
            ->where('subject_id', $lessonPlan->subject_id)
            ->where('grade_level', $lessonPlan->grade_level)
            ->withCount('questions')
            ->latest()
            ->orderByDesc('id')
            ->get();
    }

    /**
     * Get the missions that may be aligned, with their module, in display order.
     *
     * @return EloquentCollection<int, Mission>
     */
    public function missions(LessonPlan $lessonPlan): EloquentCollection
    {
        $alignedIds = $lessonPlan->missions()->pluck('missions.id')->all();

        return Mission::query()
            ->with('module')
            ->where('subject_id', $lessonPlan->subject_id)
            ->forGrade($lessonPlan->grade_level)
            ->where(function (Builder $query) use ($alignedIds): void {
                $query->where('is_active', true)->orWhereIn('id', $alignedIds);
            })
            ->orderBy('sort_order')
            ->orderBy('title')
            ->orderBy('id')
            ->get();
    }

    /**
     * Determine whether a module is suggested for the lesson plan: it is the plan's own module, or it teaches
     * the plan's competency code.
     */
    public function isSuggestedModule(LessonPlan $lessonPlan, ?LearningModule $module): bool
    {
        if ($module === null) {
            return false;
        }

        return ($lessonPlan->learning_module_id !== null && (int) $lessonPlan->learning_module_id === (int) $module->getKey())
            || CompetencyCode::matches($lessonPlan->competency_code, $module->competency_code);
    }

    /**
     * Remove the alignments that no longer match the plan's subject and grade (after the plan was edited).
     *
     * @return int the number of alignments removed
     */
    public function prune(LessonPlan $lessonPlan): int
    {
        $staleActivityIds = $lessonPlan->activities()
            ->whereDoesntHave('module', fn (Builder $query) => $this->constrainModuleToPlan($query, $lessonPlan))
            ->pluck('learning_activities.id');

        $staleQuizIds = $lessonPlan->quizzes()
            ->where(function (Builder $query) use ($lessonPlan): void {
                $query->where('quizzes.teacher_id', '<>', $lessonPlan->teacher_id)
                    ->orWhere('quizzes.subject_id', '<>', $lessonPlan->subject_id)
                    ->orWhere('quizzes.grade_level', '<>', $lessonPlan->grade_level);
            })
            ->pluck('quizzes.id');

        $staleMissionIds = $lessonPlan->missions()
            ->where(function (Builder $query) use ($lessonPlan): void {
                $query->where('missions.subject_id', '<>', $lessonPlan->subject_id)
                    ->orWhere(function (Builder $query) use ($lessonPlan): void {
                        $query->whereNotNull('missions.grade_level')->where('missions.grade_level', '<>', $lessonPlan->grade_level);
                    });
            })
            ->pluck('missions.id');

        $removed = 0;

        if ($staleActivityIds->isNotEmpty()) {
            $removed += $lessonPlan->activities()->detach($staleActivityIds->all());
        }

        if ($staleQuizIds->isNotEmpty()) {
            $removed += $lessonPlan->quizzes()->detach($staleQuizIds->all());
        }

        if ($staleMissionIds->isNotEmpty()) {
            $removed += $lessonPlan->missions()->detach($staleMissionIds->all());
        }

        return $removed;
    }

    /**
     * Limit a module query to the lesson plan's subject and grade.
     *
     * @param  Builder<LearningModule>  $query
     */
    private function constrainModuleToPlan(Builder $query, LessonPlan $lessonPlan): void
    {
        $query->where('learning_modules.subject_id', $lessonPlan->subject_id)
            ->where('learning_modules.grade_level', $lessonPlan->grade_level);
    }
}
