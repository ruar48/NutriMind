<?php

use App\Enums\Permission;
use App\Enums\UserRole;
use App\Models\ForumCategory;
use App\Models\ForumReply;
use App\Models\ForumThread;
use App\Models\User;
use App\Policies\ForumThreadPolicy;
use App\Services\RolePermissions;
use App\Services\Settings;
use Illuminate\Support\Facades\DB;

it('sends guests to the sign-in page', function () {
    $this->get(route('forum.index'))->assertRedirectToRoute('login');
});

it('forbids learner accounts', function () {
    $this->actingAs(User::factory()->student()->create())
        ->get(route('forum.index'))
        ->assertForbidden();
});

it('lists published threads with their category, author and reply count for every forum role', function (UserRole $role) {
    $category = ForumCategory::factory()->create(['name' => 'Healthy Recipes']);
    $teacher = User::factory()->teacher()->create(['name' => 'Maricel Santos']);
    $thread = ForumThread::factory()->for($category, 'category')->for($teacher, 'author')->create(['title' => 'Malunggay soup for picky eaters']);
    ForumReply::factory()->for($thread, 'thread')->count(2)->create();
    ForumReply::factory()->for($thread, 'thread')->hidden()->create();

    $response = $this->actingAs(User::factory()->{$role->value}()->create())->get(route('forum.index'));

    $response->assertOk()
        ->assertViewIs('forum.index')
        ->assertSeeText('Malunggay soup for picky eaters')
        ->assertSeeText('Healthy Recipes')
        ->assertSeeText('Maricel Santos')
        ->assertSee('data-forum-author-role="teacher"', false)
        ->assertSee('data-forum-replies-count="2"', false);
})->with([UserRole::Admin, UserRole::Teacher, UserRole::Parent]);

it('lists pinned threads first, then the most recently active', function () {
    ForumThread::factory()->create(['title' => 'Older activity thread', 'last_activity_at' => now()->subDays(3)]);
    ForumThread::factory()->create(['title' => 'Newest activity thread', 'last_activity_at' => now()->subHour()]);
    ForumThread::factory()->pinned()->create(['title' => 'Pinned guidelines thread', 'last_activity_at' => now()->subMonth()]);

    $this->actingAs(User::factory()->parent()->create())
        ->get(route('forum.index'))
        ->assertOk()
        ->assertSeeTextInOrder(['Pinned guidelines thread', 'Newest activity thread', 'Older activity thread']);
});

it('shows a reader only their own pending and hidden threads, with their status', function () {
    $parent = User::factory()->parent()->create();
    ForumThread::factory()->pending()->for($parent, 'author')->create(['title' => 'My thread awaiting review']);
    ForumThread::factory()->hidden()->for($parent, 'author')->create(['title' => 'My thread a moderator hid']);
    ForumThread::factory()->pending()->create(['title' => 'Someone else awaiting review']);
    ForumThread::factory()->hidden()->create(['title' => 'Someone else hidden']);

    $this->actingAs($parent)
        ->get(route('forum.index'))
        ->assertOk()
        ->assertSeeText('My thread awaiting review')
        ->assertSeeText('My thread a moderator hid')
        ->assertSee('data-forum-badge="pending"', false)
        ->assertSee('data-forum-badge="hidden"', false)
        ->assertDontSeeText('Someone else awaiting review')
        ->assertDontSeeText('Someone else hidden');
});

it('shows administrators every thread with its pending and hidden badges', function () {
    ForumThread::factory()->pending()->create(['title' => 'Awaiting review']);
    ForumThread::factory()->hidden()->create(['title' => 'Hidden by moderators']);

    $this->actingAs(User::factory()->admin()->create())
        ->get(route('forum.index'))
        ->assertOk()
        ->assertSeeText('Awaiting review')
        ->assertSeeText('Hidden by moderators')
        ->assertSee('data-forum-badge="pending"', false)
        ->assertSee('data-forum-badge="hidden"', false);
});

it('marks pinned and locked threads', function () {
    ForumThread::factory()->pinned()->locked()->create();

    $this->actingAs(User::factory()->parent()->create())
        ->get(route('forum.index'))
        ->assertOk()
        ->assertSee('data-forum-badge="pinned"', false)
        ->assertSee('data-forum-badge="locked"', false);
});

it('leaves out inactive categories and their threads for teachers and parents', function () {
    $inactive = ForumCategory::factory()->inactive()->create(['name' => 'Archived Topics']);
    ForumThread::factory()->for($inactive, 'category')->create(['title' => 'Thread in an archived topic']);

    $this->actingAs(User::factory()->parent()->create())
        ->get(route('forum.index'))
        ->assertOk()
        ->assertDontSeeText('Archived Topics')
        ->assertDontSeeText('Thread in an archived topic');
});

it('shows administrators inactive categories and their threads', function () {
    $inactive = ForumCategory::factory()->inactive()->create(['name' => 'Archived Topics']);
    ForumThread::factory()->for($inactive, 'category')->create(['title' => 'Thread in an archived topic']);

    $this->actingAs(User::factory()->admin()->create())
        ->get(route('forum.index'))
        ->assertOk()
        ->assertSeeText('Archived Topics')
        ->assertSeeText('Thread in an archived topic');
});

it('counts the threads the reader can see in each category tab', function () {
    $parent = User::factory()->parent()->create();
    $category = ForumCategory::factory()->create(['slug' => 'baon-ideas']);
    ForumThread::factory()->for($category, 'category')->count(2)->create();
    ForumThread::factory()->for($category, 'category')->pending()->for($parent, 'author')->create();
    ForumThread::factory()->for($category, 'category')->hidden()->create();

    $response = $this->actingAs($parent)->get(route('forum.index'));

    $response->assertOk();
    expect($response->viewData('categories')->firstWhere('slug', 'baon-ideas')->threads_count)->toBe(3);
    $response->assertSeeInOrder(['data-forum-category-tab="baon-ideas"', '>3<'], false);
});

it('searches thread titles and messages', function (string $search) {
    ForumThread::factory()->create(['title' => 'Breakfast ideas', 'body' => 'We love champorado with milk.']);
    ForumThread::factory()->create(['title' => 'Soft drinks at home', 'body' => 'How do you say no to soda?']);

    $this->actingAs(User::factory()->parent()->create())
        ->get(route('forum.index', ['search' => $search]))
        ->assertOk()
        ->assertSeeText('Breakfast ideas')
        ->assertDontSeeText('Soft drinks at home');
})->with([
    'title' => 'breakfast',
    'message' => 'champorado',
]);

it('keeps only the reader’s own threads with the "started by me" filter', function () {
    $parent = User::factory()->parent()->create();
    ForumThread::factory()->for($parent, 'author')->create(['title' => 'Thread I started']);
    ForumThread::factory()->create(['title' => 'Thread someone else started']);

    $this->actingAs($parent)
        ->get(route('forum.index', ['show' => 'mine']))
        ->assertOk()
        ->assertSeeText('Thread I started')
        ->assertDontSeeText('Thread someone else started');
});

it('lets administrators list only pending threads', function () {
    ForumThread::factory()->pending()->create(['title' => 'Awaiting review']);
    ForumThread::factory()->create(['title' => 'Already published']);

    $this->actingAs(User::factory()->admin()->create())
        ->get(route('forum.index', ['show' => 'pending']))
        ->assertOk()
        ->assertSeeText('Awaiting review')
        ->assertDontSeeText('Already published');
});

it('ignores status filters a parent may not use', function () {
    ForumThread::factory()->create(['title' => 'Already published']);

    $response = $this->actingAs(User::factory()->parent()->create())->get(route('forum.index', ['show' => 'hidden']));

    $response->assertOk()->assertSeeText('Already published');
    expect($response->viewData('filters'))->toBe(['search' => null, 'show' => null]);
});

it('ignores malformed filter values', function () {
    ForumThread::factory()->create(['title' => 'Already published']);

    $this->actingAs(User::factory()->parent()->create())
        ->get(route('forum.index').'?search[]=x&show[]=mine')
        ->assertOk()
        ->assertSeeText('Already published');
});

it('pages through threads and keeps the filters in the page links', function () {
    ForumThread::factory()->count(16)->create(['title' => 'Snack swap']);

    $response = $this->actingAs(User::factory()->parent()->create())->get(route('forum.index', ['search' => 'snack']));

    $response->assertOk();
    expect($response->viewData('threads'))->total()->toBe(16)->count()->toBe(15);
    $response->assertSee('search=snack&amp;page=2', false);
});

it('renders a page of many threads with a fixed number of queries', function () {
    $viewer = User::factory()->parent()->create();
    ForumThread::factory()->count(2)->create();
    $this->actingAs($viewer)->get(route('forum.index'))->assertOk();
    DB::enableQueryLog();
    $this->actingAs($viewer)->get(route('forum.index'))->assertOk();
    $fewThreadsQueries = count(DB::getQueryLog());
    ForumThread::factory()->count(8)->has(ForumReply::factory()->count(2), 'replies')->create();
    DB::flushQueryLog();

    $this->actingAs($viewer)->get(route('forum.index'))->assertOk();

    expect(count(DB::getQueryLog()))->toBe($fewThreadsQueries);
});

it('escapes thread titles and messages', function () {
    ForumThread::factory()->create(['title' => '<script>alert("title")</script>', 'body' => '<img src=x onerror=alert(1)>']);

    $this->actingAs(User::factory()->parent()->create())
        ->get(route('forum.index'))
        ->assertOk()
        ->assertDontSee('<script>alert("title")</script>', false)
        ->assertDontSee('<img src=x onerror=alert(1)>', false)
        ->assertSee('&lt;script&gt;', false);
});

it('shows the new thread button to members who may post', function () {
    $this->actingAs(User::factory()->parent()->create())
        ->get(route('forum.index'))
        ->assertOk()
        ->assertSee(route('forum.threads.create'), false);
});

it('hides the new thread button when posting is revoked', function () {
    app(RolePermissions::class)->revoke(UserRole::Parent, Permission::PostInForum);

    $this->actingAs(User::factory()->parent()->create())
        ->get(route('forum.index'))
        ->assertOk()
        ->assertDontSee(route('forum.threads.create'), false);
});

it('invites the first thread when the forum is empty', function () {
    ForumCategory::factory()->create();

    $this->actingAs(User::factory()->parent()->create())
        ->get(route('forum.index'))
        ->assertOk()
        ->assertSeeText('No threads yet')
        ->assertSeeText('Start a thread');
});

it('offers to clear the filters when nothing matches', function () {
    ForumThread::factory()->create(['title' => 'Breakfast ideas']);

    $this->actingAs(User::factory()->parent()->create())
        ->get(route('forum.index', ['search' => 'nothing matches this']))
        ->assertOk()
        ->assertSeeText('No threads match your filters')
        ->assertSeeText('Clear filters');
});

it('refuses teachers and parents with a friendly page while the forum is turned off', function (UserRole $role) {
    app(Settings::class)->set('forum_enabled', false);

    $this->actingAs(User::factory()->{$role->value}()->create())
        ->get(route('forum.index'))
        ->assertForbidden()
        ->assertSeeText(ForumThreadPolicy::FORUM_DISABLED_MESSAGE);
})->with([UserRole::Teacher, UserRole::Parent]);

it('keeps the forum open to administrators while it is turned off, with a notice', function () {
    app(Settings::class)->set('forum_enabled', false);

    $this->actingAs(User::factory()->admin()->create())
        ->get(route('forum.index'))
        ->assertOk()
        ->assertSee('data-forum-disabled-notice', false)
        ->assertSee(route('admin.settings.edit'), false);
});

it('forbids a role whose forum access was revoked', function () {
    app(RolePermissions::class)->revoke(UserRole::Teacher, Permission::AccessForum);

    $this->actingAs(User::factory()->teacher()->create())
        ->get(route('forum.index'))
        ->assertForbidden()
        ->assertSeeText(RolePermissions::deniedMessage(Permission::AccessForum));
});
