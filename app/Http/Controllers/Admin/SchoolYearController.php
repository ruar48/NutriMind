<?php

namespace App\Http\Controllers\Admin;

use App\Enums\GradeLevel;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreSchoolYearRequest;
use App\Http\Requests\Admin\UpdateSchoolYearRequest;
use App\Models\AcademicTerm;
use App\Models\SchoolYear;
use App\Services\Setup\AcademicCalendar;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Attributes\Controllers\Middleware;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * School year configuration: the list, the create and edit forms, the detail page with its quarters, and deletion.
 *
 * School years are an administrator-only resource without a policy; the class-level role middleware repeats the
 * route group's guard so the controller stays protected wherever it is routed.
 */
#[Middleware('role:admin')]
class SchoolYearController extends Controller
{
    /**
     * The timeline filters of the list, keyed by query value.
     *
     * @var array<string, string>
     */
    public const STATUS_FILTERS = [
        'active' => 'Active year',
        'upcoming' => 'Upcoming',
        'ended' => 'Ended',
    ];

    /**
     * List the school years, newest first, with the active year's calendar on top.
     */
    public function index(Request $request): View
    {
        $search = $this->textFilter($request, 'search');
        $status = array_key_exists($this->textFilter($request, 'status'), self::STATUS_FILTERS) ? $this->textFilter($request, 'status') : null;

        $schoolYears = SchoolYear::query()
            ->when($search !== '', fn (Builder $query) => $query->whereLike('name', '%'.$search.'%'))
            ->when($status !== null, fn (Builder $query) => $this->applyStatusFilter($query, $status))
            ->withCount(['terms', 'classrooms'])
            ->orderByDesc('starts_on')
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        $activeSchoolYear = SchoolYear::query()
            ->active()
            ->with('terms')
            ->withCount('classrooms')
            ->orderByDesc('starts_on')
            ->orderByDesc('id')
            ->first();

        return view('admin.school-years.index', [
            'schoolYears' => $schoolYears,
            'activeSchoolYear' => $activeSchoolYear,
            'status' => $status,
            'statusOptions' => self::STATUS_FILTERS,
            'isFiltered' => $search !== '' || $status !== null,
        ]);
    }

    /**
     * Show the form for a new school year.
     */
    public function create(): View
    {
        return view('admin.school-years.form', [
            'schoolYear' => new SchoolYear,
            'termsCount' => 0,
            'activeSchoolYear' => SchoolYear::current(),
        ]);
    }

    /**
     * Create a school year, optionally with four quarters, and optionally make it the active one.
     */
    public function store(StoreSchoolYearRequest $request, AcademicCalendar $calendar): RedirectResponse
    {
        [$schoolYear, $currentTerm] = DB::transaction(function () use ($request, $calendar): array {
            $schoolYear = SchoolYear::query()->create($request->safe()->only(['name', 'starts_on', 'ends_on']));

            if ($request->boolean('create_quarters')) {
                $calendar->createQuarters($schoolYear);
            }

            $currentTerm = $request->boolean('make_active') ? $calendar->activateSchoolYear($schoolYear) : null;

            return [$schoolYear, $currentTerm];
        });

        $message = $request->boolean('create_quarters')
            ? "{$schoolYear->name} was created with 4 quarters."
            : "{$schoolYear->name} was created. Add its quarters next.";

        if ($request->boolean('make_active')) {
            $message .= ' '.$this->activationNote($schoolYear, $currentTerm?->name);
        }

        return redirect()->route('admin.school-years.show', $schoolYear)->with('status', $message);
    }

    /**
     * Show a school year with its quarters timeline and classroom counts.
     */
    public function show(SchoolYear $schoolYear): View
    {
        $schoolYear->load(['terms' => fn (HasMany $query) => $query->withCount(['quizzes', 'lessonPlans'])])
            ->loadCount('classrooms');

        $classroomCounts = $schoolYear->classrooms()
            ->toBase()
            ->selectRaw('grade_level, count(*) as total_count, sum(case when archived_at is null then 1 else 0 end) as open_count')
            ->groupBy('grade_level')
            ->get()
            ->keyBy(fn (object $row): int => (int) $row->grade_level);

        $classroomsByGrade = collect(GradeLevel::cases())->map(fn (GradeLevel $grade): array => [
            'grade' => $grade,
            'total' => (int) ($classroomCounts->get($grade->value)->total_count ?? 0),
            'open' => (int) ($classroomCounts->get($grade->value)->open_count ?? 0),
        ]);

        $learnersCount = DB::table('classroom_student')
            ->join('classrooms', 'classrooms.id', '=', 'classroom_student.classroom_id')
            ->where('classrooms.school_year_id', $schoolYear->getKey())
            ->distinct()
            ->count('classroom_student.student_id');

        return view('admin.school-years.show', [
            'schoolYear' => $schoolYear,
            'classroomsByGrade' => $classroomsByGrade,
            'learnersCount' => $learnersCount,
            'canAddTerm' => $schoolYear->terms->count() < AcademicCalendar::MAX_TERMS,
        ]);
    }

    /**
     * Show the form for editing a school year.
     */
    public function edit(SchoolYear $schoolYear): View
    {
        return view('admin.school-years.form', [
            'schoolYear' => $schoolYear,
            'termsCount' => $schoolYear->terms()->count(),
            'activeSchoolYear' => SchoolYear::current(),
        ]);
    }

    /**
     * Update a school year's name and dates; optionally create its quarters (when it has none) and activate it.
     */
    public function update(UpdateSchoolYearRequest $request, SchoolYear $schoolYear, AcademicCalendar $calendar): RedirectResponse
    {
        $activates = $request->boolean('make_active') && ! $schoolYear->is_active;
        $createsQuarters = $request->boolean('create_quarters');

        $currentTerm = DB::transaction(function () use ($request, $schoolYear, $calendar, $activates, $createsQuarters): ?AcademicTerm {
            $schoolYear->update($request->safe()->only(['name', 'starts_on', 'ends_on']));

            if ($createsQuarters) {
                $calendar->createQuarters($schoolYear);
            }

            return $activates ? $calendar->activateSchoolYear($schoolYear) : null;
        });

        $message = "{$schoolYear->name} was updated.";

        if ($createsQuarters) {
            $message .= ' Its 4 quarters were created.';
        }

        if ($activates) {
            $message .= ' '.$this->activationNote($schoolYear, $currentTerm?->name);
        }

        return redirect()->route('admin.school-years.show', $schoolYear)->with('status', $message);
    }

    /**
     * Delete a school year (its quarters cascade), unless it is active or has classrooms.
     */
    public function destroy(SchoolYear $schoolYear): RedirectResponse
    {
        if ($schoolYear->is_active) {
            return redirect()->back(fallback: route('admin.school-years.show', $schoolYear))
                ->with('error', 'Activate another school year first.');
        }

        if ($schoolYear->classrooms()->exists()) {
            return redirect()->back(fallback: route('admin.school-years.show', $schoolYear))
                ->with('error', "{$schoolYear->name} has classrooms. Archive or move them first.");
        }

        $schoolYear->delete();

        return redirect()->route('admin.school-years.index')->with('status', "{$schoolYear->name} was deleted.");
    }

    /**
     * Limit the list to the active year, years that start after today, or years that ended before today.
     *
     * The boundaries are compared as "Y-m-d" strings chosen so both stored date formats sort correctly.
     *
     * @param  Builder<SchoolYear>  $query
     */
    private function applyStatusFilter(Builder $query, string $status): void
    {
        match ($status) {
            'active' => $query->where('is_active', true),
            'upcoming' => $query->where('starts_on', '>=', today()->addDay()->toDateString()),
            'ended' => $query->where('ends_on', '<', today()->toDateString()),
        };
    }

    /**
     * Read a query-string filter as squished text, or an empty string when it is missing or not text.
     */
    private function textFilter(Request $request, string $key): string
    {
        $value = $request->query($key);

        return is_string($value) ? Str::squish($value) : '';
    }

    /**
     * Describe the outcome of activating a school year.
     */
    private function activationNote(SchoolYear $schoolYear, ?string $currentTermName): string
    {
        return $currentTermName !== null
            ? "It is now the active school year, and {$currentTermName} is the current quarter."
            : 'It is now the active school year. No quarter covers today, so choose the current quarter.';
    }
}
