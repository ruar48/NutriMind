<?php

use App\Enums\ForumContentStatus;
use App\Enums\UserRole;
use App\Models\ForumCategory;
use App\Models\ForumReport;
use App\Models\ForumThread;
use App\Models\User;
use App\Services\Forum\ForumModeration;
use App\Services\Settings;
use Illuminate\Support\Facades\DB;

describe('index', function () {
    it('sends guests to the sign-in page', function () {
        $this->get(route('admin.forum.pending.index'))->assertRedirectToRoute('login');
    });

    it('forbids every role but administrators', function (UserRole $role) {
        $this->actingAs(User::factory()->{$role->value}()->create())
            ->get(route('admin.forum.pending.index'))
            ->assertForbidden();
    })->with([UserRole::Teacher, UserRole::Parent, UserRole::Student]);

    it('lists the pending threads, oldest first, with their author and category', function () {
        $category = ForumCategory::factory()->create(['name' => 'Healthy Recipes']);
        $author = User::factory()->parent()->create(['name' => 'Lorna Cruz']);
        ForumThread::factory()->pending()->for($category, 'category')->for($author, 'author')->create([
            'title' => 'Newer pending thread',
            'body' => 'Waiting for a moderator.',
            'created_at' => now()->subHour(),
        ]);
        ForumThread::factory()->pending()->create(['title' => 'Older pending thread', 'created_at' => now()->subDays(2)]);
        ForumThread::factory()->create(['title' => 'Published thread']);
        ForumThread::factory()->hidden()->create(['title' => 'Hidden thread']);

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.forum.pending.index'))
            ->assertOk()
            ->assertViewIs('admin.forum.pending.index')
            ->assertSeeTextInOrder(['Older pending thread', 'Newer pending thread'])
            ->assertSeeText('Lorna Cruz')
            ->assertSeeText('Healthy Recipes')
            ->assertSeeText('Waiting for a moderator.')
            ->assertDontSeeText('Published thread')
            ->assertDontSeeText('Hidden thread');
    });

    it('offers approve and reject for each pending thread', function () {
        $thread = ForumThread::factory()->pending()->create();

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.forum.pending.index'))
            ->assertOk()
            ->assertSee(route('admin.forum.pending.update', $thread), false)
            ->assertSee('name="decision" value="approve"', false)
            ->assertSee('name="decision" value="reject"', false);
    });

    it('filters by category and searches titles and messages', function (array $query) {
        $category = ForumCategory::factory()->create(['slug' => 'healthy-recipes']);
        ForumThread::factory()->pending()->for($category, 'category')->create(['title' => 'Malunggay soup', 'body' => 'A green soup recipe.']);
        ForumThread::factory()->pending()->create(['title' => 'Water bottles', 'body' => 'Which bottle do you use?']);

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.forum.pending.index', $query))
            ->assertOk()
            ->assertSeeText('Malunggay soup')
            ->assertDontSeeText('Water bottles');
    })->with([
        'category' => [['category' => 'healthy-recipes']],
        'title' => [['search' => 'malunggay']],
        'message' => [['search' => 'green soup']],
    ]);

    it('notes when approval is turned off', function () {
        app(Settings::class)->set('forum_requires_approval', false);

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.forum.pending.index'))
            ->assertOk()
            ->assertSee('data-forum-approval-off', false)
            ->assertSee(route('admin.settings.edit'), false);
    });

    it('does not note anything while approval is required', function () {
        app(Settings::class)->set('forum_requires_approval', true);

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.forum.pending.index'))
            ->assertOk()
            ->assertDontSee('data-forum-approval-off', false);
    });

    it('shows the open and pending counts in the moderation tabs', function () {
        ForumReport::factory()->count(2)->create();
        ForumThread::factory()->pending()->count(3)->create();

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.forum.pending.index'))
            ->assertOk()
            ->assertViewHas('openCount', 2)
            ->assertViewHas('pendingCount', 3);
    });

    it('shows an empty queue', function () {
        $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.forum.pending.index'))
            ->assertOk()
            ->assertSeeText('Nothing is waiting for approval');
    });

    it('offers to clear the filters when nothing matches', function () {
        ForumThread::factory()->pending()->create();

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.forum.pending.index', ['search' => 'nothing matches this']))
            ->assertOk()
            ->assertSeeText('No pending threads match your filters');
    });

    it('escapes pending titles and messages', function () {
        ForumThread::factory()->pending()->create(['title' => '<script>alert("t")</script>', 'body' => "<b>bold</b>\nnext"]);

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.forum.pending.index'))
            ->assertOk()
            ->assertDontSee('<script>alert("t")</script>', false)
            ->assertDontSee('<b>bold</b>', false)
            ->assertSee('&lt;b&gt;bold&lt;/b&gt;<br />', false);
    });

    it('pages through the queue', function () {
        ForumThread::factory()->pending()->count(16)->create();

        $response = $this->actingAs(User::factory()->admin()->create())->get(route('admin.forum.pending.index'));

        $response->assertOk();
        expect($response->viewData('threads'))->total()->toBe(16)->count()->toBe(15);
    });

    it('renders many pending threads with a fixed number of queries', function () {
        $admin = User::factory()->admin()->create();
        ForumThread::factory()->pending()->count(2)->create();
        $this->actingAs($admin)->get(route('admin.forum.pending.index'))->assertOk();
        DB::enableQueryLog();
        $this->actingAs($admin)->get(route('admin.forum.pending.index'))->assertOk();
        $fewThreadsQueries = count(DB::getQueryLog());
        ForumThread::factory()->pending()->count(8)->create();
        DB::flushQueryLog();

        $this->actingAs($admin)->get(route('admin.forum.pending.index'))->assertOk();

        expect(count(DB::getQueryLog()))->toBe($fewThreadsQueries);
    });
});

describe('update', function () {
    it('forbids every role but administrators', function (UserRole $role) {
        $thread = ForumThread::factory()->pending()->create();

        $this->actingAs(User::factory()->{$role->value}()->create())
            ->put(route('admin.forum.pending.update', $thread), ['decision' => 'approve'])
            ->assertForbidden();

        expect($thread->refresh()->status)->toBe(ForumContentStatus::Pending);
    })->with([UserRole::Teacher, UserRole::Parent]);

    it('publishes an approved thread as active now', function () {
        $this->freezeSecond();
        $thread = ForumThread::factory()->pending()->create(['title' => 'Snack ideas', 'last_activity_at' => now()->subDays(2)]);

        $this->actingAs(User::factory()->admin()->create())
            ->from(route('admin.forum.pending.index'))
            ->put(route('admin.forum.pending.update', $thread), ['decision' => 'approve'])
            ->assertRedirectToRoute('admin.forum.pending.index')
            ->assertSessionHas('status', '“Snack ideas” is published.');

        expect($thread->refresh()->status)->toBe(ForumContentStatus::Published);
        expect($thread->last_activity_at->toDateTimeString())->toBe(now()->toDateTimeString());
    });

    it('hides a rejected thread', function () {
        $thread = ForumThread::factory()->pending()->create(['title' => 'Snack ideas']);

        $this->actingAs(User::factory()->admin()->create())
            ->put(route('admin.forum.pending.update', $thread), ['decision' => 'reject'])
            ->assertSessionHas('status', '“Snack ideas” was rejected. Only its author and administrators can see it.');

        expect($thread->refresh()->status)->toBe(ForumContentStatus::Hidden);
    });

    it('returns to the thread page when the decision was made there', function () {
        $thread = ForumThread::factory()->pending()->create();

        $this->actingAs(User::factory()->admin()->create())
            ->from(route('forum.threads.show', $thread))
            ->put(route('admin.forum.pending.update', $thread), ['decision' => 'approve'])
            ->assertRedirect(route('forum.threads.show', $thread));
    });

    it('refuses a thread that is no longer pending', function (string $state, ForumContentStatus $status) {
        $thread = ForumThread::factory()->{$state}()->create();

        $this->actingAs(User::factory()->admin()->create())
            ->put(route('admin.forum.pending.update', $thread), ['decision' => 'approve'])
            ->assertSessionHas('error', ForumModeration::ALREADY_REVIEWED_MESSAGE);

        expect($thread->refresh()->status)->toBe($status);
    })->with([
        'hidden' => ['hidden', ForumContentStatus::Hidden],
        'published' => ['pinned', ForumContentStatus::Published],
    ]);

    it('validates the decision', function (array $input) {
        $thread = ForumThread::factory()->pending()->create();

        $this->actingAs(User::factory()->admin()->create())
            ->put(route('admin.forum.pending.update', $thread), $input)
            ->assertSessionHasErrors(['decision' => 'Choose whether to approve or reject the thread.']);

        expect($thread->refresh()->status)->toBe(ForumContentStatus::Pending);
    })->with([
        'missing' => [[]],
        'unknown' => [['decision' => 'publish']],
    ]);

    it('returns 404 for an unknown thread', function () {
        $this->actingAs(User::factory()->admin()->create())
            ->put(route('admin.forum.pending.update', 999), ['decision' => 'approve'])
            ->assertNotFound();
    });
});
