<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Difficulty;
use App\Enums\MissionType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreMissionRequest;
use App\Http\Requests\Admin\UpdateMissionRequest;
use App\Models\LearningModule;
use App\Models\Mission;
use App\Models\Subject;
use App\Services\Content\ListFilter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * Administrator › Missions: story, daily, weekly and challenge missions of the game.
 *
 * Admin-only: the route group applies role:admin (administrators manage content without a policy).
 */
class MissionController extends Controller
{
    /**
     * Missions per page.
     */
    private const int PER_PAGE = 20;

    /**
     * Shown when a mission cannot be deleted because learners started it.
     */
    public const string HAS_PROGRESS_MESSAGE = 'Learners have already started this mission. Deactivate it instead.';

    /**
     * List the missions with filters (subject, type, difficulty, grade, active state, search).
     */
    public function index(Request $request): View
    {
        $filters = [
            'subject' => ListFilter::id($request, 'subject'),
            'type' => ListFilter::enum($request, 'type', MissionType::class),
            'difficulty' => ListFilter::enum($request, 'difficulty', Difficulty::class),
            'grade' => ListFilter::choice($request, 'grade', ['5', '6', 'both']),
            'status' => ListFilter::choice($request, 'status', ['active', 'inactive']),
            'search' => ListFilter::search($request),
        ];

        $missions = Mission::query()
            ->with(['subject', 'module'])
            ->withCount(['progress', 'progress as completed_count' => fn (Builder $query) => $query->completed()])
            ->when($filters['subject'], fn (Builder $query, int $subjectId) => $query->where('subject_id', $subjectId))
            ->when($filters['type'], fn (Builder $query, MissionType $type) => $query->where('type', $type))
            ->when($filters['difficulty'], fn (Builder $query, Difficulty $difficulty) => $query->where('difficulty', $difficulty))
            ->when($filters['grade'] === 'both', fn (Builder $query) => $query->whereNull('grade_level'))
            ->when(
                $filters['grade'] !== null && $filters['grade'] !== 'both',
                fn (Builder $query) => $query->where('grade_level', (int) $filters['grade']),
            )
            ->when($filters['status'], fn (Builder $query, string $status) => $query->where('is_active', $status === 'active'))
            ->when($filters['search'], function (Builder $query, string $search): void {
                $query->where(function (Builder $query) use ($search): void {
                    $query->whereLike('title', "%{$search}%")->orWhereLike('objective', "%{$search}%");
                });
            })
            ->orderByDesc('is_active')
            ->orderBy('sort_order')
            ->orderBy('title')
            ->orderBy('id')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        return view('admin.missions.index', [
            'missions' => $missions,
            'filters' => $filters,
            'subjectOptions' => Subject::query()->orderBy('name')->orderBy('id')->pluck('name', 'id'),
        ]);
    }

    /**
     * Show the form for a new mission; `?learning_module_id=` starts it inside that module.
     */
    public function create(Request $request): View
    {
        $mission = new Mission([
            'difficulty' => Difficulty::Easy,
            'points_reward' => 50,
            'sort_order' => 0,
            'is_active' => true,
        ]);

        $moduleId = ListFilter::id($request, 'learning_module_id');
        $module = $moduleId === null ? null : LearningModule::query()->find($moduleId);

        if ($module !== null) {
            $mission->fill([
                'subject_id' => $module->subject_id,
                'learning_module_id' => $module->getKey(),
                'grade_level' => $module->grade_level,
            ]);
        }

        return view('admin.missions.form', [
            'mission' => $mission,
            'subjectOptions' => $this->subjectOptions(),
            'modules' => $this->moduleChoices(),
            'startingModule' => $module,
        ]);
    }

    /**
     * Store a new mission.
     */
    public function store(StoreMissionRequest $request): RedirectResponse
    {
        $mission = Mission::query()->create($request->validated());

        return redirect()->route('admin.missions.index')->with('status', "Mission “{$mission->title}” created.");
    }

    /**
     * Show the form for editing a mission.
     */
    public function edit(Mission $mission): View
    {
        return view('admin.missions.form', [
            'mission' => $mission,
            'subjectOptions' => $this->subjectOptions($mission),
            'modules' => $this->moduleChoices(),
            'startingModule' => null,
        ]);
    }

    /**
     * Update a mission.
     */
    public function update(UpdateMissionRequest $request, Mission $mission): RedirectResponse
    {
        $mission->update($request->validated());

        return redirect()->route('admin.missions.index')->with('status', 'Mission updated.');
    }

    /**
     * Delete a mission, unless learners already started it.
     */
    public function destroy(Mission $mission): RedirectResponse
    {
        if ($mission->progress()->exists()) {
            return redirect()->route('admin.missions.index')->with('error', self::HAS_PROGRESS_MESSAGE);
        }

        $mission->delete();

        return redirect()->route('admin.missions.index')->with('status', "Mission “{$mission->title}” deleted.");
    }

    /**
     * Get the subjects a mission may use: active subjects, plus the mission's current subject.
     *
     * @return Collection<int, string>
     */
    private function subjectOptions(?Mission $mission = null): Collection
    {
        return Subject::query()
            ->where(function (Builder $query) use ($mission): void {
                $query->where('is_active', true)->when($mission, fn (Builder $query) => $query->orWhere('id', $mission->subject_id));
            })
            ->orderBy('name')
            ->orderBy('id')
            ->get()
            ->mapWithKeys(fn (Subject $subject): array => [
                $subject->getKey() => $subject->is_active ? $subject->name : $subject->name.' (inactive)',
            ]);
    }

    /**
     * Get every module a mission can belong to, with its subject, in picker order.
     *
     * @return EloquentCollection<int, LearningModule>
     */
    private function moduleChoices(): EloquentCollection
    {
        return LearningModule::query()
            ->with('subject')
            ->orderBy('grade_level')
            ->orderBy('quarter')
            ->orderBy('sort_order')
            ->orderBy('title')
            ->orderBy('id')
            ->get();
    }
}
