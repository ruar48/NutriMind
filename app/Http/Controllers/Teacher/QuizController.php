<?php

namespace App\Http\Controllers\Teacher;

use App\Enums\GradeLevel;
use App\Enums\QuizStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Teacher\StoreQuizRequest;
use App\Http\Requests\Teacher\UpdateQuizRequest;
use App\Models\AcademicTerm;
use App\Models\Classroom;
use App\Models\LearningModule;
use App\Models\Quiz;
use App\Models\SchoolYear;
use App\Models\Subject;
use App\Models\User;
use App\Services\QuestionMedia;
use App\Services\Settings;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Attributes\Controllers\Authorize;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class QuizController extends Controller
{
    /**
     * Quizzes per page of the list.
     */
    private const PER_PAGE = 15;

    /**
     * List the teacher's quizzes with status tabs and filters.
     */
    #[Authorize('viewAny', Quiz::class)]
    public function index(Request $request): View
    {
        /** @var User $teacher */
        $teacher = $request->user();
        $subjectIds = $teacher->assignedSubjectIds();

        $subjectOptions = $teacher->assignedSubjects->sortBy('name')->mapWithKeys(fn (Subject $subject): array => [$subject->id => $subject->name]);
        $classroomOptions = Classroom::query()
            ->accessibleBy($teacher)
            ->orderBy('name')
            ->orderBy('id')
            ->get(['id', 'name', 'archived_at'])
            ->sortBy(fn (Classroom $classroom): int => $classroom->isArchived() ? 1 : 0)
            ->mapWithKeys(fn (Classroom $classroom): array => [$classroom->id => $classroom->name.($classroom->isArchived() ? ' (archived)' : '')]);

        $filters = [
            'subject' => $this->whitelisted($request->query('subject'), $subjectOptions),
            'classroom' => $this->whitelisted($request->query('classroom'), $classroomOptions),
            'search' => is_string($request->query('search')) ? trim($request->query('search')) : '',
        ];
        $status = is_string($request->query('status')) ? QuizStatus::tryFrom($request->query('status')) : null;

        $filtered = Quiz::query()
            ->whereBelongsTo($teacher, 'teacher')
            ->whereIn('subject_id', $subjectIds)
            ->when($filters['subject'], fn (Builder $query, int $subjectId) => $query->where('subject_id', $subjectId))
            ->when($filters['classroom'], fn (Builder $query, int $classroomId) => $query->whereHas(
                'classrooms',
                fn (Builder $classrooms) => $classrooms->whereKey($classroomId),
            ))
            ->when($filters['search'] !== '', fn (Builder $query) => $query->whereLike('title', '%'.$filters['search'].'%'));

        $statusCounts = (clone $filtered)->toBase()
            ->select('status')
            ->selectRaw('count(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $quizzes = (clone $filtered)
            ->when($status, fn (Builder $query, QuizStatus $status) => $query->where('status', $status))
            ->with(['subject', 'classrooms' => fn ($classrooms) => $classrooms->orderBy('classrooms.name')->orderBy('classrooms.id')])
            ->withCount('questions')
            ->withSum('questions', 'points')
            ->withCount(['attempts as submitted_attempts_count' => fn (Builder $attempts) => $attempts->whereNotNull('submitted_at')])
            ->withAvg(['attempts as average_percentage' => fn (Builder $attempts) => $attempts->whereNotNull('submitted_at')], 'percentage')
            ->withExists('attempts')
            ->addSelect(['next_due_at' => DB::table('classroom_quiz')
                ->select('due_at')
                ->whereColumn('classroom_quiz.quiz_id', 'quizzes.id')
                ->where('due_at', '>=', now())
                ->orderBy('due_at')
                ->limit(1),
            ])
            ->withCasts(['next_due_at' => 'datetime', 'average_percentage' => 'float'])
            ->latest()
            ->orderByDesc('id')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        return view('teacher.quizzes.index', [
            'quizzes' => $quizzes,
            'status' => $status,
            'counts' => [
                'all' => (int) $statusCounts->sum(),
                ...collect(QuizStatus::cases())->mapWithKeys(fn (QuizStatus $case): array => [$case->value => (int) ($statusCounts[$case->value] ?? 0)])->all(),
            ],
            'filters' => $filters,
            'subjectOptions' => $subjectOptions->all(),
            'classroomOptions' => $classroomOptions->all(),
            'lockedCount' => Quiz::query()->whereBelongsTo($teacher, 'teacher')->whereNotIn('subject_id', $subjectIds)->count(),
        ]);
    }

    /**
     * Show the form for a new quiz, optionally prefilled for a subject or one of the teacher's classrooms.
     */
    #[Authorize('create', Quiz::class)]
    public function create(Request $request, Settings $settings): View
    {
        /** @var User $teacher */
        $teacher = $request->user();
        $classroom = filter_var($request->query('classroom'), FILTER_VALIDATE_INT) === false
            ? null
            : Classroom::query()->accessibleBy($teacher)->active()->whereKey((int) $request->query('classroom'))->first();
        $subjectId = filter_var($request->query('subject'), FILTER_VALIDATE_INT);
        $defaultMaxAttempts = $settings->get('default_max_attempts');

        $quiz = new Quiz([
            'subject_id' => $classroom?->subject_id ?? ($subjectId === false ? null : $subjectId),
            'grade_level' => $classroom?->grade_level,
            'passing_score' => (int) $settings->get('default_passing_score', 75),
            'max_attempts' => $defaultMaxAttempts === null ? null : (int) $defaultMaxAttempts,
            'shuffle_questions' => false,
            'show_correct_answers' => true,
        ]);

        return view('teacher.quizzes.form', $this->formData($teacher, $quiz, $classroom === null ? [] : [$classroom->id]));
    }

    /**
     * Create a draft quiz and assign it to the selected classrooms.
     */
    #[Authorize('create', Quiz::class)]
    public function store(StoreQuizRequest $request): RedirectResponse
    {
        /** @var User $teacher */
        $teacher = $request->user();

        $quiz = DB::transaction(function () use ($request, $teacher): Quiz {
            $quiz = $teacher->quizzes()->create([...$request->quizAttributes(), 'status' => QuizStatus::Draft]);
            $quiz->classrooms()->attach(array_fill_keys($request->classroomIds(), $request->availabilityWindow()));

            return $quiz;
        });

        return redirect()->route('teacher.quizzes.show', $quiz)->with('status', 'Quiz created. Add your first question.');
    }

    /**
     * Show the quiz builder: settings summary, publishing checklist and questions.
     */
    #[Authorize('view', 'quiz')]
    public function show(Quiz $quiz): View
    {
        $quiz->load([
            'subject',
            'module',
            'academicTerm',
            'questions',
            'classrooms' => fn ($classrooms) => $classrooms->orderBy('classrooms.name')->orderBy('classrooms.id'),
        ])->loadCount([
            'attempts as submitted_attempts_count' => fn (Builder $attempts) => $attempts->whereNotNull('submitted_at'),
        ])->loadExists('attempts');

        return view('teacher.quizzes.show', [
            'quiz' => $quiz,
            'checklist' => self::publishChecklist($quiz),
            'questionsLockedMessage' => QuizQuestionController::questionsLockedMessage($quiz),
            'totalPoints' => (int) $quiz->questions->sum('points'),
            'window' => $this->availabilityWindowOf($quiz),
        ]);
    }

    /**
     * Show the form for editing the quiz's settings.
     */
    #[Authorize('update', 'quiz')]
    public function edit(Request $request, Quiz $quiz): View
    {
        $quiz->load(['subject', 'module', 'academicTerm', 'classrooms']);

        return view('teacher.quizzes.form', $this->formData(
            $request->user(),
            $quiz,
            $quiz->classrooms->whereNull('archived_at')->modelKeys(),
        ));
    }

    /**
     * Save the quiz's settings and classroom assignments.
     */
    #[Authorize('update', 'quiz')]
    public function update(UpdateQuizRequest $request, Quiz $quiz): RedirectResponse
    {
        DB::transaction(function () use ($request, $quiz): void {
            $quiz->update($request->quizAttributes());
            $quiz->classrooms()->sync($request->classroomSync());
        });

        return redirect()->route('teacher.quizzes.show', $quiz)->with('status', 'Quiz settings saved.');
    }

    /**
     * Delete a quiz nobody attempted, with its questions and their media files.
     */
    #[Authorize('delete', 'quiz')]
    public function destroy(Quiz $quiz, QuestionMedia $media): RedirectResponse
    {
        $paths = $media->pathsOf($quiz->questions()->get(['id', 'image_path', 'video_path']));

        DB::transaction(function () use ($quiz, $media, $paths): void {
            $quiz->delete();
            $media->delete(...$paths);
        });

        return redirect()->route('teacher.quizzes.index')->with('status', '“'.$quiz->title.'” was deleted.');
    }

    /**
     * Get the publishing checklist of a quiz whose questions and classrooms are loaded.
     *
     * @return array{hasQuestions: bool, hasClassroom: bool, isReviewed: bool, isReady: bool}
     */
    public static function publishChecklist(Quiz $quiz): array
    {
        $checklist = [
            'hasQuestions' => $quiz->questions->isNotEmpty(),
            'hasClassroom' => $quiz->classrooms->whereNull('archived_at')->isNotEmpty(),
            'isReviewed' => $quiz->isReviewed(),
        ];

        return [...$checklist, 'isReady' => ! in_array(false, $checklist, true)];
    }

    /**
     * Get the data of the quiz settings form.
     *
     * @param  list<int>  $assignedClassroomIds
     * @return array<string, mixed>
     */
    private function formData(User $teacher, Quiz $quiz, array $assignedClassroomIds): array
    {
        $teacher->assignedSubjectIds();
        $subjects = $teacher->assignedSubjects
            ->filter(fn (Subject $subject): bool => $subject->is_active || (int) $subject->id === (int) $quiz->subject_id)
            ->sortBy('name')
            ->values();

        $schoolYear = SchoolYear::current();
        $terms = $schoolYear === null ? collect() : $schoolYear->terms;

        if ($quiz->academicTerm !== null && ! $terms->contains($quiz->academicTerm)) {
            $terms = $terms->push($quiz->academicTerm);
        }

        $hasAttempts = $quiz->exists && $quiz->attempts()->exists();

        return [
            'quiz' => $quiz,
            'subjectOptions' => $subjects->mapWithKeys(fn (Subject $subject): array => [$subject->id => $subject->name])->all(),
            'modules' => LearningModule::query()
                ->whereIn('subject_id', $subjects->modelKeys())
                ->with('subject')
                ->orderBy('subject_id')
                ->orderBy('grade_level')
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get(),
            'termOptions' => $terms->mapWithKeys(fn (AcademicTerm $term): array => [$term->id => $term->name])->all(),
            'classrooms' => Classroom::query()
                ->accessibleBy($teacher)
                ->active()
                ->with('subject')
                ->orderBy('grade_level')
                ->orderBy('name')
                ->orderBy('id')
                ->get(),
            'assignedClassroomIds' => array_map(fn (mixed $id): int => (int) $id, $assignedClassroomIds),
            'attemptedClassroomIds' => $hasAttempts
                ? $quiz->attempts()->whereNotNull('classroom_id')->distinct()->pluck('classroom_id')->map(fn (mixed $id): int => (int) $id)->all()
                : [],
            'hasAttempts' => $hasAttempts,
            'window' => $quiz->exists ? $this->availabilityWindowOf($quiz) : ['available_from' => null, 'due_at' => null],
            'gradeOptions' => GradeLevel::options(),
        ];
    }

    /**
     * Get the availability window of the quiz's assignments: the earliest opening date and the earliest due date.
     *
     * @return array{available_from: mixed, due_at: mixed}
     */
    private function availabilityWindowOf(Quiz $quiz): array
    {
        $assignments = $quiz->classrooms->map(fn (Classroom $classroom) => $classroom->pivot);

        return [
            'available_from' => $assignments->pluck('available_from')->filter()->min(),
            'due_at' => $assignments->pluck('due_at')->filter()->min(),
        ];
    }

    /**
     * Get a filter value when it is one of the offered options, or null.
     *
     * @param  Collection<int, string>  $options
     */
    private function whitelisted(mixed $value, Collection $options): ?int
    {
        $id = filter_var($value, FILTER_VALIDATE_INT);

        return $id !== false && $options->has($id) ? $id : null;
    }
}
