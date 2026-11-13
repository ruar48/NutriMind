<?php

namespace App\Http\Controllers\Teacher;

use App\Enums\LessonPlanStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Teacher\StoreLessonPlanRequest;
use App\Http\Requests\Teacher\UpdateLessonPlanRequest;
use App\Models\AcademicTerm;
use App\Models\LearningModule;
use App\Models\LessonPlan;
use App\Models\Mission;
use App\Models\SchoolYear;
use App\Models\User;
use App\Services\Content\ContentAlignmentReport;
use App\Services\Content\LessonPlanAlignmentOptions;
use App\Services\Content\ListFilter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Attributes\Controllers\Authorize;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Teacher › Lesson plans, each with the game content aligned to it.
 */
class LessonPlanController extends Controller
{
    /**
     * Lesson plans per page.
     */
    private const int PER_PAGE = 15;

    /**
     * List the teacher's lesson plans (in subjects still assigned to them) with filters and alignment counts.
     */
    #[Authorize('viewAny', LessonPlan::class)]
    public function index(Request $request): View
    {
        /** @var User $teacher */
        $teacher = $request->user();
        $subjectIds = $teacher->assignedSubjectIds();

        $subjectFilter = ListFilter::id($request, 'subject');
        $filters = [
            'subject' => in_array($subjectFilter, $subjectIds, true) ? $subjectFilter : null,
            'term' => ListFilter::id($request, 'term'),
            'status' => ListFilter::enum($request, 'status', LessonPlanStatus::class),
            'alignment' => ListFilter::choice($request, 'alignment', ['aligned', 'unaligned']),
            'search' => ListFilter::search($request),
        ];

        $lessonPlans = $this->ownPlans($teacher)
            ->with(['subject', 'academicTerm'])
            ->withCount(['activities', 'quizzes', 'missions'])
            ->when($filters['subject'], fn (Builder $query, int $subjectId) => $query->where('subject_id', $subjectId))
            ->when($filters['term'], fn (Builder $query, int $termId) => $query->where('academic_term_id', $termId))
            ->when($filters['status'], fn (Builder $query, LessonPlanStatus $status) => $query->where('status', $status))
            ->when($filters['alignment'] === 'aligned', fn (Builder $query) => $query->where(fn (Builder $query) => $this->whereAligned($query)))
            ->when($filters['alignment'] === 'unaligned', fn (Builder $query) => $query->whereNot(fn (Builder $query) => $this->whereAligned($query)))
            ->when($filters['search'], function (Builder $query, string $search): void {
                $query->where(function (Builder $query) use ($search): void {
                    $query->whereLike('title', "%{$search}%")->orWhereLike('competency_code', "%{$search}%");
                });
            })
            ->latest('starts_on')
            ->orderByDesc('id')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        return view('teacher.lesson-plans.index', [
            'lessonPlans' => $lessonPlans,
            'filters' => $filters,
            'summary' => $this->summary($teacher),
            'subjectOptions' => $teacher->assignedSubjects->sortBy('name')->mapWithKeys(fn ($subject): array => [$subject->getKey() => $subject->name]),
            'termOptions' => $this->termFilterOptions(),
        ]);
    }

    /**
     * Show the form for a new lesson plan, starting in the current quarter.
     */
    #[Authorize('create', LessonPlan::class)]
    public function create(Request $request): View
    {
        /** @var User $teacher */
        $teacher = $request->user();

        return view('teacher.lesson-plans.form', [
            'lessonPlan' => new LessonPlan([
                'status' => LessonPlanStatus::Planned,
                'academic_term_id' => AcademicTerm::current()?->getKey(),
            ]),
            ...$this->formOptions($teacher),
        ]);
    }

    /**
     * Store a new lesson plan for the teacher.
     */
    #[Authorize('create', LessonPlan::class)]
    public function store(StoreLessonPlanRequest $request): RedirectResponse
    {
        /** @var User $teacher */
        $teacher = $request->user();
        $lessonPlan = $teacher->lessonPlans()->create($request->validated());

        return redirect()->route('teacher.lesson-plans.show', $lessonPlan)
            ->with('status', 'Lesson plan created. Align game activities, quizzes and missions to it below.');
    }

    /**
     * Show a lesson plan with its alignment editor and coverage indicators.
     */
    #[Authorize('view', 'lesson_plan')]
    public function show(LessonPlan $lessonPlan, LessonPlanAlignmentOptions $options, ContentAlignmentReport $report): View
    {
        $lessonPlan->load(['subject', 'academicTerm.schoolYear', 'module']);
        $missions = $options->missions($lessonPlan);

        return view('teacher.lesson-plans.show', [
            'lessonPlan' => $lessonPlan,
            'activityGroups' => $options->activityGroups($lessonPlan),
            'quizzes' => $options->quizzes($lessonPlan),
            'missions' => $missions,
            'suggestedMissionIds' => $missions
                ->filter(fn (Mission $mission): bool => $options->isSuggestedModule($lessonPlan, $mission->module))
                ->modelKeys(),
            'aligned' => [
                'activities' => $lessonPlan->activities()->pluck('learning_activities.id')->map(fn (mixed $id): int => (int) $id)->all(),
                'quizzes' => $lessonPlan->quizzes()->pluck('quizzes.id')->map(fn (mixed $id): int => (int) $id)->all(),
                'missions' => $lessonPlan->missions()->pluck('missions.id')->map(fn (mixed $id): int => (int) $id)->all(),
            ],
            'coverage' => $report->forLessonPlan($lessonPlan),
        ]);
    }

    /**
     * Show the form for editing a lesson plan.
     */
    #[Authorize('update', 'lesson_plan')]
    public function edit(Request $request, LessonPlan $lessonPlan): View
    {
        /** @var User $teacher */
        $teacher = $request->user();

        return view('teacher.lesson-plans.form', [
            'lessonPlan' => $lessonPlan,
            ...$this->formOptions($teacher, $lessonPlan),
        ]);
    }

    /**
     * Update a lesson plan; alignments that no longer match its subject or grade are removed.
     */
    #[Authorize('update', 'lesson_plan')]
    public function update(UpdateLessonPlanRequest $request, LessonPlan $lessonPlan, LessonPlanAlignmentOptions $options): RedirectResponse
    {
        $removed = DB::transaction(function () use ($request, $lessonPlan, $options): int {
            $lessonPlan->update($request->validated());

            return $options->prune($lessonPlan);
        });

        $status = $removed === 0
            ? 'Lesson plan updated.'
            : 'Lesson plan updated. '.trans_choice(
                '{1} :count aligned item no longer matched its subject or grade and was removed.|[2,*] :count aligned items no longer matched its subject or grade and were removed.',
                $removed,
            );

        return redirect()->route('teacher.lesson-plans.show', $lessonPlan)->with('status', $status);
    }

    /**
     * Delete a lesson plan (its alignments go with it).
     */
    #[Authorize('delete', 'lesson_plan')]
    public function destroy(LessonPlan $lessonPlan): RedirectResponse
    {
        $lessonPlan->delete();

        return redirect()->route('teacher.lesson-plans.index')->with('status', "Lesson plan “{$lessonPlan->title}” deleted.");
    }

    /**
     * Start a query of the teacher's plans in subjects still assigned to them.
     *
     * @return Builder<LessonPlan>
     */
    private function ownPlans(User $teacher): Builder
    {
        return LessonPlan::query()
            ->where('teacher_id', $teacher->getKey())
            ->whereIn('subject_id', $teacher->assignedSubjectIds());
    }

    /**
     * Limit a lesson plan query to plans with at least one aligned activity, quiz or mission.
     *
     * @param  Builder<LessonPlan>  $query
     */
    private function whereAligned(Builder $query): void
    {
        $query->whereHas('activities')->orWhereHas('quizzes')->orWhereHas('missions');
    }

    /**
     * Count the teacher's plans: all, aligned to content, and ongoing.
     *
     * @return array{total: int, aligned: int, unaligned: int, ongoing: int}
     */
    private function summary(User $teacher): array
    {
        $counts = $this->ownPlans($teacher)
            ->toBase()
            ->selectRaw('COUNT(*) as total')
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as ongoing', [LessonPlanStatus::Ongoing->value])
            ->selectRaw(
                'SUM(CASE WHEN EXISTS (SELECT 1 FROM learning_activity_lesson_plan WHERE learning_activity_lesson_plan.lesson_plan_id = lesson_plans.id)'
                .' OR EXISTS (SELECT 1 FROM lesson_plan_quiz WHERE lesson_plan_quiz.lesson_plan_id = lesson_plans.id)'
                .' OR EXISTS (SELECT 1 FROM lesson_plan_mission WHERE lesson_plan_mission.lesson_plan_id = lesson_plans.id)'
                .' THEN 1 ELSE 0 END) as aligned',
            )
            ->first();

        $total = (int) $counts->total;
        $aligned = (int) $counts->aligned;

        return [
            'total' => $total,
            'aligned' => $aligned,
            'unaligned' => $total - $aligned,
            'ongoing' => (int) $counts->ongoing,
        ];
    }

    /**
     * Get the quarters to filter by, grouped by school year (newest year first).
     *
     * @return array<string, array<int, string>>
     */
    private function termFilterOptions(): array
    {
        return AcademicTerm::query()
            ->with('schoolYear')
            ->get()
            ->sortBy([
                fn (AcademicTerm $a, AcademicTerm $b): int => $b->schoolYear->starts_on <=> $a->schoolYear->starts_on,
                fn (AcademicTerm $a, AcademicTerm $b): int => $a->sequence <=> $b->sequence,
            ])
            ->groupBy(fn (AcademicTerm $term): string => 'SY '.$term->schoolYear->name)
            ->map(fn (Collection $terms): array => $terms->mapWithKeys(fn (AcademicTerm $term): array => [$term->getKey() => $term->name])->all())
            ->all();
    }

    /**
     * Get the options of the lesson plan form: the teacher's active subjects, the quarters of the active school
     * year and the published modules of those subjects (each list also keeps the plan's current value).
     *
     * @return array{subjectOptions: Collection<int, string>, termOptions: Collection<int, string>, modules: EloquentCollection<int, LearningModule>, activeSchoolYear: ?SchoolYear}
     */
    private function formOptions(User $teacher, ?LessonPlan $lessonPlan = null): array
    {
        $subjects = $teacher->assignedSubjects()
            ->where(function (Builder $query) use ($lessonPlan): void {
                $query->where('subjects.is_active', true)
                    ->when($lessonPlan, fn (Builder $query) => $query->orWhere('subjects.id', $lessonPlan->subject_id));
            })
            ->orderBy('subjects.name')
            ->orderBy('subjects.id')
            ->get();

        $activeSchoolYear = SchoolYear::current();

        $terms = AcademicTerm::query()
            ->with('schoolYear')
            ->where(function (Builder $query) use ($activeSchoolYear, $lessonPlan): void {
                $query->where('school_year_id', $activeSchoolYear?->getKey())
                    ->when($lessonPlan?->academic_term_id, fn (Builder $query, int $termId) => $query->orWhere('id', $termId));
            })
            ->orderBy('starts_on')
            ->orderBy('sequence')
            ->orderBy('id')
            ->get();

        $modules = LearningModule::query()
            ->with('subject')
            ->whereIn('subject_id', $subjects->modelKeys())
            ->where(function (Builder $query) use ($lessonPlan): void {
                $query->where('is_published', true)
                    ->when($lessonPlan?->learning_module_id, fn (Builder $query, int $moduleId) => $query->orWhere('id', $moduleId));
            })
            ->orderBy('grade_level')
            ->orderBy('quarter')
            ->orderBy('sort_order')
            ->orderBy('title')
            ->orderBy('id')
            ->get();

        return [
            'subjectOptions' => $subjects->mapWithKeys(fn ($subject): array => [
                $subject->getKey() => $subject->is_active ? $subject->name : $subject->name.' (inactive)',
            ]),
            'termOptions' => $terms->mapWithKeys(fn (AcademicTerm $term): array => [
                $term->getKey() => $term->school_year_id === $activeSchoolYear?->getKey()
                    ? $term->name
                    : $term->name.' (SY '.$term->schoolYear->name.')',
            ]),
            'modules' => $modules,
            'activeSchoolYear' => $activeSchoolYear,
        ];
    }
}
