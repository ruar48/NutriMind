<?php

namespace App\View\Composers;

use App\Enums\ForumContentStatus;
use App\Enums\Permission;
use App\Enums\UserRole;
use App\Models\ForumReport;
use App\Models\ForumThread;
use App\Models\LearnerFeedback;
use App\Models\User;
use Illuminate\Contracts\Auth\Guard;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\View\View;

/**
 * Builds the sidebar navigation of the portal layout for the signed-in user's role.
 *
 * Items the user cannot use (missing permission, forum turned off) are left out, and so are sections that end
 * up empty. An item is active on its own route and on every route of the area below it, so child pages
 * highlight their parent item.
 *
 * @phpstan-type NavigationItem array{label: string, url: string, icon: string, active: bool, badge: int|string|null}
 * @phpstan-type NavigationSection array{label: string, items: list<NavigationItem>}
 */
class NavigationComposer
{
    public function __construct(
        private readonly Guard $auth,
        private readonly Request $request,
    ) {}

    /**
     * Share the navigation with the layout.
     */
    public function compose(View $view): void
    {
        $user = $this->auth->user();

        $view->with('navigation', $user instanceof User ? $this->navigationFor($user) : []);
    }

    /**
     * Build the navigation sections of the user's portal.
     *
     * @return list<NavigationSection>
     */
    private function navigationFor(User $user): array
    {
        $sections = match ($user->role) {
            UserRole::Admin => $this->admin(),
            UserRole::Teacher => $this->teacher($user),
            UserRole::Parent => $this->parent($user),
            default => [],
        };

        return array_values(array_filter($sections, fn (array $section): bool => $section['items'] !== []));
    }

    /**
     * Build the Administrator Portal navigation.
     *
     * @return list<NavigationSection>
     */
    private function admin(): array
    {
        return [
            $this->section('Overview', [
                $this->item('Dashboard', 'admin.dashboard', 'layout-dashboard'),
                $this->item('School Analytics', 'admin.analytics.index', 'chart-line', ['admin.analytics.*']),
            ]),
            $this->section('Academic Setup', [
                $this->item('School Years & Terms', 'admin.school-years.index', 'calendar-range', ['admin.school-years.*', 'admin.terms.*']),
                $this->item('Subjects', 'admin.subjects.index', 'book-open', ['admin.subjects.*']),
                $this->item('Subject Assignment', 'admin.subject-assignments.index', 'user-check', ['admin.subject-assignments.*']),
            ]),
            $this->section('People', [
                $this->item('All Users', 'admin.users.index', 'users', ['admin.users.*']),
                $this->item('Teachers', 'admin.teachers.index', 'graduation-cap', ['admin.teachers.*']),
                $this->item('Learners', 'admin.students.index', 'backpack', ['admin.students.*']),
                $this->item('Parents & Guardians', 'admin.parents.index', 'house-heart', ['admin.parents.*']),
                $this->item('Roles & Permissions', 'admin.roles.index', 'shield-check', ['admin.roles.*']),
            ]),
            $this->section('Game Content', [
                $this->item('Learning Modules', 'admin.modules.index', 'map', ['admin.modules.*', 'admin.activities.*']),
                $this->item('Missions', 'admin.missions.index', 'flag', ['admin.missions.*']),
                $this->item('Rewards', 'admin.rewards.index', 'trophy', ['admin.rewards.*']),
                $this->item('Lesson Alignment', 'admin.content-alignment.index', 'link', ['admin.content-alignment.*']),
            ]),
            $this->section('Insights', [
                $this->item('Reports & Export', 'admin.reports.index', 'file-spreadsheet', ['admin.reports.*']),
            ]),
            $this->section('Community', [
                $this->item('Parent Forum', 'forum.index', 'messages-square', ['forum.*']),
                $this->item('Moderation', 'admin.forum.reports.index', 'shield', ['admin.forum.reports.*', 'admin.forum.pending.*'], $this->moderationQueueCount()),
                $this->item('Forum Categories', 'admin.forum-categories.index', 'tag', ['admin.forum-categories.*']),
            ]),
            $this->section('System', [
                $this->item('Settings', 'admin.settings.edit', 'settings', ['admin.settings.*']),
            ]),
        ];
    }

    /**
     * Build the Teacher Portal navigation.
     *
     * @return list<NavigationSection>
     */
    private function teacher(User $user): array
    {
        return [
            $this->section('Overview', [
                $this->item('Dashboard', 'teacher.dashboard', 'layout-dashboard'),
                $user->hasPermission(Permission::ViewAnalytics)
                    ? $this->item('Learning Analytics', 'teacher.analytics.index', 'chart-line', ['teacher.analytics.*'])
                    : null,
            ]),
            $this->section('Teaching', [
                $this->item('My Subjects', 'teacher.subjects.index', 'book-open', ['teacher.subjects.*']),
                $this->item('Classrooms', 'teacher.classrooms.index', 'school', ['teacher.classrooms.*', 'teacher.imports.*']),
                $user->hasPermission(Permission::ManageLessonPlans)
                    ? $this->item('Lesson Plans', 'teacher.lesson-plans.index', 'notebook-pen', ['teacher.lesson-plans.*'])
                    : null,
            ]),
            $this->section('Assessments', [
                $this->item('Quizzes', 'teacher.quizzes.index', 'clipboard-check', ['teacher.quizzes.*', 'teacher.attempts.*']),
                $user->hasPermission(Permission::ManageQuestionBank)
                    ? $this->item('Question Bank', 'teacher.question-bank.index', 'library', ['teacher.question-bank.*'])
                    : null,
            ]),
            $this->section('Monitoring', [
                $this->item('Learners', 'teacher.students.index', 'users', ['teacher.students.*']),
                $this->item('Mission Progress', 'teacher.missions.index', 'flag', ['teacher.missions.*']),
                $user->hasPermission(Permission::GiveFeedback)
                    ? $this->item('Feedback', 'teacher.feedback.index', 'message-circle', ['teacher.feedback.*'])
                    : null,
            ]),
            $this->section('Insights', [
                $this->item('Reports', 'teacher.reports.index', 'file-spreadsheet', ['teacher.reports.*']),
            ]),
            $this->section('Community', [
                $user->can('viewAny', ForumThread::class)
                    ? $this->item('Parent Forum', 'forum.index', 'messages-square', ['forum.*'])
                    : null,
            ]),
        ];
    }

    /**
     * Build the Parent Portal navigation.
     *
     * @return list<NavigationSection>
     */
    private function parent(User $user): array
    {
        return [
            $this->section('Overview', [
                $this->item('Dashboard', 'parent.dashboard', 'layout-dashboard'),
                $this->item('My Children', 'parent.children.index', 'users-round', ['parent.children.*'], $this->unreadFeedbackCount($user)),
            ]),
            $this->section('Community', [
                $user->can('viewAny', ForumThread::class)
                    ? $this->item('Parent Forum', 'forum.index', 'messages-square', ['forum.*'])
                    : null,
            ]),
        ];
    }

    /**
     * Build a navigation section from its items, leaving out the missing ones.
     *
     * @param  list<NavigationItem|null>  $items
     * @return NavigationSection
     */
    private function section(string $label, array $items): array
    {
        return [
            'label' => $label,
            'items' => array_values(array_filter($items)),
        ];
    }

    /**
     * Build a navigation item, or nothing when its route is not registered.
     *
     * @param  list<string>|null  $activePatterns  route name patterns that highlight the item (default: its route)
     * @return NavigationItem|null
     */
    private function item(string $label, string $routeName, string $icon, ?array $activePatterns = null, ?int $badge = null): ?array
    {
        if (! Route::has($routeName)) {
            return null;
        }

        return [
            'label' => $label,
            'url' => route($routeName),
            'icon' => $icon,
            'active' => $this->request->routeIs(...($activePatterns ?? [$routeName])),
            'badge' => $badge,
        ];
    }

    /**
     * Count the unresolved forum reports and the threads waiting for approval.
     */
    private function moderationQueueCount(): ?int
    {
        $count = ForumReport::query()->unresolved()->count()
            + ForumThread::query()->where('status', ForumContentStatus::Pending)->count();

        return $count > 0 ? $count : null;
    }

    /**
     * Count the unread feedback that teachers shared about the parent's linked learners.
     */
    private function unreadFeedbackCount(User $user): ?int
    {
        if (! $user->hasPermission(Permission::ViewTeacherFeedback)) {
            return null;
        }

        $count = LearnerFeedback::query()
            ->visibleToGuardians()
            ->unread()
            ->whereIn('student_id', $user->linkedStudents()->select('users.id'))
            ->count();

        return $count > 0 ? $count : null;
    }
}
