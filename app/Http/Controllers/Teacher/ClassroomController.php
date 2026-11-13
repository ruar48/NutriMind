<?php

namespace App\Http\Controllers\Teacher;

use App\Enums\LearningMode;
use App\Enums\PerformanceBand;
use App\Enums\QuizStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Teacher\StoreClassroomRequest;
use App\Http\Requests\Teacher\UpdateClassroomRequest;
use App\Models\Classroom;
use App\Models\Mission;
use App\Models\MissionProgress;
use App\Models\Quiz;
use App\Models\SchoolYear;
use App\Models\Subject;
use App\Models\User;
use App\Services\LevelCalculator;
use App\Services\Settings;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Attributes\Controllers\Authorize;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ClassroomController extends Controller
{
    /**
     * Classroom cards per page on the index.
     */
    public const CLASSROOMS_PER_PAGE = 12;

    /**
     * Learners per page on the roster tab.
     */
    public const LEARNERS_PER_PAGE = 25;

    /**
     * Assigned quizzes per page on the quizzes tab.
     */
    public const QUIZZES_PER_PAGE = 15;

    /**
     * The tabs of the classroom page; the first one is the default.
     *
     * @var list<string>
     */
    public const TABS = ['learners', 'quizzes', 'overview'];

    /**
     * The longest search term used by the index and roster searches.
     */
    private const SEARCH_MAX_LENGTH = 100;

    /**
     * Subject names up to this length are used as they are in suggested classroom names; longer ones use the subject code.
     */
    private const SHORT_SUBJECT_NAME_LENGTH = 12;

    /**
     * List the teacher's classrooms in subjects still assigned to them, as cards with their key figures.
     */
    #[Authorize('viewAny', Classroom::class)]
    public function index(Request $request): View
    {
        $teacher = $request->user();
        $assignedSubjectIds = $teacher->assignedSubjectIds();
        $isArchivedTab = $request->query('status') === 'archived';
        $subjectId = $this->subjectFilter($request, $assignedSubjectIds);
        $search = $this->searchTerm($request);

        $classrooms = Classroom::query()
            ->accessibleBy($teacher)
            ->when($isArchivedTab, fn (Builder $query) => $query->archived(), fn (Builder $query) => $query->active())
            ->when($subjectId !== null, fn (Builder $query) => $query->where('classrooms.subject_id', $subjectId))
            ->when($search !== null, function (Builder $query) use ($search): void {
                $pattern = '%'.$search.'%';

                $query->where(function (Builder $query) use ($pattern): void {
                    $query->whereLike('classrooms.name', $pattern)
                        ->orWhereLike('classrooms.section', $pattern)
                        ->orWhereLike('classrooms.room', $pattern)
                        ->orWhereLike('classrooms.class_code', $pattern);
                });
            })
            ->with(['subject', 'schoolYear'])
            ->withCount([
                'students',
                'quizzes as published_quizzes_count' => fn (Builder $query) => $query->published(),
            ])
            ->withAvg(['quizAttempts as average_score' => fn (Builder $query) => $query->submitted()], 'percentage')
            ->when(
                $isArchivedTab,
                fn (Builder $query) => $query->orderByDesc('classrooms.archived_at')->orderByDesc('classrooms.id'),
                fn (Builder $query) => $query->orderBy('classrooms.grade_level')->orderBy('classrooms.name')->orderBy('classrooms.id'),
            )
            ->paginate(self::CLASSROOMS_PER_PAGE)
            ->withQueryString();

        $statusCounts = Classroom::query()
            ->accessibleBy($teacher)
            ->toBase()
            ->selectRaw('count(case when archived_at is null then 1 end) as active_count, count(archived_at) as archived_count')
            ->first();

        return view('teacher.classrooms.index', [
            'classrooms' => $classrooms,
            'status' => $isArchivedTab ? 'archived' : 'active',
            'counts' => [
                'active' => (int) $statusCounts->active_count,
                'archived' => (int) $statusCounts->archived_count,
            ],
            'subjectOptions' => $teacher->assignedSubjects
                ->sortBy([['name', 'asc'], ['id', 'asc']])
                ->mapWithKeys(fn (Subject $subject): array => [$subject->id => $subject->name])
                ->all(),
            'lockedCount' => $teacher->classrooms()->whereNotIn('subject_id', $assignedSubjectIds)->count(),
            'isFiltered' => $subjectId !== null || $search !== null,
        ]);
    }

    /**
     * Show the new classroom form (or an empty state when no school year is active).
     */
    #[Authorize('create', Classroom::class)]
    public function create(Request $request, Settings $settings): View
    {
        $subjects = $this->activeSubjects($request->user());

        $classroom = new Classroom([
            'learning_mode' => LearningMode::tryFrom((string) $settings->get('default_learning_mode')) ?? LearningMode::Automatic,
        ]);

        $requestedSubjectId = filter_var($request->query('subject_id'), FILTER_VALIDATE_INT);

        if ($requestedSubjectId !== false && $subjects->contains('id', $requestedSubjectId)) {
            $classroom->subject_id = $requestedSubjectId;
        } elseif ($subjects->count() === 1) {
            $classroom->subject_id = $subjects->first()->id;
        }

        return view('teacher.classrooms.create', [
            'classroom' => $classroom,
            'schoolYear' => SchoolYear::current(),
            'subjectOptions' => $subjects->mapWithKeys(fn (Subject $subject): array => [$subject->id => $subject->name])->all(),
            'subjectShortNames' => $subjects->mapWithKeys(fn (Subject $subject): array => [$subject->id => $this->shortSubjectName($subject)])->all(),
        ]);
    }

    /**
     * Create a classroom for the teacher in the active school year.
     */
    #[Authorize('create', Classroom::class)]
    public function store(StoreClassroomRequest $request): RedirectResponse
    {
        $classroom = $request->user()->classrooms()->create(
            $request->safe()->only(['subject_id', 'grade_level', 'section', 'name', 'room', 'description', 'learning_mode'])
            + ['school_year_id' => $request->schoolYear()?->id],
        );

        return to_route('teacher.classrooms.show', $classroom)
            ->with('status', $classroom->name.' is ready. Share the class code '.$classroom->class_code.' with your learners.');
    }

    /**
     * Show a classroom with its learners, assigned quizzes or overview tab.
     */
    #[Authorize('view', 'classroom')]
    public function show(Request $request, Classroom $classroom, LevelCalculator $levels): View
    {
        $tab = in_array($request->query('tab'), self::TABS, true) ? $request->query('tab') : self::TABS[0];

        $classroom->load(['subject', 'schoolYear'])
            ->loadCount(['students', 'quizzes'])
            ->loadExists('quizAttempts');

        return view('teacher.classrooms.show', [
            'classroom' => $classroom,
            'tab' => $tab,
            'canBeDeleted' => $this->isEmpty($classroom),
            'students' => $tab === 'learners' ? $this->roster($request, $classroom) : null,
            'quizzes' => $tab === 'quizzes' ? $this->assignedQuizzes($classroom) : null,
            'stats' => $tab === 'overview' ? $this->overview($classroom) : null,
            'levels' => $levels,
        ]);
    }

    /**
     * Show the classroom settings form.
     */
    #[Authorize('update', 'classroom')]
    public function edit(Classroom $classroom): View
    {
        $classroom->load(['subject', 'schoolYear']);

        return view('teacher.classrooms.edit', [
            'classroom' => $classroom,
            'subjectShortName' => $this->shortSubjectName($classroom->subject),
        ]);
    }

    /**
     * Update the editable classroom settings (the subject and grade never change).
     */
    #[Authorize('update', 'classroom')]
    public function update(UpdateClassroomRequest $request, Classroom $classroom): RedirectResponse
    {
        $classroom->update($request->safe()->only(['section', 'name', 'room', 'description', 'learning_mode']));

        return to_route('teacher.classrooms.show', $classroom)->with('status', $classroom->name.' was updated.');
    }

    /**
     * Delete an empty classroom; classrooms with learners, quizzes or attempts must be archived instead.
     */
    #[Authorize('delete', 'classroom')]
    public function destroy(Classroom $classroom): RedirectResponse
    {
        $classroom->loadCount(['students', 'quizzes'])->loadExists('quizAttempts');

        if (! $this->isEmpty($classroom)) {
            return to_route('teacher.classrooms.show', $classroom)
                ->with('error', $classroom->name.' still has learners, quizzes or quiz attempts. Archive it instead.');
        }

        $classroom->delete();

        return to_route('teacher.classrooms.index')->with('status', $classroom->name.' was deleted.');
    }

    /**
     * Determine whether the classroom has no learners, no assigned quizzes and no quiz attempts.
     *
     * Reads students_count, quizzes_count and quiz_attempts_exists, which the caller loads.
     */
    private function isEmpty(Classroom $classroom): bool
    {
        return (int) $classroom->students_count === 0
            && (int) $classroom->quizzes_count === 0
            && ! $classroom->quiz_attempts_exists;
    }

    /**
     * Get the enrolled learners with their average score and completed quizzes in this classroom.
     *
     * @return LengthAwarePaginator<int, User>
     */
    private function roster(Request $request, Classroom $classroom): LengthAwarePaginator
    {
        $search = $this->searchTerm($request);
        $submittedHere = function (Builder $query) use ($classroom): void {
            $query->where('quiz_attempts.classroom_id', $classroom->id)->submitted();
        };

        return $classroom->students()
            ->with('studentProfile')
            ->withAvg(['quizAttempts as classroom_average' => $submittedHere], 'percentage')
            ->withAggregate(['quizAttempts as quizzes_completed' => $submittedHere], DB::raw('distinct quiz_attempts.quiz_id'), 'count')
            ->when($search !== null, function (Builder $query) use ($search): void {
                $query->where(function (Builder $query) use ($search): void {
                    $query->search($search)
                        ->orWhereHas('studentProfile', fn (Builder $profile) => $profile->whereLike('student_profiles.lrn', '%'.$search.'%'));
                });
            })
            ->orderBy('users.name')
            ->orderBy('users.id')
            ->paginate(self::LEARNERS_PER_PAGE)
            ->withQueryString();
    }

    /**
     * Get the quizzes assigned to the classroom with how many enrolled learners submitted each and the class average.
     *
     * @return LengthAwarePaginator<int, Quiz>
     */
    private function assignedQuizzes(Classroom $classroom): LengthAwarePaginator
    {
        $rosterIds = $this->rosterIds($classroom);

        return $classroom->quizzes()
            ->withCount('questions')
            ->withAvg(['attempts as classroom_average' => function (Builder $query) use ($classroom): void {
                $query->where('quiz_attempts.classroom_id', $classroom->id)->submitted();
            }], 'percentage')
            ->withAggregate(['attempts as attempted_count' => function (Builder $query) use ($classroom, $rosterIds): void {
                $query->where('quiz_attempts.classroom_id', $classroom->id)
                    ->whereIn('quiz_attempts.student_id', $rosterIds)
                    ->submitted();
            }], DB::raw('distinct quiz_attempts.student_id'), 'count')
            ->orderByPivot('assigned_at', 'desc')
            ->orderByDesc('quizzes.id')
            ->paginate(self::QUIZZES_PER_PAGE)
            ->withQueryString();
    }

    /**
     * Compute the overview figures of the classroom.
     *
     * Averages and pass rates cover every submitted attempt recorded in the classroom (removed learners' history
     * included); completion, missions, activity and bands cover the learners enrolled now.
     *
     * @return array{learners: int, activeThisWeek: int, attempts: int, averageScore: ?float, passRate: ?float, openQuizzes: int, completedPairs: int, expectedPairs: int, completionRate: ?float, missionsAvailable: int, missionsCompleted: int, missionsTotal: int, bandCounts: array<string, int>}
     */
    private function overview(Classroom $classroom): array
    {
        $learners = (int) $classroom->students_count;
        $rosterIds = $this->rosterIds($classroom);

        $attempts = $classroom->quizAttempts()->submitted()->count();
        $passedAttempts = $classroom->quizAttempts()->submitted()->where('passed', true)->count();
        $averageScore = $classroom->quizAttempts()->submitted()->avg('percentage');

        $openQuizIds = $classroom->quizzes()
            ->whereIn('quizzes.status', [QuizStatus::Published, QuizStatus::Closed])
            ->pluck('quizzes.id');

        $completedPairs = DB::query()
            ->fromSub(
                $this->submittedHere($classroom, $rosterIds)
                    ->whereIn('quiz_id', $openQuizIds)
                    ->select('student_id', 'quiz_id')
                    ->distinct(),
                'completed_pairs',
            )
            ->count();
        $expectedPairs = $learners * $openQuizIds->count();

        $missionIds = Mission::query()
            ->active()
            ->where('subject_id', $classroom->subject_id)
            ->forGrade($classroom->grade_level)
            ->pluck('id');
        $missionsCompleted = MissionProgress::query()
            ->completed()
            ->whereIn('mission_id', $missionIds)
            ->whereIn('student_id', $rosterIds)
            ->count();

        $activeThisWeek = $classroom->students()
            ->whereHas('studentProfile', fn (Builder $profile) => $profile->where('last_active_at', '>=', now()->subDays(7)))
            ->count();

        $learnerAverages = $this->submittedHere($classroom, $rosterIds)
            ->groupBy('student_id')
            ->select('student_id', DB::raw('avg(percentage) as average'))
            ->pluck('average');

        $bandCounts = $learnerAverages
            ->countBy(fn (mixed $average): string => PerformanceBand::fromScore(round((float) $average, 1))->value)
            ->all();
        $bandCounts[PerformanceBand::NoData->value] = $learners - $learnerAverages->count();

        return [
            'learners' => $learners,
            'activeThisWeek' => $activeThisWeek,
            'attempts' => $attempts,
            'averageScore' => $averageScore === null ? null : round((float) $averageScore, 1),
            'passRate' => $attempts === 0 ? null : round($passedAttempts / $attempts * 100, 1),
            'openQuizzes' => $openQuizIds->count(),
            'completedPairs' => $completedPairs,
            'expectedPairs' => $expectedPairs,
            'completionRate' => $expectedPairs === 0 ? null : round($completedPairs / $expectedPairs * 100, 1),
            'missionsAvailable' => $missionIds->count(),
            'missionsCompleted' => $missionsCompleted,
            'missionsTotal' => $missionIds->count() * $learners,
            'bandCounts' => $bandCounts,
        ];
    }

    /**
     * Get a subquery of the IDs of the learners enrolled in the classroom.
     */
    private function rosterIds(Classroom $classroom): QueryBuilder
    {
        return DB::table('classroom_student')->where('classroom_id', $classroom->id)->select('student_id');
    }

    /**
     * Get a query of the submitted attempts recorded in the classroom by the learners enrolled now.
     */
    private function submittedHere(Classroom $classroom, QueryBuilder $rosterIds): QueryBuilder
    {
        return DB::table('quiz_attempts')
            ->where('classroom_id', $classroom->id)
            ->whereNotNull('submitted_at')
            ->whereIn('student_id', $rosterIds);
    }

    /**
     * Get the teacher's active assigned subjects, ordered by name.
     *
     * @return Collection<int, Subject>
     */
    private function activeSubjects(User $teacher): Collection
    {
        return $teacher->assignedSubjects()
            ->active()
            ->orderBy('subjects.name')
            ->orderBy('subjects.id')
            ->get(['subjects.id', 'subjects.name', 'subjects.code']);
    }

    /**
     * Get the subject label used in suggested classroom names ("Health 5 – Sampaguita", "EPP-HE 6 – Narra").
     */
    private function shortSubjectName(Subject $subject): string
    {
        return mb_strlen($subject->name) <= self::SHORT_SUBJECT_NAME_LENGTH ? $subject->name : $subject->code;
    }

    /**
     * Get the subject filter when it names one of the teacher's assigned subjects; anything else means no filter.
     *
     * @param  list<int>  $assignedSubjectIds
     */
    private function subjectFilter(Request $request, array $assignedSubjectIds): ?int
    {
        $subjectId = filter_var($request->query('subject'), FILTER_VALIDATE_INT);

        return $subjectId !== false && in_array($subjectId, $assignedSubjectIds, true) ? $subjectId : null;
    }

    /**
     * Get the trimmed search term (at most 100 characters), or null when there is none.
     */
    private function searchTerm(Request $request): ?string
    {
        $search = $request->query('search');

        if (! is_string($search)) {
            return null;
        }

        $search = mb_substr(trim($search), 0, self::SEARCH_MAX_LENGTH);

        return $search === '' ? null : $search;
    }
}
