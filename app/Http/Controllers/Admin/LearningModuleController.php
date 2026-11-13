<?php

namespace App\Http\Controllers\Admin;

use App\Enums\GradeLevel;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreLearningModuleRequest;
use App\Http\Requests\Admin\UpdateLearningModuleRequest;
use App\Models\ActivityProgress;
use App\Models\LearningModule;
use App\Models\LessonPlan;
use App\Models\Subject;
use App\Services\Content\CompetencyCode;
use App\Services\Content\ListFilter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;

/**
 * Administrator › Learning modules: the game worlds' lessons, each with its activities.
 *
 * Admin-only: the route group applies role:admin (administrators manage content without a policy).
 */
class LearningModuleController extends Controller
{
    /**
     * Modules per page.
     */
    private const int PER_PAGE = 20;

    /**
     * Shown when a module cannot be deleted because learners played its activities.
     */
    public const string HAS_PROGRESS_MESSAGE = 'Learners have already played activities in this module. Unpublish it instead.';

    /**
     * The query-string keys that prefill the "new module" form (from the lesson alignment page).
     *
     * @var list<string>
     */
    private const array PREFILL_KEYS = ['subject_id', 'grade_level', 'quarter', 'title', 'world_name', 'competency_code', 'learning_competency'];

    /**
     * List the modules with filters (subject, grade, quarter, published state, search).
     */
    public function index(Request $request): View
    {
        $filters = [
            'subject' => ListFilter::id($request, 'subject'),
            'grade' => ListFilter::enum($request, 'grade', GradeLevel::class),
            'quarter' => ListFilter::choice($request, 'quarter', ['1', '2', '3', '4', 'none']),
            'status' => ListFilter::choice($request, 'status', ['published', 'draft']),
            'search' => ListFilter::search($request),
        ];

        $modules = LearningModule::query()
            ->with('subject')
            ->withCount(['activities', 'missions'])
            ->withExists(['activities as has_progress' => fn (Builder $query) => $query->whereHas('progress')])
            ->addSelect(['lesson_plans_count' => $this->alignedLessonPlans()->selectRaw('COUNT(*)')])
            ->when($filters['subject'], fn (Builder $query, int $subjectId) => $query->where('subject_id', $subjectId))
            ->when($filters['grade'], fn (Builder $query, GradeLevel $grade) => $query->where('grade_level', $grade))
            ->when($filters['quarter'] === 'none', fn (Builder $query) => $query->whereNull('quarter'))
            ->when(
                $filters['quarter'] !== null && $filters['quarter'] !== 'none',
                fn (Builder $query) => $query->where('quarter', (int) $filters['quarter']),
            )
            ->when($filters['status'], fn (Builder $query, string $status) => $query->where('is_published', $status === 'published'))
            ->when($filters['search'], function (Builder $query, string $search): void {
                $query->where(function (Builder $query) use ($search): void {
                    $query->whereLike('title', "%{$search}%")
                        ->orWhereLike('world_name', "%{$search}%")
                        ->orWhereLike('competency_code', "%{$search}%");
                });
            })
            ->orderBy('grade_level')
            ->orderBy('quarter')
            ->orderBy('sort_order')
            ->orderBy('title')
            ->orderBy('id')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        return view('admin.modules.index', [
            'modules' => $modules,
            'filters' => $filters,
            'subjectOptions' => Subject::query()->orderBy('name')->orderBy('id')->pluck('name', 'id'),
            'totalModules' => LearningModule::query()->count(),
        ]);
    }

    /**
     * Show the form for a new module, optionally prefilled from the query string (lesson alignment page).
     */
    public function create(Request $request): View
    {
        $prefill = $this->prefill($request);

        return view('admin.modules.form', [
            'module' => new LearningModule([...['sort_order' => 0, 'is_published' => true], ...$prefill]),
            'subjectOptions' => $this->subjectOptions(),
            'isPrefilled' => $prefill !== [],
        ]);
    }

    /**
     * Store a new module.
     */
    public function store(StoreLearningModuleRequest $request): RedirectResponse
    {
        $module = LearningModule::query()->create($request->validated());

        return redirect()->route('admin.modules.show', $module)
            ->with('status', "Module “{$module->title}” created. Add its activities next.");
    }

    /**
     * Show a module with its activities, missions and how lesson plans use it.
     */
    public function show(LearningModule $module): View
    {
        $module->load('subject');

        $activities = $module->activities()
            ->withCount(['progress', 'lessonPlans'])
            ->get();

        $missions = $module->missions()
            ->withCount(['progress', 'progress as completed_count' => fn (Builder $query) => $query->completed()])
            ->orderBy('sort_order')
            ->orderBy('title')
            ->orderBy('id')
            ->get();

        $alignedPlans = LessonPlan::query()
            ->where(fn (Builder $query) => $this->constrainToModule($query, (int) $module->getKey()));

        $codeKey = CompetencyCode::key($module->competency_code);

        return view('admin.modules.show', [
            'module' => $module,
            'activities' => $activities,
            'missions' => $missions,
            'lessonPlans' => (clone $alignedPlans)
                ->with(['teacher', 'subject'])
                ->latest('updated_at')
                ->orderByDesc('id')
                ->limit(6)
                ->get(),
            'usage' => [
                'lessonPlans' => (clone $alignedPlans)->count(),
                'followingModule' => LessonPlan::query()->where('learning_module_id', $module->getKey())->count(),
                'sameCompetency' => $codeKey === null ? 0 : LessonPlan::query()
                    ->where('subject_id', $module->subject_id)
                    ->where('grade_level', $module->grade_level)
                    ->whereRaw('UPPER(TRIM(competency_code)) = ?', [$codeKey])
                    ->count(),
                'quizzes' => $module->quizzes()->count(),
            ],
        ]);
    }

    /**
     * Show the form for editing a module.
     */
    public function edit(LearningModule $module): View
    {
        $module->load('subject');

        return view('admin.modules.form', [
            'module' => $module,
            'subjectOptions' => $this->subjectOptions($module),
            'isPrefilled' => false,
        ]);
    }

    /**
     * Update a module.
     */
    public function update(UpdateLearningModuleRequest $request, LearningModule $module): RedirectResponse
    {
        $module->update($request->validated());

        return redirect()->route('admin.modules.show', $module)->with('status', 'Module updated.');
    }

    /**
     * Delete a module and its activities, unless learners already played one of them.
     */
    public function destroy(LearningModule $module): RedirectResponse
    {
        $hasProgress = ActivityProgress::query()
            ->whereHas('activity', fn (Builder $query) => $query->where('learning_module_id', $module->getKey()))
            ->exists();

        if ($hasProgress) {
            return redirect()->route('admin.modules.show', $module)->with('error', self::HAS_PROGRESS_MESSAGE);
        }

        $module->delete();

        return redirect()->route('admin.modules.index')->with('status', "Module “{$module->title}” deleted.");
    }

    /**
     * Get the valid prefill values from the query string; invalid or unknown values are dropped.
     *
     * @return array<string, mixed>
     */
    private function prefill(Request $request): array
    {
        $input = collect($request->query())->only(self::PREFILL_KEYS)->all();

        if (array_key_exists('competency_code', $input)) {
            $input['competency_code'] = CompetencyCode::normalize($input['competency_code']);
        }

        $validator = Validator::make($input, [
            'subject_id' => ['integer', 'exists:subjects,id,is_active,1'],
            'grade_level' => ['integer', 'in:'.implode(',', array_column(GradeLevel::cases(), 'value'))],
            'quarter' => ['integer', 'between:1,4'],
            'title' => ['string', 'max:150'],
            'world_name' => ['string', 'max:100'],
            'competency_code' => ['string', 'max:'.CompetencyCode::MAX_LENGTH],
            'learning_competency' => ['string', 'max:1000'],
        ]);

        return collect($validator->valid())
            ->only(self::PREFILL_KEYS)
            ->filter(fn (mixed $value): bool => is_string($value) && $value !== '')
            ->map(fn (string $value, string $key): int|string => in_array($key, ['subject_id', 'grade_level', 'quarter'], true) ? (int) $value : $value)
            ->all();
    }

    /**
     * Get the subjects a module may use: active subjects, plus the module's current subject.
     *
     * @return Collection<int, string>
     */
    private function subjectOptions(?LearningModule $module = null): Collection
    {
        return Subject::query()
            ->where(function (Builder $query) use ($module): void {
                $query->where('is_active', true)->when($module, fn (Builder $query) => $query->orWhere('id', $module->subject_id));
            })
            ->orderBy('name')
            ->orderBy('id')
            ->get()
            ->mapWithKeys(fn (Subject $subject): array => [
                $subject->getKey() => $subject->is_active ? $subject->name : $subject->name.' (inactive)',
            ]);
    }

    /**
     * Start a subquery of the lesson plans aligned to the outer query's module: plans that follow the module or
     * align at least one of its activities.
     *
     * @return Builder<LessonPlan>
     */
    private function alignedLessonPlans(): Builder
    {
        return LessonPlan::query()->where(function (Builder $query): void {
            $query->whereColumn('lesson_plans.learning_module_id', 'learning_modules.id')
                ->orWhereHas('activities', fn (Builder $query) => $query->whereColumn('learning_activities.learning_module_id', 'learning_modules.id'));
        });
    }

    /**
     * Limit a lesson plan query to the plans aligned to the module (following it or using its activities).
     *
     * @param  Builder<LessonPlan>  $query
     */
    private function constrainToModule(Builder $query, int $moduleId): void
    {
        $query->where('learning_module_id', $moduleId)
            ->orWhereHas('activities', fn (Builder $query) => $query->where('learning_activities.learning_module_id', $moduleId));
    }
}
