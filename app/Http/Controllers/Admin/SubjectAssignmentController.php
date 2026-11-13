<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateSubjectAssignmentRequest;
use App\Models\Classroom;
use App\Models\Quiz;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Attributes\Controllers\Middleware;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Subject assignment: which subjects each teacher may teach. Subject-based teacher access (classrooms, quizzes,
 * question bank, lesson plans) follows these assignments (administrator only, see SchoolYearController).
 */
#[Middleware('role:admin')]
class SubjectAssignmentController extends Controller
{
    /**
     * The coverage filters of the list, keyed by query value.
     *
     * @var array<string, string>
     */
    public const COVERAGE_FILTERS = [
        'unassigned' => 'Without subjects',
        'assigned' => 'With subjects',
    ];

    /**
     * List the active teachers, each with a form of subject checkboxes and what revoking would lock.
     */
    public function index(Request $request): View
    {
        $search = $this->textFilter($request, 'search');
        $coverage = array_key_exists($this->textFilter($request, 'coverage'), self::COVERAGE_FILTERS) ? $this->textFilter($request, 'coverage') : null;
        $subjectFilter = ctype_digit($this->textFilter($request, 'subject')) ? (int) $this->textFilter($request, 'subject') : null;

        $teachers = User::query()
            ->ofRole(UserRole::Teacher)
            ->active()
            ->search($search)
            ->when($subjectFilter !== null, fn (Builder $query) => $query->whereHas('assignedSubjects', fn (Builder $query) => $query->whereKey($subjectFilter)))
            ->when($coverage === 'unassigned', fn (Builder $query) => $query->whereDoesntHave('assignedSubjects'))
            ->when($coverage === 'assigned', fn (Builder $query) => $query->whereHas('assignedSubjects'))
            ->with(['assignedSubjects' => fn (BelongsToMany $query) => $query->orderBy('name')->orderBy('subjects.id')])
            ->orderBy('name')
            ->orderBy('id')
            ->paginate(10)
            ->withQueryString();

        $teacherIds = $teachers->getCollection()->modelKeys();

        $subjects = Subject::query()
            ->withCount(['teachers' => fn (Builder $query) => $query->where('users.role', UserRole::Teacher->value)->where('users.is_active', true)])
            ->orderBy('name')
            ->orderBy('id')
            ->get();

        return view('admin.subject-assignments.index', [
            'teachers' => $teachers,
            'activeSubjects' => $subjects->where('is_active', true)->values(),
            'subjects' => $subjects,
            'classroomCounts' => $this->countsByTeacherAndSubject(Classroom::query(), $teacherIds),
            'quizCounts' => $this->countsByTeacherAndSubject(Quiz::query(), $teacherIds),
            'activeTeachersCount' => User::query()->ofRole(UserRole::Teacher)->active()->count(),
            'unassignedTeachersCount' => User::query()->ofRole(UserRole::Teacher)->active()->whereDoesntHave('assignedSubjects')->count(),
            'coverage' => $coverage,
            'coverageOptions' => self::COVERAGE_FILTERS,
            'subjectFilter' => $subjectFilter,
            'isFiltered' => $search !== '' || $coverage !== null || $subjectFilter !== null,
        ]);
    }

    /**
     * Sync the teacher's subjects: new ones are recorded as assigned by the current administrator, removed ones are
     * revoked, and unchanged ones keep who assigned them.
     */
    public function update(UpdateSubjectAssignmentRequest $request, User $teacher): RedirectResponse
    {
        $requestedIds = collect($request->validated('subject_ids') ?? [])->map(fn (mixed $id): int => (int) $id)->unique();
        $assigned = $teacher->assignedSubjects()->orderBy('name')->get();

        $attachIds = $requestedIds->diff($assigned->modelKeys())->values();
        $detached = $assigned->reject(fn (Subject $subject): bool => $requestedIds->contains($subject->getKey()))->values();

        DB::transaction(function () use ($teacher, $attachIds, $detached, $request): void {
            if ($attachIds->isNotEmpty()) {
                $teacher->assignedSubjects()->attach(
                    $attachIds->mapWithKeys(fn (int $id): array => [$id => ['assigned_by' => $request->user()->getKey()]])->all(),
                );
            }

            if ($detached->isNotEmpty()) {
                $teacher->assignedSubjects()->detach($detached->modelKeys());
            }
        });

        $attached = Subject::query()->whereKey($attachIds->all())->orderBy('name')->get();

        return redirect()
            ->to(url()->previous(route('admin.subject-assignments.index')).'#teacher-'.$teacher->getKey())
            ->with('status', $this->summary($teacher, $attached, $detached));
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
     * Count a teacher-owned model per teacher and subject for the given teachers.
     *
     * @param  Builder<Classroom>|Builder<Quiz>  $query
     * @param  list<int>  $teacherIds
     * @return array<string, int> keyed "{teacher id}:{subject id}"
     */
    private function countsByTeacherAndSubject(Builder $query, array $teacherIds): array
    {
        if ($teacherIds === []) {
            return [];
        }

        return $query->toBase()
            ->whereIn('teacher_id', $teacherIds)
            ->selectRaw('teacher_id, subject_id, count(*) as aggregate')
            ->groupBy('teacher_id', 'subject_id')
            ->get()
            ->mapWithKeys(fn (object $row): array => [$row->teacher_id.':'.$row->subject_id => (int) $row->aggregate])
            ->all();
    }

    /**
     * Describe what changed, including how many classrooms each revoked subject locks.
     *
     * @param  Collection<int, Subject>  $attached
     * @param  Collection<int, Subject>  $detached
     */
    private function summary(User $teacher, Collection $attached, Collection $detached): string
    {
        if ($attached->isEmpty() && $detached->isEmpty()) {
            return "{$teacher->name}'s subjects are unchanged.";
        }

        $lockedClassrooms = $detached->isEmpty()
            ? collect()
            : $teacher->classrooms()
                ->toBase()
                ->whereIn('subject_id', $detached->modelKeys())
                ->selectRaw('subject_id, count(*) as aggregate')
                ->groupBy('subject_id')
                ->pluck('aggregate', 'subject_id');

        $changes = [];

        if ($attached->isNotEmpty()) {
            $changes[] = 'assigned '.Arr::join($attached->pluck('name')->all(), ', ', ' and ');
        }

        if ($detached->isNotEmpty()) {
            $changes[] = 'revoked '.Arr::join($detached->map(function (Subject $subject) use ($lockedClassrooms): string {
                $count = (int) ($lockedClassrooms[$subject->getKey()] ?? 0);

                return $count === 0 ? $subject->name : $subject->name.' ('.trans_choice(':count classroom locked|:count classrooms locked', $count).')';
            })->all(), ', ', ' and ');
        }

        return "{$teacher->name}'s subjects were saved: ".implode('; ', $changes).'.';
    }
}
