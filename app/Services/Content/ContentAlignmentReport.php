<?php

namespace App\Services\Content;

use App\Enums\GradeLevel;
use App\Models\LearningModule;
use App\Models\LessonPlan;
use App\Models\Subject;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Compares the competencies of the teachers' lesson plans with the game content that covers them.
 *
 * Lesson plans with a competency code are grouped by (subject, grade, code). Matching content: learning modules
 * with the same subject, grade and code; their published activities (counted for published modules only, the
 * ones learners can play); the missions in those modules; and the quizzes aligned to the group's lesson plans.
 */
class ContentAlignmentReport
{
    /**
     * The SQL expression lesson plan codes are grouped by (matches CompetencyCode::key()).
     */
    private const string PLAN_CODE_KEY = 'UPPER(TRIM(lesson_plans.competency_code))';

    /**
     * Get every planned competency with its matching content, ordered by subject name, grade and code.
     *
     * @return Collection<int, CompetencyCoverage>
     */
    public function competencies(?int $subjectId = null, ?GradeLevel $grade = null): Collection
    {
        $groups = $this->planGroups($subjectId, $grade);

        if ($groups->isEmpty()) {
            return collect();
        }

        $quizCounts = $this->alignedQuizCounts($subjectId, $grade);
        $modules = $this->modulesByCompetency($subjectId, $grade);
        $subjects = Subject::query()->whereIn('id', $groups->pluck('subject_id')->unique()->all())->get()->keyBy('id');

        return $groups
            ->map(function (object $group) use ($quizCounts, $modules, $subjects): CompetencyCoverage {
                $key = $this->groupKey((int) $group->subject_id, (int) $group->grade_level, (string) $group->code_key);

                return $this->coverage(
                    subject: $subjects->get((int) $group->subject_id),
                    grade: GradeLevel::from((int) $group->grade_level),
                    code: (string) $group->code_key,
                    learningCompetency: $group->learning_competency,
                    quarter: $group->quarter === null ? null : (int) $group->quarter,
                    plansCount: (int) $group->plans_count,
                    teachersCount: (int) $group->teachers_count,
                    modules: $modules->get($key, collect()),
                    quizzesCount: (int) $quizCounts->get($key, 0),
                );
            })
            ->sortBy([
                fn (CompetencyCoverage $a, CompetencyCoverage $b): int => strcasecmp($a->subject->name, $b->subject->name),
                fn (CompetencyCoverage $a, CompetencyCoverage $b): int => $a->grade->value <=> $b->grade->value,
                fn (CompetencyCoverage $a, CompetencyCoverage $b): int => strcmp($a->code, $b->code),
            ])
            ->values();
    }

    /**
     * Count the lesson plans without a competency code ("unmapped"), which cannot be matched to content.
     */
    public function unmappedPlansCount(?int $subjectId = null, ?GradeLevel $grade = null): int
    {
        return LessonPlan::query()
            ->where(function (Builder $query): void {
                $query->whereNull('competency_code')->orWhereRaw('TRIM(competency_code) = ?', ['']);
            })
            ->when($subjectId !== null, fn (Builder $query) => $query->where('subject_id', $subjectId))
            ->when($grade !== null, fn (Builder $query) => $query->where('grade_level', $grade->value))
            ->count();
    }

    /**
     * Get the coverage of one lesson plan's competency (null when the plan has no code).
     *
     * The plan counts as the whole group: matching modules by code and the quizzes aligned to this plan.
     */
    public function forLessonPlan(LessonPlan $lessonPlan): ?CompetencyCoverage
    {
        $code = CompetencyCode::key($lessonPlan->competency_code);

        if ($code === null) {
            return null;
        }

        $lessonPlan->loadMissing(['subject', 'academicTerm']);

        $modules = $this->moduleQuery((int) $lessonPlan->subject_id, $lessonPlan->grade_level)
            ->whereRaw('UPPER(TRIM(competency_code)) = ?', [$code])
            ->get();

        return $this->coverage(
            subject: $lessonPlan->subject,
            grade: $lessonPlan->grade_level,
            code: $code,
            learningCompetency: $lessonPlan->learning_competency,
            quarter: $lessonPlan->academicTerm?->sequence,
            plansCount: 1,
            teachersCount: 1,
            modules: $modules,
            quizzesCount: $lessonPlan->quizzes()->count(),
        );
    }

    /**
     * Build the coverage of one competency from its matching content.
     *
     * @param  Collection<int, LearningModule>  $modules
     */
    private function coverage(
        Subject $subject,
        GradeLevel $grade,
        string $code,
        ?string $learningCompetency,
        ?int $quarter,
        int $plansCount,
        int $teachersCount,
        Collection $modules,
        int $quizzesCount,
    ): CompetencyCoverage {
        $publishedActivities = (int) $modules
            ->filter(fn (LearningModule $module): bool => $module->is_published)
            ->sum('published_activities_count');

        return new CompetencyCoverage(
            subject: $subject,
            grade: $grade,
            code: $code,
            learningCompetency: $learningCompetency,
            quarter: $quarter,
            plansCount: $plansCount,
            teachersCount: $teachersCount,
            modules: $modules->values(),
            publishedActivitiesCount: $publishedActivities,
            missionsCount: (int) $modules->sum('missions_count'),
            quizzesCount: $quizzesCount,
            status: CoverageStatus::fromCounts($modules->count(), $publishedActivities, $quizzesCount),
        );
    }

    /**
     * Group the coded lesson plans by subject, grade and code.
     *
     * @return Collection<int, object{subject_id: int, grade_level: int, code_key: string, plans_count: int, teachers_count: int, learning_competency: ?string, quarter: ?int}>
     */
    private function planGroups(?int $subjectId, ?GradeLevel $grade): Collection
    {
        return $this->codedPlans(DB::table('lesson_plans'), $subjectId, $grade)
            ->leftJoin('academic_terms', 'academic_terms.id', '=', 'lesson_plans.academic_term_id')
            ->select(['lesson_plans.subject_id', 'lesson_plans.grade_level'])
            ->selectRaw(self::PLAN_CODE_KEY.' as code_key')
            ->selectRaw('COUNT(*) as plans_count')
            ->selectRaw('COUNT(DISTINCT lesson_plans.teacher_id) as teachers_count')
            ->selectRaw('MAX(lesson_plans.learning_competency) as learning_competency')
            ->selectRaw('MIN(academic_terms.sequence) as quarter')
            ->groupBy('lesson_plans.subject_id', 'lesson_plans.grade_level', DB::raw(self::PLAN_CODE_KEY))
            ->get();
    }

    /**
     * Count the distinct quizzes aligned to each group's lesson plans, keyed by group.
     *
     * @return Collection<string, int>
     */
    private function alignedQuizCounts(?int $subjectId, ?GradeLevel $grade): Collection
    {
        return $this->codedPlans(DB::table('lesson_plan_quiz'), $subjectId, $grade)
            ->join('lesson_plans', 'lesson_plans.id', '=', 'lesson_plan_quiz.lesson_plan_id')
            ->select(['lesson_plans.subject_id', 'lesson_plans.grade_level'])
            ->selectRaw(self::PLAN_CODE_KEY.' as code_key')
            ->selectRaw('COUNT(DISTINCT lesson_plan_quiz.quiz_id) as quizzes_count')
            ->groupBy('lesson_plans.subject_id', 'lesson_plans.grade_level', DB::raw(self::PLAN_CODE_KEY))
            ->get()
            ->mapWithKeys(fn (object $row): array => [
                $this->groupKey((int) $row->subject_id, (int) $row->grade_level, (string) $row->code_key) => (int) $row->quizzes_count,
            ]);
    }

    /**
     * Get the coded modules grouped by subject, grade and code.
     *
     * @return Collection<string, Collection<int, LearningModule>>
     */
    private function modulesByCompetency(?int $subjectId, ?GradeLevel $grade): Collection
    {
        return $this->moduleQuery($subjectId, $grade)
            ->whereNotNull('competency_code')
            ->get()
            ->filter(fn (LearningModule $module): bool => CompetencyCode::key($module->competency_code) !== null)
            ->groupBy(fn (LearningModule $module): string => $this->groupKey(
                (int) $module->subject_id,
                $module->grade_level->value,
                (string) CompetencyCode::key($module->competency_code),
            ));
    }

    /**
     * Start a query of modules with the counts the coverage needs, in display order.
     *
     * @return Builder<LearningModule>
     */
    private function moduleQuery(?int $subjectId, ?GradeLevel $grade): Builder
    {
        return LearningModule::query()
            ->withCount([
                'activities as published_activities_count' => fn (Builder $query) => $query->where('is_published', true),
                'missions',
            ])
            ->when($subjectId !== null, fn (Builder $query) => $query->where('subject_id', $subjectId))
            ->when($grade !== null, fn (Builder $query) => $query->where('grade_level', $grade->value))
            ->orderBy('sort_order')
            ->orderBy('title')
            ->orderBy('id');
    }

    /**
     * Limit a query that reads lesson_plans to coded plans of the filters.
     */
    private function codedPlans(QueryBuilder $query, ?int $subjectId, ?GradeLevel $grade): QueryBuilder
    {
        return $query
            ->whereNotNull('lesson_plans.competency_code')
            ->whereRaw('TRIM(lesson_plans.competency_code) <> ?', [''])
            ->when($subjectId !== null, fn (QueryBuilder $query) => $query->where('lesson_plans.subject_id', $subjectId))
            ->when($grade !== null, fn (QueryBuilder $query) => $query->where('lesson_plans.grade_level', $grade->value));
    }

    /**
     * Build the key a competency group is matched by.
     */
    private function groupKey(int $subjectId, int $grade, string $code): string
    {
        return $subjectId.'|'.$grade.'|'.$code;
    }
}
