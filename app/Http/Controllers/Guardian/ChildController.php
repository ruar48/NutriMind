<?php

namespace App\Http\Controllers\Guardian;

use App\Enums\Permission;
use App\Enums\ProgressStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Guardian\IndexChildRequest;
use App\Http\Requests\Guardian\ShowChildRequest;
use App\Models\LearnerFeedback;
use App\Models\Mission;
use App\Models\MissionProgress;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\Reward;
use App\Models\Subject;
use App\Models\User;
use App\Services\LearnerProgressService;
use App\Services\LearnerProgressSummary;
use App\Services\LevelCalculator;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Routing\Attributes\Controllers\Authorize;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * The parent's linked learners: the "My children" list and each child's progress page.
 */
class ChildController extends Controller
{
    /**
     * How many children the list shows per page.
     */
    public const CHILDREN_PER_PAGE = 12;

    /**
     * The filter bar appears when the parent follows more children than this (or a filter is active).
     */
    public const FILTER_THRESHOLD = 4;

    /**
     * How many quiz attempts the quizzes tab shows per page.
     */
    public const ATTEMPTS_PER_PAGE = 15;

    /**
     * How many missions the missions tab shows per page.
     */
    public const MISSIONS_PER_PAGE = 10;

    /**
     * How many feedback messages the feedback tab shows per page.
     */
    public const FEEDBACK_PER_PAGE = 10;

    /**
     * How many rewards the overview's rewards shelf shows.
     */
    public const OVERVIEW_REWARDS = 6;

    /**
     * List the learners linked to the parent with their level and average score.
     */
    #[Authorize(Permission::ViewChildProgress)]
    public function index(IndexChildRequest $request, LevelCalculator $levels): View
    {
        $parent = $request->user();
        $search = $request->searchTerm();
        $grade = $request->gradeLevel();
        $isFiltered = $search !== null || $grade !== null;

        $children = $parent->linkedStudents()
            ->with([
                'studentProfile',
                'enrolledClassrooms' => fn (BelongsToMany $query) => $query->active()
                    ->with('subject')
                    ->orderBy('classrooms.name')
                    ->orderBy('classrooms.id'),
            ])
            ->when($grade !== null, fn (Builder $query) => $query->whereHas(
                'studentProfile',
                fn (Builder $profile) => $profile->where('grade_level', $grade),
            ))
            ->when($search !== null, fn (Builder $query) => $query->where(fn (Builder $matches) => $matches
                ->search($search)
                ->orWhereHas('studentProfile', fn (Builder $profile) => $profile->whereLike('lrn', '%'.$search.'%'))))
            ->orderBy('users.name')
            ->orderBy('users.id')
            ->paginate(self::CHILDREN_PER_PAGE)
            ->withQueryString();

        $childIds = $children->getCollection()->modelKeys();
        $bestScores = $this->bestScoresPerChild($childIds);
        $missionsCompleted = $this->missionsCompletedPerChild($childIds);

        return view('parent.children.index', [
            'children' => $children,
            'cards' => $children->getCollection()->map(function (User $child) use ($levels, $bestScores, $missionsCompleted): array {
                $totalPoints = (int) ($child->studentProfile?->total_points ?? 0);
                $scores = $bestScores->get($child->getKey(), collect());

                return [
                    'student' => $child,
                    'stats' => [
                        'levelProgress' => $levels->progress($totalPoints),
                        'maxLevel' => $levels->maxLevel(),
                        'totalPoints' => $totalPoints,
                        'averageScore' => $scores->isEmpty() ? null : round((float) $scores->avg(), 1),
                        'quizzesTaken' => $scores->count(),
                        'missionsCompleted' => $missionsCompleted->get($child->getKey(), 0),
                        'lastActiveAt' => $child->studentProfile?->last_active_at,
                    ],
                ];
            })->all(),
            'showFilters' => $isFiltered || $children->total() > self::FILTER_THRESHOLD,
            'filters' => ['grade' => $grade?->value],
            'isFiltered' => $isFiltered,
        ]);
    }

    /**
     * Show a linked learner's progress on one of the page's tabs.
     */
    #[Authorize('viewAsGuardian', 'student')]
    public function show(ShowChildRequest $request, User $student, LearnerProgressService $progress): View
    {
        $parent = $request->user();
        $tab = $request->tab();
        $canViewFeedback = $parent->hasPermission(Permission::ViewTeacherFeedback);

        if ($tab === 'feedback') {
            Gate::authorize(Permission::ViewTeacherFeedback);
        }

        $summary = $progress->summarize($student);
        $attemptUrl = $parent->hasPermission(Permission::ViewQuizDetails)
            ? fn (QuizAttempt $attempt): string => route('parent.children.attempts.show', [$student, $attempt])
            : null;

        $tabData = match ($tab) {
            'quizzes' => $this->quizzesTab($request, $student),
            'missions' => $this->missionsTab($request, $student),
            'feedback' => $this->feedbackTab($request, $student, $attemptUrl),
            'rewards' => $this->rewardsTab($request, $summary),
            default => [],
        };

        return view('parent.children.show', [
            'student' => $student,
            'tab' => $tab,
            'summary' => $summary,
            'relationship' => $parent->linkedStudents()->whereKey($student->getKey())->first(['users.id'])?->pivot->relationship,
            'attemptUrl' => $attemptUrl,
            'canViewFeedback' => $canViewFeedback,
            'attemptCount' => $student->quizAttempts()->submitted()->count(),
            'unreadFeedbackCount' => $canViewFeedback
                ? $student->feedbackReceived()->visibleToGuardians()->unread()->count()
                : 0,
            ...$tabData,
        ]);
    }

    /**
     * Get the best percentage of each quiz each child submitted, grouped by learner ID.
     *
     * @param  list<int>  $childIds
     * @return Collection<int, Collection<int, float>>
     */
    private function bestScoresPerChild(array $childIds): Collection
    {
        if ($childIds === []) {
            return collect();
        }

        return QuizAttempt::query()
            ->whereIn('student_id', $childIds)
            ->submitted()
            ->toBase()
            ->select(['student_id', 'quiz_id'])
            ->selectRaw('max(percentage) as best')
            ->groupBy('student_id', 'quiz_id')
            ->get()
            ->groupBy(fn (object $row): int => (int) $row->student_id)
            ->map(fn (Collection $rows): Collection => $rows->map(fn (object $row): float => (float) $row->best)->values());
    }

    /**
     * Count each child's completed missions, keyed by learner ID.
     *
     * @param  list<int>  $childIds
     * @return Collection<int, int>
     */
    private function missionsCompletedPerChild(array $childIds): Collection
    {
        if ($childIds === []) {
            return collect();
        }

        return MissionProgress::query()
            ->whereIn('student_id', $childIds)
            ->completed()
            ->toBase()
            ->select('student_id')
            ->selectRaw('count(*) as aggregate')
            ->groupBy('student_id')
            ->pluck('aggregate', 'student_id')
            ->mapWithKeys(fn (mixed $count, mixed $studentId): array => [(int) $studentId => (int) $count]);
    }

    /**
     * Get the requested subject when the tab offers it as a filter, or null.
     *
     * @param  Collection<int, string>  $subjectOptions
     */
    private function offeredSubjectId(ShowChildRequest $request, Collection $subjectOptions): ?int
    {
        $subjectId = $request->subjectId();

        return $subjectId !== null && $subjectOptions->has($subjectId) ? $subjectId : null;
    }

    /**
     * Build the quizzes tab: the child's submitted attempts, newest first, filtered by subject and result.
     *
     * @return array<string, mixed>
     */
    private function quizzesTab(ShowChildRequest $request, User $student): array
    {
        $subjectOptions = Subject::query()
            ->whereIn('id', Quiz::query()->select('subject_id')->whereIn('id', $student->quizAttempts()->submitted()->select('quiz_id')))
            ->orderBy('name')
            ->orderBy('id')
            ->pluck('name', 'id');

        $subjectId = $this->offeredSubjectId($request, $subjectOptions);
        $passed = $request->passed();

        $attempts = $student->quizAttempts()
            ->submitted()
            ->when($subjectId !== null, fn (Builder $query) => $query->whereIn(
                'quiz_id',
                Quiz::query()->select('id')->where('subject_id', $subjectId),
            ))
            ->when($passed !== null, fn (Builder $query) => $query->where('passed', $passed))
            ->with('quiz.subject')
            ->latest('submitted_at')
            ->orderByDesc('id')
            ->paginate(self::ATTEMPTS_PER_PAGE)
            ->withQueryString();

        return [
            'attempts' => $attempts,
            'subjectOptions' => $subjectOptions->all(),
            'filters' => [
                'subject' => $subjectId,
                'result' => match ($passed) {
                    true => ShowChildRequest::RESULT_PASSED,
                    false => ShowChildRequest::RESULT_NOT_PASSED,
                    null => null,
                },
            ],
            'isFiltered' => $subjectId !== null || $passed !== null,
        ];
    }

    /**
     * Build the missions tab: the child's missions in progress and completed, filtered by status and subject.
     *
     * @return array<string, mixed>
     */
    private function missionsTab(ShowChildRequest $request, User $student): array
    {
        $statuses = array_map(fn (ProgressStatus $status): string => $status->value, ShowChildRequest::MISSION_STATUSES);

        $subjectOptions = Subject::query()
            ->whereIn('id', Mission::query()->select('subject_id')->whereIn('id', $student->missionProgress()->whereIn('status', $statuses)->select('mission_id')))
            ->orderBy('name')
            ->orderBy('id')
            ->pluck('name', 'id');

        $subjectId = $this->offeredSubjectId($request, $subjectOptions);
        $status = $request->missionStatus();

        $missions = $student->missionProgress()
            ->whereIn('status', $status === null ? $statuses : [$status->value])
            ->when($subjectId !== null, fn (Builder $query) => $query->whereIn(
                'mission_id',
                Mission::query()->select('id')->where('subject_id', $subjectId),
            ))
            ->with('mission.subject')
            ->latest('updated_at')
            ->orderByDesc('id')
            ->paginate(self::MISSIONS_PER_PAGE)
            ->withQueryString();

        return [
            'missions' => $missions,
            'missionPoints' => (int) $student->missionProgress()->sum('points_earned'),
            'subjectOptions' => $subjectOptions->all(),
            'statusOptions' => collect(ShowChildRequest::MISSION_STATUSES)
                ->mapWithKeys(fn (ProgressStatus $status): array => [$status->value => $status->label()])
                ->all(),
            'filters' => ['subject' => $subjectId, 'status' => $status?->value],
            'isFiltered' => $subjectId !== null || $status !== null,
        ];
    }

    /**
     * Build the feedback tab: the guardian-visible feedback about the child, newest first, filtered by type.
     *
     * The unread messages on the page are marked as read; they keep their "New" badge on this visit. Reading
     * does not change the teacher's message, so updated_at is left alone.
     *
     * @param  (Closure(QuizAttempt): string)|null  $attemptUrl
     * @return array<string, mixed>
     */
    private function feedbackTab(ShowChildRequest $request, User $student, ?Closure $attemptUrl): array
    {
        $type = $request->feedbackType();

        $feedback = $student->feedbackReceived()
            ->visibleToGuardians()
            ->when($type !== null, fn (Builder $query) => $query->where('type', $type))
            ->with(['teacher', 'classroom.subject', 'quizAttempt.quiz'])
            ->latest()
            ->orderByDesc('id')
            ->paginate(self::FEEDBACK_PER_PAGE)
            ->withQueryString();

        $newFeedbackIds = $feedback->getCollection()
            ->filter(fn (LearnerFeedback $message): bool => $message->read_at === null)
            ->modelKeys();

        if ($newFeedbackIds !== []) {
            LearnerFeedback::query()->whereKey($newFeedbackIds)->unread()->toBase()->update(['read_at' => now()]);
        }

        return [
            'feedback' => $feedback,
            'newFeedbackIds' => $newFeedbackIds,
            'feedbackAttemptUrl' => fn (LearnerFeedback $message): ?string => $attemptUrl !== null
                && $message->quizAttempt !== null
                && $message->quizAttempt->student()->is($student)
                && $message->quizAttempt->submitted_at !== null
                    ? $attemptUrl($message->quizAttempt)
                    : null,
            'filters' => ['type' => $type?->value],
            'isFiltered' => $type !== null,
        ];
    }

    /**
     * Build the rewards tab: the rewards the child earned and the active rewards still to earn, by type.
     *
     * @return array<string, mixed>
     */
    private function rewardsTab(ShowChildRequest $request, LearnerProgressSummary $summary): array
    {
        $type = $request->rewardType();

        return [
            'earnedRewards' => $summary->rewards
                ->when($type !== null, fn (EloquentCollection $rewards) => $rewards->filter(fn (Reward $reward): bool => $reward->type === $type))
                ->values(),
            'lockedRewards' => Reward::query()
                ->active()
                ->whereKeyNot($summary->rewards->modelKeys())
                ->when($type !== null, fn (Builder $query) => $query->where('type', $type))
                ->orderBy('name')
                ->orderBy('id')
                ->get(),
            'filters' => ['reward_type' => $type?->value],
            'isFiltered' => $type !== null,
        ];
    }
}
