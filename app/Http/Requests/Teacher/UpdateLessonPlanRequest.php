<?php

namespace App\Http\Requests\Teacher;

use App\Models\LessonPlan;
use App\Models\SchoolYear;
use Illuminate\Database\Query\Builder;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;

/**
 * Validates an edited lesson plan: the same rules as a new one, except that the plan may keep its current
 * subject (deactivated since), quarter (of an earlier school year) and module (unpublished since).
 */
class UpdateLessonPlanRequest extends StoreLessonPlanRequest
{
    /**
     * Get the subjects the plan may use: the teacher's active assigned subjects and the plan's current one.
     *
     * @return list<int>
     */
    protected function allowedSubjectIds(): array
    {
        return array_values(array_unique([...parent::allowedSubjectIds(), (int) $this->lessonPlan()->subject_id]));
    }

    /**
     * Get the rule the quarter must pass: a term of the active school year or the plan's current term.
     */
    protected function termRule(): Exists
    {
        $activeYearId = SchoolYear::current()?->getKey();
        $currentTermId = $this->lessonPlan()->academic_term_id;

        return Rule::exists('academic_terms', 'id')->where(function (Builder $query) use ($activeYearId, $currentTermId): void {
            $query->where('school_year_id', $activeYearId)->orWhere('id', $currentTermId);
        });
    }

    /**
     * Get the rule the module must pass: a module of the chosen subject and grade that is published or is the
     * plan's current module.
     */
    protected function moduleRule(): Exists
    {
        $currentModuleId = $this->lessonPlan()->learning_module_id;

        return Rule::exists('learning_modules', 'id')->where(function (Builder $query) use ($currentModuleId): void {
            $query->where('subject_id', $this->integer('subject_id'))
                ->where('grade_level', $this->integer('grade_level'))
                ->where(function (Builder $query) use ($currentModuleId): void {
                    $query->where('is_published', true)->orWhere('id', $currentModuleId);
                });
        });
    }

    /**
     * Get the lesson plan being edited.
     */
    private function lessonPlan(): LessonPlan
    {
        /** @var LessonPlan */
        return $this->route('lesson_plan');
    }
}
