<?php

use App\Enums\Permission;
use App\Enums\UserRole;
use App\Models\ForumReport;
use App\Models\ForumThread;
use App\Models\LearnerFeedback;
use App\Models\User;
use App\Services\RolePermissions;
use App\Services\Settings;
use App\View\Components\Icon;
use App\View\Composers\NavigationComposer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/**
 * Compose the layout navigation for the user, optionally while the request is on the given URL.
 *
 * @return list<array{label: string, items: list<array{label: string, url: string, icon: string, active: bool, badge: int|string|null}>}>
 */
function navigationComposedFor(?User $user, ?string $url = null): array
{
    if ($user !== null) {
        test()->actingAs($user);
    }

    if ($url !== null) {
        $request = Request::create($url);
        $route = Route::getRoutes()->match($request);
        $request->setRouteResolver(fn () => $route);
        app()->instance('request', $request);
    }

    $view = view('components.layouts.app');
    app(NavigationComposer::class)->compose($view);

    return $view->getData()['navigation'];
}

/**
 * Map the navigation to its section labels and item labels.
 *
 * @param  list<array{label: string, items: list<array{label: string}>}>  $navigation
 * @return array<string, list<string>>
 */
function navigationLabels(array $navigation): array
{
    return collect($navigation)->mapWithKeys(fn (array $section): array => [
        $section['label'] => array_column($section['items'], 'label'),
    ])->all();
}

/**
 * Find a navigation item by its label.
 *
 * @param  list<array{label: string, items: list<array<string, mixed>>}>  $navigation
 * @return array<string, mixed>|null
 */
function navigationItem(array $navigation, string $label): ?array
{
    return collect($navigation)->flatMap(fn (array $section): array => $section['items'])->firstWhere('label', $label);
}

it('builds the administrator navigation', function () {
    $navigation = navigationComposedFor(User::factory()->admin()->create());

    expect(navigationLabels($navigation))->toBe([
        'Overview' => ['Dashboard', 'School Analytics'],
        'Academic Setup' => ['School Years & Terms', 'Subjects', 'Subject Assignment'],
        'People' => ['All Users', 'Teachers', 'Learners', 'Parents & Guardians', 'Roles & Permissions'],
        'Game Content' => ['Learning Modules', 'Missions', 'Rewards', 'Lesson Alignment'],
        'Insights' => ['Reports & Export'],
        'Community' => ['Parent Forum', 'Moderation', 'Forum Categories'],
        'System' => ['Settings'],
    ]);
});

it('builds the teacher navigation', function () {
    $navigation = navigationComposedFor(User::factory()->teacher()->create());

    expect(navigationLabels($navigation))->toBe([
        'Overview' => ['Dashboard', 'Learning Analytics'],
        'Teaching' => ['My Subjects', 'Classrooms', 'Lesson Plans'],
        'Assessments' => ['Quizzes', 'Question Bank'],
        'Monitoring' => ['Learners', 'Mission Progress', 'Feedback'],
        'Insights' => ['Reports'],
        'Community' => ['Parent Forum'],
    ]);
});

it('builds the parent navigation', function () {
    $navigation = navigationComposedFor(User::factory()->parent()->create());

    expect(navigationLabels($navigation))->toBe([
        'Overview' => ['Dashboard', 'My Children'],
        'Community' => ['Parent Forum'],
    ]);
});

it('links every item to its route with an icon from the icon set', function (UserRole $role, string $label, string $routeName, string $icon) {
    $item = navigationItem(navigationComposedFor(User::factory()->{$role->value}()->create()), $label);

    expect($item)->url->toBe(route($routeName))->icon->toBe($icon)
        ->and(Icon::has($icon))->toBeTrue();
})->with([
    [UserRole::Admin, 'Dashboard', 'admin.dashboard', 'layout-dashboard'],
    [UserRole::Admin, 'School Analytics', 'admin.analytics.index', 'chart-line'],
    [UserRole::Admin, 'School Years & Terms', 'admin.school-years.index', 'calendar-range'],
    [UserRole::Admin, 'Subjects', 'admin.subjects.index', 'book-open'],
    [UserRole::Admin, 'Subject Assignment', 'admin.subject-assignments.index', 'user-check'],
    [UserRole::Admin, 'All Users', 'admin.users.index', 'users'],
    [UserRole::Admin, 'Teachers', 'admin.teachers.index', 'graduation-cap'],
    [UserRole::Admin, 'Learners', 'admin.students.index', 'backpack'],
    [UserRole::Admin, 'Parents & Guardians', 'admin.parents.index', 'house-heart'],
    [UserRole::Admin, 'Roles & Permissions', 'admin.roles.index', 'shield-check'],
    [UserRole::Admin, 'Learning Modules', 'admin.modules.index', 'map'],
    [UserRole::Admin, 'Missions', 'admin.missions.index', 'flag'],
    [UserRole::Admin, 'Rewards', 'admin.rewards.index', 'trophy'],
    [UserRole::Admin, 'Lesson Alignment', 'admin.content-alignment.index', 'link'],
    [UserRole::Admin, 'Reports & Export', 'admin.reports.index', 'file-spreadsheet'],
    [UserRole::Admin, 'Parent Forum', 'forum.index', 'messages-square'],
    [UserRole::Admin, 'Moderation', 'admin.forum.reports.index', 'shield'],
    [UserRole::Admin, 'Forum Categories', 'admin.forum-categories.index', 'tag'],
    [UserRole::Admin, 'Settings', 'admin.settings.edit', 'settings'],
    [UserRole::Teacher, 'Dashboard', 'teacher.dashboard', 'layout-dashboard'],
    [UserRole::Teacher, 'Learning Analytics', 'teacher.analytics.index', 'chart-line'],
    [UserRole::Teacher, 'My Subjects', 'teacher.subjects.index', 'book-open'],
    [UserRole::Teacher, 'Classrooms', 'teacher.classrooms.index', 'school'],
    [UserRole::Teacher, 'Lesson Plans', 'teacher.lesson-plans.index', 'notebook-pen'],
    [UserRole::Teacher, 'Quizzes', 'teacher.quizzes.index', 'clipboard-check'],
    [UserRole::Teacher, 'Question Bank', 'teacher.question-bank.index', 'library'],
    [UserRole::Teacher, 'Learners', 'teacher.students.index', 'users'],
    [UserRole::Teacher, 'Mission Progress', 'teacher.missions.index', 'flag'],
    [UserRole::Teacher, 'Feedback', 'teacher.feedback.index', 'message-circle'],
    [UserRole::Teacher, 'Reports', 'teacher.reports.index', 'file-spreadsheet'],
    [UserRole::Teacher, 'Parent Forum', 'forum.index', 'messages-square'],
    [UserRole::Parent, 'Dashboard', 'parent.dashboard', 'layout-dashboard'],
    [UserRole::Parent, 'My Children', 'parent.children.index', 'users-round'],
    [UserRole::Parent, 'Parent Forum', 'forum.index', 'messages-square'],
]);

it('leaves out a teacher item whose permission the administrator revoked', function (Permission $permission, string $label) {
    $teacher = User::factory()->teacher()->create();
    app(RolePermissions::class)->revoke(UserRole::Teacher, $permission);

    $navigation = navigationComposedFor($teacher);

    expect(navigationItem($navigation, $label))->toBeNull()
        ->and(navigationItem($navigation, 'Quizzes'))->not->toBeNull();
})->with([
    [Permission::ViewAnalytics, 'Learning Analytics'],
    [Permission::ManageLessonPlans, 'Lesson Plans'],
    [Permission::ManageQuestionBank, 'Question Bank'],
    [Permission::GiveFeedback, 'Feedback'],
    [Permission::AccessForum, 'Parent Forum'],
]);

it('drops a section that ends up empty', function () {
    $teacher = User::factory()->teacher()->create();
    app(RolePermissions::class)->revoke(UserRole::Teacher, Permission::AccessForum);

    expect(navigationLabels(navigationComposedFor($teacher)))->not->toHaveKey('Community');
});

it('leaves out the parent forum when the parent may not access it', function () {
    $parent = User::factory()->parent()->create();
    app(RolePermissions::class)->revoke(UserRole::Parent, Permission::AccessForum);

    expect(navigationLabels(navigationComposedFor($parent)))->toBe(['Overview' => ['Dashboard', 'My Children']]);
});

it('leaves out the parent forum for teachers and parents while it is turned off', function (UserRole $role) {
    $user = User::factory()->{$role->value}()->create();
    app(Settings::class)->set('forum_enabled', false);

    expect(navigationItem(navigationComposedFor($user), 'Parent Forum'))->toBeNull();
})->with([UserRole::Teacher, UserRole::Parent]);

it('keeps the parent forum for administrators while it is turned off', function () {
    $admin = User::factory()->admin()->create();
    app(Settings::class)->set('forum_enabled', false);

    expect(navigationItem(navigationComposedFor($admin), 'Parent Forum'))->not->toBeNull();
});

it('counts unresolved reports and pending threads on the moderation item', function () {
    ForumReport::factory()->count(2)->create();
    ForumReport::factory()->resolved()->create();
    ForumThread::factory()->pending()->count(3)->create();
    ForumThread::factory()->hidden()->create();

    expect(navigationItem(navigationComposedFor(User::factory()->admin()->create()), 'Moderation'))->badge->toBe(5);
});

it('shows no moderation badge when nothing waits for moderation', function () {
    ForumReport::factory()->resolved()->create();

    expect(navigationItem(navigationComposedFor(User::factory()->admin()->create()), 'Moderation'))->badge->toBeNull();
});

it('counts the unread feedback shared about the parent\'s children', function () {
    $child = User::factory()->student()->create();
    $otherChild = User::factory()->student()->create();
    $parent = User::factory()->parent()->guardianOf($child)->guardianOf($otherChild)->create();
    LearnerFeedback::factory()->for($child, 'student')->count(2)->create();
    LearnerFeedback::factory()->for($otherChild, 'student')->create();
    LearnerFeedback::factory()->for($child, 'student')->read()->create();
    LearnerFeedback::factory()->for($child, 'student')->hiddenFromGuardians()->create();
    LearnerFeedback::factory()->create();

    expect(navigationItem(navigationComposedFor($parent), 'My Children'))->badge->toBe(3);
});

it('shows no feedback badge when the parent may not read teacher feedback', function () {
    $child = User::factory()->student()->create();
    $parent = User::factory()->parent()->guardianOf($child)->create();
    LearnerFeedback::factory()->for($child, 'student')->create();
    app(RolePermissions::class)->revoke(UserRole::Parent, Permission::ViewTeacherFeedback);

    expect(navigationItem(navigationComposedFor($parent), 'My Children'))->badge->toBeNull();
});

it('highlights the item of the area the current page belongs to', function (UserRole $role, string $url, string $activeLabel) {
    $navigation = navigationComposedFor(User::factory()->{$role->value}()->create(), $url);

    $activeLabels = collect($navigation)->flatMap(fn (array $section): array => $section['items'])->where('active', true)->pluck('label')->all();

    expect($activeLabels)->toBe([$activeLabel]);
})->with([
    'admin dashboard' => [UserRole::Admin, '/admin', 'Dashboard'],
    'admin term edit page' => [UserRole::Admin, '/admin/terms/3/edit', 'School Years & Terms'],
    'admin learner import' => [UserRole::Admin, '/admin/students/import', 'Learners'],
    'admin module activity' => [UserRole::Admin, '/admin/activities/4/edit', 'Learning Modules'],
    'admin pending threads' => [UserRole::Admin, '/admin/forum/pending', 'Moderation'],
    'admin forum categories' => [UserRole::Admin, '/admin/forum-categories/create', 'Forum Categories'],
    'teacher question editor' => [UserRole::Teacher, '/teacher/quizzes/5/questions/9/edit', 'Quizzes'],
    'teacher attempt' => [UserRole::Teacher, '/teacher/attempts/12', 'Quizzes'],
    'teacher classroom import' => [UserRole::Teacher, '/teacher/classrooms/2/import', 'Classrooms'],
    'teacher bank item' => [UserRole::Teacher, '/teacher/question-bank/8/edit', 'Question Bank'],
    'teacher forum thread' => [UserRole::Teacher, '/forum/threads/3', 'Parent Forum'],
    'parent child page' => [UserRole::Parent, '/parent/children/6', 'My Children'],
]);

it('highlights nothing on pages outside the navigation', function () {
    $navigation = navigationComposedFor(User::factory()->teacher()->create(), '/account');

    expect(collect($navigation)->flatMap(fn (array $section): array => $section['items'])->where('active', true))->toBeEmpty();
});

it('builds no navigation for guests', function () {
    expect(navigationComposedFor(null))->toBe([]);
});

it('builds no navigation for learners', function () {
    expect(navigationComposedFor(User::factory()->student()->create()))->toBe([]);
});

it('renders the navigation of the signed-in user on the account settings page', function () {
    $this->actingAs(User::factory()->teacher()->create());

    $this->get(route('account.edit'))
        ->assertOk()
        ->assertSeeTextInOrder(['Overview', 'Dashboard', 'Learning Analytics', 'Teaching', 'My Subjects', 'Classrooms', 'Lesson Plans', 'Assessments', 'Quizzes', 'Question Bank', 'Monitoring', 'Learners', 'Mission Progress', 'Feedback', 'Insights', 'Reports', 'Community', 'Parent Forum'])
        ->assertSee('href="'.route('teacher.quizzes.index').'"', false)
        ->assertDontSeeText('School Analytics');
});

it('renders the moderation badge for administrators', function () {
    ForumThread::factory()->pending()->count(2)->create();
    $this->actingAs(User::factory()->admin()->create());

    $response = $this->get(route('account.edit'));

    $response->assertOk();
    expect($response->getContent())->toMatch('#href="'.preg_quote(route('admin.forum.reports.index'), '#').'".*?Moderation</span>\s*<span[^>]*>2</span>#s');
});

it('removes a revoked item from the rendered parent navigation', function () {
    $parent = User::factory()->parent()->create();
    app(RolePermissions::class)->revoke(UserRole::Parent, Permission::AccessForum);

    $this->actingAs($parent)
        ->get(route('account.edit'))
        ->assertOk()
        ->assertSeeText('My Children')
        ->assertDontSee('href="'.route('forum.index').'"', false);
});
