<?php

namespace App\Http\Controllers\Teacher;

use App\Enums\FeedbackType;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Teacher\Concerns\ReadsMonitoringFilters;
use App\Http\Requests\Teacher\StoreFeedbackRequest;
use App\Http\Requests\Teacher\UpdateFeedbackRequest;
use App\Models\Classroom;
use App\Models\LearnerFeedback;
use App\Models\QuizAttempt;
use App\Models\User;
use App\Services\LearnerProgressService;
use App\Services\Monitoring\FeedbackSuggestions;
use App\Services\Monitoring\FeedbackTargets;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Attributes\Controllers\Authorize;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class FeedbackController extends Controller
{
    use ReadsMonitoringFilters;

    /**
     * The number of feedback messages per page.
     */
    public const PER_PAGE = 12;

    /**
     * List the feedback the teacher wrote, newest first.
     */
    #[Authorize('viewAny', LearnerFeedback::class)]
    public function index(Request $request): View
    {
        $teacher = $request->user();
        $classrooms = Classroom::query()->accessibleBy($teacher)->orderBy('name')->orderBy('id')->get();
        $classroom = $this->classroomFilter($request, $classrooms);
        $type = $this->enumFilter($request, 'type', FeedbackType::class);
        $search = $this->searchFilter($request);
        $student = $this->studentFilter($request, $teacher);

        $feedback = $teacher->feedbackGiven()
            ->with(['student.studentProfile', 'classroom.subject', 'quizAttempt.quiz'])
            ->when($classroom !== null, fn (Builder $query) => $query->where('learner_feedback.classroom_id', $classroom->getKey()))
            ->when($type !== null, fn (Builder $query) => $query->where('learner_feedback.type', $type))
            ->when($student !== null, fn (Builder $query) => $query->whereBelongsTo($student, 'student'))
            ->when($search !== null, fn (Builder $query) => $query->whereHas('student', fn (Builder $learner) => $learner->search($search)))
            ->latest()
            ->orderByDesc('id')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        return view('teacher.feedback.index', [
            'feedback' => $feedback,
            'classrooms' => $classrooms,
            'classroom' => $classroom,
            'type' => $type,
            'student' => $student,
            'learnerIds' => $this->learnersOfTeacher($teacher, $feedback->getCollection()->pluck('student_id')->all()),
            'attemptUrl' => fn (QuizAttempt $attempt): ?string => $teacher->can('viewResults', $attempt->quiz)
                ? route('teacher.attempts.show', $attempt)
                : null,
        ]);
    }

    /**
     * Show the feedback form. Without ?student= the teacher first picks one of their learners; ?attempt= links one of
     * the learner's attempts at the teacher's quizzes.
     */
    #[Authorize('create', LearnerFeedback::class)]
    public function create(Request $request, FeedbackTargets $targets, LearnerProgressService $progress, FeedbackSuggestions $suggestions): View
    {
        $teacher = $request->user();
        $student = $this->queriedRecord($request, 'student', fn (int $id): ?User => User::query()->ofRole(UserRole::Student)->find($id));

        if ($student === null) {
            return view('teacher.feedback.create', [
                'student' => null,
                'learnerOptions' => $targets->learnerOptions($teacher),
            ]);
        }

        Gate::authorize('giveFeedbackTo', $student);

        $attempt = $this->queriedRecord($request, 'attempt', fn (int $id): ?QuizAttempt => $targets->allowsAttempt($teacher, $student, $id)
            ? QuizAttempt::query()->with('quiz')->find($id)
            : null);
        $classrooms = $targets->classrooms($teacher, $student);

        $feedback = new LearnerFeedback([
            'classroom_id' => $this->defaultClassroomId($classrooms, $attempt),
            'quiz_attempt_id' => $attempt?->getKey(),
            'is_visible_to_guardians' => true,
        ]);

        return view('teacher.feedback.create', [
            'student' => $student,
            'feedback' => $feedback,
            ...$this->formData($teacher, $student, $targets, $classrooms, $attempt, $progress, $suggestions),
        ]);
    }

    /**
     * Save new feedback for one of the teacher's learners.
     */
    #[Authorize('create', LearnerFeedback::class)]
    public function store(StoreFeedbackRequest $request): RedirectResponse
    {
        $student = User::query()->ofRole(UserRole::Student)->findOrFail($request->integer('student_id'));

        Gate::authorize('giveFeedbackTo', $student);

        $request->user()->feedbackGiven()->create([
            'student_id' => $student->getKey(),
            'classroom_id' => $request->validated('classroom_id'),
            'quiz_attempt_id' => $request->validated('quiz_attempt_id'),
            'type' => $request->validated('type'),
            'message' => $request->validated('message'),
            'is_visible_to_guardians' => $request->boolean('is_visible_to_guardians', true),
        ]);

        return to_route('teacher.feedback.index')->with('status', 'Your feedback for '.$student->name.' was saved.');
    }

    /**
     * Show the form to change the teacher's feedback.
     */
    #[Authorize('update', 'feedback')]
    public function edit(Request $request, LearnerFeedback $feedback, FeedbackTargets $targets, LearnerProgressService $progress, FeedbackSuggestions $suggestions): View
    {
        $teacher = $request->user();
        $feedback->load(['student.studentProfile', 'classroom', 'quizAttempt.quiz']);
        $student = $feedback->student;
        $attempt = $feedback->quizAttempt !== null && $targets->allowsAttempt($teacher, $student, $feedback->quizAttempt->getKey())
            ? $feedback->quizAttempt
            : null;
        $classrooms = $targets->classrooms($teacher, $student);

        return view('teacher.feedback.edit', [
            'student' => $student,
            'feedback' => $feedback,
            'classroomUnavailable' => $feedback->classroom_id !== null && ! $classrooms->contains('id', $feedback->classroom_id),
            'attemptUnavailable' => $feedback->quizAttempt !== null && $attempt === null,
            ...$this->formData($teacher, $student, $targets, $classrooms, $attempt, $progress, $suggestions),
        ]);
    }

    /**
     * Save changes to the teacher's feedback. An edited message counts as unread again for parents and guardians.
     */
    #[Authorize('update', 'feedback')]
    public function update(UpdateFeedbackRequest $request, LearnerFeedback $feedback): RedirectResponse
    {
        $feedback->fill([
            'classroom_id' => $request->validated('classroom_id'),
            'quiz_attempt_id' => $request->validated('quiz_attempt_id'),
            'type' => $request->validated('type'),
            'message' => $request->validated('message'),
            'is_visible_to_guardians' => $request->has('is_visible_to_guardians')
                ? $request->boolean('is_visible_to_guardians')
                : $feedback->is_visible_to_guardians,
        ]);

        if ($feedback->isDirty('message')) {
            $feedback->read_at = null;
        }

        $feedback->save();

        return to_route('teacher.feedback.index')->with('status', 'Your feedback for '.$feedback->student->name.' was updated.');
    }

    /**
     * Delete the teacher's feedback.
     */
    #[Authorize('delete', 'feedback')]
    public function destroy(LearnerFeedback $feedback): RedirectResponse
    {
        $name = $feedback->student->name;

        $feedback->delete();

        return to_route('teacher.feedback.index')->with('status', 'Your feedback for '.$name.' was deleted.');
    }

    /**
     * Build the choices and suggestions of the feedback form.
     *
     * @param  EloquentCollection<int, Classroom>  $classrooms
     * @return array<string, mixed>
     */
    private function formData(User $teacher, User $student, FeedbackTargets $targets, EloquentCollection $classrooms, ?QuizAttempt $attempt, LearnerProgressService $progress, FeedbackSuggestions $suggestions): array
    {
        $attempts = $targets->attempts($teacher, $student);

        if ($attempt !== null && ! $attempts->contains($attempt)) {
            $attempts->prepend($attempt);
        }

        $canViewLearner = $teacher->can('viewAsTeacher', $student);
        $subjectIds = $canViewLearner ? $progress->teacherSubjectIds($teacher, $student) : [];
        $summary = $canViewLearner ? $progress->summarize($student, $subjectIds) : null;

        return [
            'classroomOptions' => $classrooms->mapWithKeys(fn (Classroom $classroom): array => [
                $classroom->getKey() => $classroom->name.($classroom->isArchived() ? ' (archived)' : ''),
            ]),
            'attemptOptions' => $attempts->mapWithKeys(fn (QuizAttempt $quizAttempt): array => [
                $quizAttempt->getKey() => FeedbackTargets::attemptLabel($quizAttempt),
            ]),
            'canViewLearner' => $canViewLearner,
            'summary' => $summary,
            'suggestions' => $summary === null ? [] : $suggestions->for($summary, $subjectIds),
        ];
    }

    /**
     * Pick the classroom a new feedback starts with: the attempt's classroom, or the learner's only active classroom.
     *
     * @param  EloquentCollection<int, Classroom>  $classrooms
     */
    private function defaultClassroomId(EloquentCollection $classrooms, ?QuizAttempt $attempt): ?int
    {
        if ($attempt?->classroom_id !== null && $classrooms->contains('id', $attempt->classroom_id)) {
            return (int) $attempt->classroom_id;
        }

        $active = $classrooms->reject(fn (Classroom $classroom): bool => $classroom->isArchived());

        return $active->count() === 1 ? (int) $active->first()->getKey() : null;
    }

    /**
     * Resolve a record id passed in the query string. Blank means none; anything that does not resolve is 404.
     *
     * @template TModel of object
     *
     * @param  callable(int): (TModel|null)  $resolve
     * @return TModel|null
     */
    private function queriedRecord(Request $request, string $key, callable $resolve): ?object
    {
        $value = $request->query($key);

        if ($value === null || $value === '') {
            return null;
        }

        $record = is_string($value) && ctype_digit($value) ? $resolve((int) $value) : null;

        abort_if($record === null, 404);

        return $record;
    }

    /**
     * Resolve the ?student= filter: a learner the teacher can follow or has written feedback about (404 otherwise).
     */
    private function studentFilter(Request $request, User $teacher): ?User
    {
        return $this->queriedRecord($request, 'student', function (int $id) use ($teacher): ?User {
            $student = User::query()->ofRole(UserRole::Student)->find($id);

            $isKnown = $student !== null && (
                $teacher->feedbackGiven()->whereBelongsTo($student, 'student')->exists()
                || $teacher->can('viewAsTeacher', $student)
            );

            return $isKnown ? $student : null;
        });
    }

    /**
     * Get which of the learners the teacher can open (enrolled in one of the teacher's classrooms).
     *
     * @param  list<int|string>  $studentIds
     * @return list<int>
     */
    private function learnersOfTeacher(User $teacher, array $studentIds): array
    {
        if ($studentIds === []) {
            return [];
        }

        return User::query()
            ->ofRole(UserRole::Student)
            ->whereKey(array_values(array_unique($studentIds)))
            ->whereHas('enrolledClassrooms', fn (Builder $query) => $query->accessibleBy($teacher))
            ->pluck('id')
            ->map(fn (mixed $id): int => (int) $id)
            ->all();
    }
}
