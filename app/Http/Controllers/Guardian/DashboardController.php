<?php

namespace App\Http\Controllers\Guardian;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Models\ForumThread;
use App\Models\LearnerFeedback;
use App\Models\MissionProgress;
use App\Models\QuizAttempt;
use App\Models\RewardStudent;
use App\Models\User;
use App\Services\LearnerProgressService;
use App\Services\LearnerProgressSummary;
use App\Services\Settings;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Carbon\WeekDay;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * The Parent Portal home: one card per linked learner, this week's highlights, the latest teacher feedback,
 * the newest forum threads and the school announcement.
 *
 * Every parent lands here after signing in, so the page itself is protected by the role:parent route
 * middleware only; each section is shown when the parent holds the permission behind it.
 */
class DashboardController extends Controller
{
    /**
     * How many feedback messages the "From the teachers" list shows.
     */
    public const RECENT_FEEDBACK_LIMIT = 5;

    /**
     * How many forum threads the "Parent forum" list shows.
     */
    public const LATEST_THREADS_LIMIT = 5;

    /**
     * Show the parent's dashboard.
     */
    public function __invoke(Request $request, LearnerProgressService $progress, Settings $settings): View
    {
        $parent = $request->user();
        $canViewProgress = $parent->hasPermission(Permission::ViewChildProgress);
        $canViewFeedback = $parent->hasPermission(Permission::ViewTeacherFeedback);
        $canViewForum = $parent->can('viewAny', ForumThread::class);

        $children = $this->children($parent);
        $childIds = $children->modelKeys();
        $weekStart = now()->toImmutable()->startOfWeek(WeekDay::Monday);

        $summaries = $canViewProgress
            ? $children->mapWithKeys(fn (User $child): array => [$child->getKey() => $progress->summarize($child)])
            : collect();

        $latestFeedback = $canViewFeedback ? $this->latestFeedbackPerChild($childIds) : collect();
        $announcement = $settings->get('announcement');

        return view('parent.dashboard', [
            'parent' => $parent,
            'greeting' => $this->greeting($parent),
            'children' => $children,
            'cards' => $children->map(fn (User $child): array => [
                'student' => $child,
                'stats' => $this->cardStats($summaries->get($child->getKey())),
                'feedback' => $latestFeedback->get($child->getKey()),
            ])->all(),
            'canViewProgress' => $canViewProgress,
            'canViewFeedback' => $canViewFeedback,
            'canViewForum' => $canViewForum,
            'canStartThread' => $canViewForum && $parent->can('create', ForumThread::class),
            'weekStart' => $weekStart,
            'highlights' => $canViewProgress && $childIds !== [] ? $this->highlights($childIds, $weekStart) : null,
            'recentFeedback' => $canViewFeedback && $childIds !== [] ? $this->recentFeedback($childIds) : new EloquentCollection,
            'unreadFeedbackCount' => $canViewFeedback && $childIds !== [] ? $this->unreadFeedbackCount($childIds) : 0,
            'threads' => $canViewForum ? $this->latestThreads($parent) : new EloquentCollection,
            'schoolAnnouncement' => is_string($announcement) && filled($announcement) ? trim($announcement) : null,
        ]);
    }

    /**
     * Get the learners linked to the parent with their profile and active classrooms, ordered by name.
     *
     * @return EloquentCollection<int, User>
     */
    private function children(User $parent): EloquentCollection
    {
        return $parent->linkedStudents()
            ->with([
                'studentProfile',
                'enrolledClassrooms' => fn (BelongsToMany $query) => $query->active()
                    ->with('subject')
                    ->orderBy('classrooms.name')
                    ->orderBy('classrooms.id'),
            ])
            ->orderBy('users.name')
            ->orderBy('users.id')
            ->get();
    }

    /**
     * Build the greeting of the hero banner from the time of day and the parent's first name.
     */
    private function greeting(User $parent): string
    {
        $hour = now()->hour;
        $timeOfDay = match (true) {
            $hour < 12 => 'Good morning',
            $hour < 18 => 'Good afternoon',
            default => 'Good evening',
        };
        $firstName = Str::of((string) $parent->name)->squish()->before(' ')->toString();

        return $firstName === '' ? $timeOfDay : $timeOfDay.', '.$firstName;
    }

    /**
     * Pick the figures a child card shows from the learner's progress summary (null while progress is hidden).
     *
     * @return array{levelProgress: array{level: int, into: int, needed: int, percent: float}, maxLevel: int, totalPoints: int, averageScore: float|null, quizzesTaken: int, missionsCompleted: int, lastActiveAt: CarbonInterface|null, syncOverdue: bool}|null
     */
    private function cardStats(?LearnerProgressSummary $summary): ?array
    {
        if ($summary === null) {
            return null;
        }

        return [
            'levelProgress' => $summary->levelProgress,
            'maxLevel' => $summary->maxLevel,
            'totalPoints' => $summary->totalPoints,
            'averageScore' => $summary->averageScore,
            'quizzesTaken' => $summary->quizzesTaken,
            'missionsCompleted' => $summary->missionsCompleted,
            'lastActiveAt' => $summary->lastActiveAt,
            'syncOverdue' => $summary->isSyncOverdue(),
        ];
    }

    /**
     * Count this week's quizzes completed, rewards earned and missions completed per child.
     *
     * A quiz counts once per child however many times it was retaken this week.
     *
     * @param  list<int>  $childIds
     * @return array{quizzes: Collection<int, int>, rewards: Collection<int, int>, missions: Collection<int, int>}
     */
    private function highlights(array $childIds, CarbonImmutable $weekStart): array
    {
        $quizzes = QuizAttempt::query()
            ->whereIn('student_id', $childIds)
            ->submitted()
            ->where('submitted_at', '>=', $weekStart)
            ->toBase()
            ->select('student_id')
            ->selectRaw('count(distinct quiz_id) as aggregate')
            ->groupBy('student_id')
            ->pluck('aggregate', 'student_id');

        $rewards = RewardStudent::query()
            ->whereIn('student_id', $childIds)
            ->where('awarded_at', '>=', $weekStart)
            ->toBase()
            ->select('student_id')
            ->selectRaw('count(*) as aggregate')
            ->groupBy('student_id')
            ->pluck('aggregate', 'student_id');

        $missions = MissionProgress::query()
            ->whereIn('student_id', $childIds)
            ->completed()
            ->where('completed_at', '>=', $weekStart)
            ->toBase()
            ->select('student_id')
            ->selectRaw('count(*) as aggregate')
            ->groupBy('student_id')
            ->pluck('aggregate', 'student_id');

        $asCounts = fn (Collection $counts): Collection => $counts
            ->mapWithKeys(fn (mixed $count, mixed $studentId): array => [(int) $studentId => (int) $count]);

        return [
            'quizzes' => $asCounts($quizzes),
            'rewards' => $asCounts($rewards),
            'missions' => $asCounts($missions),
        ];
    }

    /**
     * Get the newest guardian-visible feedback about the children, with the teacher and learner.
     *
     * @param  list<int>  $childIds
     * @return EloquentCollection<int, LearnerFeedback>
     */
    private function recentFeedback(array $childIds): EloquentCollection
    {
        return LearnerFeedback::query()
            ->visibleToGuardians()
            ->whereIn('student_id', $childIds)
            ->with(['teacher', 'student'])
            ->latest()
            ->orderByDesc('id')
            ->limit(self::RECENT_FEEDBACK_LIMIT)
            ->get();
    }

    /**
     * Get each child's newest guardian-visible feedback (with its teacher) in one query, keyed by learner ID.
     *
     * A message is the newest when no other visible message about the same learner is newer (later created_at,
     * or the same created_at with a higher ID), which stays portable across SQLite and MySQL.
     *
     * @param  list<int>  $childIds
     * @return Collection<int, LearnerFeedback>
     */
    private function latestFeedbackPerChild(array $childIds): Collection
    {
        if ($childIds === []) {
            return collect();
        }

        return LearnerFeedback::query()
            ->visibleToGuardians()
            ->whereIn('student_id', $childIds)
            ->whereNotExists(function (QueryBuilder $newer): void {
                $newer->selectRaw('1')
                    ->from('learner_feedback as newer')
                    ->whereColumn('newer.student_id', 'learner_feedback.student_id')
                    ->where('newer.is_visible_to_guardians', true)
                    ->where(function (QueryBuilder $later): void {
                        $later->whereColumn('newer.created_at', '>', 'learner_feedback.created_at')
                            ->orWhere(function (QueryBuilder $sameTime): void {
                                $sameTime->whereColumn('newer.created_at', '=', 'learner_feedback.created_at')
                                    ->whereColumn('newer.id', '>', 'learner_feedback.id');
                            });
                    });
            })
            ->with('teacher')
            ->get()
            ->keyBy(fn (LearnerFeedback $feedback): int => (int) $feedback->student_id);
    }

    /**
     * Count the guardian-visible feedback about the children that no parent has read yet.
     *
     * @param  list<int>  $childIds
     */
    private function unreadFeedbackCount(array $childIds): int
    {
        return LearnerFeedback::query()
            ->visibleToGuardians()
            ->unread()
            ->whereIn('student_id', $childIds)
            ->count();
    }

    /**
     * Get the forum threads with the most recent activity that the parent may open, in active categories.
     *
     * @return EloquentCollection<int, ForumThread>
     */
    private function latestThreads(User $parent): EloquentCollection
    {
        return ForumThread::query()
            ->visibleTo($parent)
            ->whereHas('category', fn (Builder $category) => $category->active())
            ->with(['category', 'author'])
            ->withCount(['replies' => fn (Builder $replies) => $replies->published()])
            ->orderByDesc('last_activity_at')
            ->orderByDesc('id')
            ->limit(self::LATEST_THREADS_LIMIT)
            ->get();
    }
}
