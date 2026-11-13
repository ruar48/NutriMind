<?php

use App\Enums\ForumContentStatus;
use App\Enums\Permission;
use App\Enums\UserRole;
use App\Models\ForumCategory;
use App\Models\ForumReply;
use App\Models\ForumReport;
use App\Models\ForumThread;
use App\Models\User;
use App\Policies\ForumThreadPolicy;
use App\Services\Forum\ForumPosting;
use App\Services\RolePermissions;
use App\Services\Settings;
use Illuminate\Support\Facades\DB;

describe('create', function () {
    it('sends guests to the sign-in page', function () {
        $this->get(route('forum.threads.create'))->assertRedirectToRoute('login');
    });

    it('forbids learner accounts', function () {
        $this->actingAs(User::factory()->student()->create())
            ->get(route('forum.threads.create'))
            ->assertForbidden();
    });

    it('offers only the active categories', function (UserRole $role) {
        ForumCategory::factory()->create(['name' => 'Healthy Recipes']);
        ForumCategory::factory()->inactive()->create(['name' => 'Archived Topics']);

        $response = $this->actingAs(User::factory()->{$role->value}()->create())->get(route('forum.threads.create'));

        $response->assertOk()
            ->assertViewIs('forum.threads.create')
            ->assertSeeText('Healthy Recipes')
            ->assertDontSeeText('Archived Topics');
    })->with([UserRole::Admin, UserRole::Teacher, UserRole::Parent]);

    it('preselects the category given in the link', function () {
        $category = ForumCategory::factory()->create(['slug' => 'healthy-recipes']);

        $this->actingAs(User::factory()->parent()->create())
            ->get(route('forum.threads.create', ['category' => 'healthy-recipes']))
            ->assertOk()
            ->assertSee('<option value="'.$category->id.'" selected', false);
    });

    it('tells teachers and parents when threads wait for approval', function () {
        ForumCategory::factory()->create();
        app(Settings::class)->set('forum_requires_approval', true);

        $this->actingAs(User::factory()->parent()->create())
            ->get(route('forum.threads.create'))
            ->assertOk()
            ->assertSeeText('Threads are reviewed first');
    });

    it('does not mention approval to administrators', function () {
        ForumCategory::factory()->create();
        app(Settings::class)->set('forum_requires_approval', true);

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('forum.threads.create'))
            ->assertOk()
            ->assertDontSeeText('Threads are reviewed first');
    });

    it('explains when no category is open', function () {
        ForumCategory::factory()->inactive()->create();

        $this->actingAs(User::factory()->parent()->create())
            ->get(route('forum.threads.create'))
            ->assertOk()
            ->assertSeeText('No open categories');
    });

    it('forbids members whose posting permission was revoked', function () {
        app(RolePermissions::class)->revoke(UserRole::Parent, Permission::PostInForum);

        $this->actingAs(User::factory()->parent()->create())
            ->get(route('forum.threads.create'))
            ->assertForbidden()
            ->assertSeeText(RolePermissions::deniedMessage(Permission::PostInForum));
    });

    it('refuses teachers and parents while the forum is turned off', function () {
        app(Settings::class)->set('forum_enabled', false);

        $this->actingAs(User::factory()->teacher()->create())
            ->get(route('forum.threads.create'))
            ->assertForbidden()
            ->assertSeeText(ForumThreadPolicy::FORUM_DISABLED_MESSAGE);
    });
});

describe('store', function () {
    it('publishes the thread right away when no approval is required', function () {
        $this->freezeSecond();
        $category = ForumCategory::factory()->create();
        $parent = User::factory()->parent()->create();

        $response = $this->actingAs($parent)->post(route('forum.threads.store'), [
            'forum_category_id' => $category->id,
            'title' => 'Healthy merienda ideas',
            'body' => 'What do you pack for merienda? We like boiled saba and peanuts.',
        ]);

        $thread = ForumThread::query()->sole();
        $response->assertRedirectToRoute('forum.threads.show', $thread)
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status', 'Your thread is posted.');
        expect($thread)
            ->author_id->toBe($parent->id)
            ->forum_category_id->toBe($category->id)
            ->title->toBe('Healthy merienda ideas')
            ->status->toBe(ForumContentStatus::Published)
            ->is_pinned->toBeFalse()
            ->is_locked->toBeFalse();
        expect($thread->last_activity_at->toDateTimeString())->toBe(now()->toDateTimeString());
    });

    it('holds threads by teachers and parents for approval when the setting is on', function (UserRole $role) {
        $category = ForumCategory::factory()->create();
        app(Settings::class)->set('forum_requires_approval', true);

        $this->actingAs(User::factory()->{$role->value}()->create())
            ->post(route('forum.threads.store'), [
                'forum_category_id' => $category->id,
                'title' => 'Healthy merienda ideas',
                'body' => 'What do you pack for merienda? We like boiled saba.',
            ])
            ->assertSessionHas('status', 'Submitted for approval. A moderator will review your thread before it appears in the forum.');

        expect(ForumThread::query()->sole()->status)->toBe(ForumContentStatus::Pending);
    })->with([UserRole::Teacher, UserRole::Parent]);

    it('publishes administrators’ threads even when approval is required', function () {
        $category = ForumCategory::factory()->create();
        app(Settings::class)->set('forum_requires_approval', true);

        $this->actingAs(User::factory()->admin()->create())
            ->post(route('forum.threads.store'), [
                'forum_category_id' => $category->id,
                'title' => 'Parent-teacher conference',
                'body' => 'Our conference is on Saturday morning at the covered court.',
            ])
            ->assertSessionHas('status', 'Your thread is posted.');

        expect(ForumThread::query()->sole()->status)->toBe(ForumContentStatus::Published);
    });

    it('ignores fields the author may not set', function () {
        $category = ForumCategory::factory()->create();
        $parent = User::factory()->parent()->create();
        $someoneElse = User::factory()->parent()->create();
        app(Settings::class)->set('forum_requires_approval', true);

        $this->actingAs($parent)->post(route('forum.threads.store'), [
            'forum_category_id' => $category->id,
            'title' => 'Healthy merienda ideas',
            'body' => 'What do you pack for merienda? We like boiled saba.',
            'status' => 'published',
            'is_pinned' => '1',
            'is_locked' => '1',
            'author_id' => $someoneElse->id,
        ]);

        expect(ForumThread::query()->sole())
            ->author_id->toBe($parent->id)
            ->status->toBe(ForumContentStatus::Pending)
            ->is_pinned->toBeFalse()
            ->is_locked->toBeFalse();
    });

    it('requires a category, a title and a message', function () {
        $this->actingAs(User::factory()->parent()->create())
            ->post(route('forum.threads.store'), [])
            ->assertSessionHasErrors([
                'forum_category_id' => 'The category field is required.',
                'title' => 'The title field is required.',
                'body' => 'The message field is required.',
            ]);

        $this->assertDatabaseCount('forum_threads', 0);
    });

    it('rejects a title or message of the wrong length', function (array $input, string $field, string $message) {
        $category = ForumCategory::factory()->create();

        $this->actingAs(User::factory()->parent()->create())
            ->post(route('forum.threads.store'), [
                'forum_category_id' => $category->id,
                'title' => 'A valid thread title',
                'body' => 'A message that is long enough.',
                ...$input,
            ])
            ->assertSessionHasErrors([$field => $message]);

        $this->assertDatabaseCount('forum_threads', 0);
    })->with([
        'title too short' => [['title' => 'Hi!'], 'title', 'The title field must be at least 5 characters.'],
        'title too long' => [['title' => str_repeat('a', 151)], 'title', 'The title field must not be greater than 150 characters.'],
        'message too short' => [['body' => 'Too short'], 'body', 'The message field must be at least 10 characters.'],
        'message too long' => [['body' => str_repeat('a', 5001)], 'body', 'The message field must not be greater than 5000 characters.'],
    ]);

    it('accepts a title and message at their length limits', function () {
        $category = ForumCategory::factory()->create();

        $this->actingAs(User::factory()->parent()->create())
            ->post(route('forum.threads.store'), [
                'forum_category_id' => $category->id,
                'title' => str_repeat('a', 150),
                'body' => str_repeat('b', 5000),
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseCount('forum_threads', 1);
    });

    it('rejects an inactive or unknown category', function (Closure $categoryId) {
        $this->actingAs(User::factory()->parent()->create())
            ->post(route('forum.threads.store'), [
                'forum_category_id' => $categoryId(),
                'title' => 'Healthy merienda ideas',
                'body' => 'What do you pack for merienda?',
            ])
            ->assertSessionHasErrors(['forum_category_id' => 'Choose one of the open categories.']);

        $this->assertDatabaseCount('forum_threads', 0);
    })->with([
        'inactive' => fn (): int => ForumCategory::factory()->inactive()->create()->id,
        'unknown' => fn (): int => 999,
    ]);

    it('forbids members whose posting permission was revoked', function () {
        $category = ForumCategory::factory()->create();
        app(RolePermissions::class)->revoke(UserRole::Teacher, Permission::PostInForum);

        $this->actingAs(User::factory()->teacher()->create())
            ->post(route('forum.threads.store'), [
                'forum_category_id' => $category->id,
                'title' => 'Healthy merienda ideas',
                'body' => 'What do you pack for merienda?',
            ])
            ->assertForbidden();

        $this->assertDatabaseCount('forum_threads', 0);
    });
});

describe('show', function () {
    it('sends guests to the sign-in page', function () {
        $thread = ForumThread::factory()->create();

        $this->get(route('forum.threads.show', $thread))->assertRedirectToRoute('login');
    });

    it('forbids learner accounts', function () {
        $thread = ForumThread::factory()->create();

        $this->actingAs(User::factory()->student()->create())
            ->get(route('forum.threads.show', $thread))
            ->assertForbidden();
    });

    it('shows the thread with its author, category and replies oldest first', function (UserRole $role) {
        $category = ForumCategory::factory()->create(['name' => 'Healthy Recipes']);
        $thread = ForumThread::factory()->for($category, 'category')->create([
            'title' => 'Malunggay recipes',
            'body' => 'Share your favorite malunggay dish.',
        ]);
        ForumReply::factory()->for($thread, 'thread')->create(['body' => 'Second reply body', 'created_at' => now()->subHour()]);
        ForumReply::factory()->for($thread, 'thread')->create(['body' => 'First reply body', 'created_at' => now()->subDay()]);

        $this->actingAs(User::factory()->{$role->value}()->create())
            ->get(route('forum.threads.show', $thread))
            ->assertOk()
            ->assertViewIs('forum.threads.show')
            ->assertSeeText('Malunggay recipes')
            ->assertSeeText('Share your favorite malunggay dish.')
            ->assertSeeText('Healthy Recipes')
            ->assertSeeText($thread->author->name)
            ->assertSeeText('2 replies')
            ->assertSeeTextInOrder(['First reply body', 'Second reply body']);
    })->with([UserRole::Admin, UserRole::Teacher, UserRole::Parent]);

    it('escapes the title and body and keeps the line breaks', function () {
        $thread = ForumThread::factory()->create([
            'title' => '<script>alert("title")</script>',
            'body' => "First line\n<b onclick=\"steal()\">bold</b>",
        ]);
        ForumReply::factory()->for($thread, 'thread')->create(['body' => "<script>alert('reply')</script>\nSecond line"]);

        $this->actingAs(User::factory()->parent()->create())
            ->get(route('forum.threads.show', $thread))
            ->assertOk()
            ->assertDontSee('<script>alert("title")</script>', false)
            ->assertDontSee('<b onclick="steal()">', false)
            ->assertDontSee("<script>alert('reply')</script>", false)
            ->assertSee('First line<br />', false)
            ->assertSee('&lt;b onclick=&quot;steal()&quot;&gt;bold&lt;/b&gt;', false)
            ->assertSee('&lt;script&gt;alert(&#039;reply&#039;)&lt;/script&gt;<br />', false);
    });

    it('shows a hidden reply as a moderator notice to teachers and parents', function (UserRole $role) {
        $thread = ForumThread::factory()->create();
        ForumReply::factory()->for($thread, 'thread')->hidden()->create(['body' => 'Rude words here']);

        $this->actingAs(User::factory()->{$role->value}()->create())
            ->get(route('forum.threads.show', $thread))
            ->assertOk()
            ->assertSeeText('This reply was hidden by a moderator.')
            ->assertDontSeeText('Rude words here');
    })->with([UserRole::Teacher, UserRole::Parent]);

    it('shows the full hidden reply to administrators', function () {
        $thread = ForumThread::factory()->create();
        ForumReply::factory()->for($thread, 'thread')->hidden()->create(['body' => 'Rude words here']);

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('forum.threads.show', $thread))
            ->assertOk()
            ->assertSeeText('Rude words here')
            ->assertDontSeeText('This reply was hidden by a moderator.')
            ->assertSee('data-forum-reply-status="hidden"', false);
    });

    it('returns 404 for someone else’s pending or hidden thread', function (string $state) {
        $thread = ForumThread::factory()->{$state}()->create();

        $this->actingAs(User::factory()->parent()->create())
            ->get(route('forum.threads.show', $thread))
            ->assertNotFound();
    })->with(['pending', 'hidden']);

    it('shows the author their pending thread with a notice and no reply form', function () {
        $parent = User::factory()->parent()->create();
        $thread = ForumThread::factory()->pending()->for($parent, 'author')->create();

        $this->actingAs($parent)
            ->get(route('forum.threads.show', $thread))
            ->assertOk()
            ->assertSee('data-forum-status-notice="pending"', false)
            ->assertSeeText(ForumPosting::UNPUBLISHED_THREAD_MESSAGE)
            ->assertDontSee(route('forum.threads.replies.store', $thread), false);
    });

    it('shows the author their hidden thread with a notice', function () {
        $parent = User::factory()->parent()->create();
        $thread = ForumThread::factory()->hidden()->for($parent, 'author')->create();

        $this->actingAs($parent)
            ->get(route('forum.threads.show', $thread))
            ->assertOk()
            ->assertSee('data-forum-status-notice="hidden"', false)
            ->assertSeeText('Only you and the moderators can see this thread.');
    });

    it('shows the reply form on an open thread', function () {
        $thread = ForumThread::factory()->create();

        $this->actingAs(User::factory()->parent()->create())
            ->get(route('forum.threads.show', $thread))
            ->assertOk()
            ->assertSee(route('forum.threads.replies.store', $thread), false);
    });

    it('replaces the reply form with a notice on a locked thread', function () {
        $thread = ForumThread::factory()->locked()->create();

        $this->actingAs(User::factory()->parent()->create())
            ->get(route('forum.threads.show', $thread))
            ->assertOk()
            ->assertSee('data-forum-reply-notice', false)
            ->assertSeeText(ForumThreadPolicy::LOCKED_MESSAGE)
            ->assertDontSee(route('forum.threads.replies.store', $thread), false);
    });

    it('keeps the reply form for administrators on a locked thread', function () {
        $thread = ForumThread::factory()->locked()->create();

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('forum.threads.show', $thread))
            ->assertOk()
            ->assertSee(route('forum.threads.replies.store', $thread), false);
    });

    it('explains why a member without the posting permission cannot reply', function () {
        $thread = ForumThread::factory()->create();
        app(RolePermissions::class)->revoke(UserRole::Parent, Permission::PostInForum);

        $this->actingAs(User::factory()->parent()->create())
            ->get(route('forum.threads.show', $thread))
            ->assertOk()
            ->assertSeeText(RolePermissions::deniedMessage(Permission::PostInForum))
            ->assertDontSee(route('forum.threads.replies.store', $thread), false);
    });

    it('offers the report dialog to readers who are not the author', function () {
        $thread = ForumThread::factory()->create();

        $this->actingAs(User::factory()->parent()->create())
            ->get(route('forum.threads.show', $thread))
            ->assertOk()
            ->assertSee('data-forum-report-form="thread"', false)
            ->assertSee(route('forum.reports.store'), false);
    });

    it('does not offer the author a report button for their own thread', function () {
        $parent = User::factory()->parent()->create();
        $thread = ForumThread::factory()->for($parent, 'author')->create();

        $this->actingAs($parent)
            ->get(route('forum.threads.show', $thread))
            ->assertOk()
            ->assertDontSee('data-forum-report-form="thread"', false);
    });

    it('shows that the reader already reported the thread', function () {
        $parent = User::factory()->parent()->create();
        $thread = ForumThread::factory()->create();
        ForumReport::factory()->for($thread, 'thread')->for($parent, 'reporter')->create();

        $this->actingAs($parent)
            ->get(route('forum.threads.show', $thread))
            ->assertOk()
            ->assertSee('data-forum-reported', false)
            ->assertDontSee('data-forum-report-form="thread"', false);
    });

    it('does not offer administrators the report dialog', function () {
        $thread = ForumThread::factory()->create();
        $reply = ForumReply::factory()->for($thread, 'thread')->create();

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('forum.threads.show', $thread))
            ->assertOk()
            ->assertDontSee('data-forum-report-form="thread"', false)
            ->assertDontSee('data-forum-report-form="reply-'.$reply->id.'"', false);
    });

    it('offers the reply actions each reader may use', function () {
        $parent = User::factory()->parent()->create();
        $thread = ForumThread::factory()->create();
        $ownReply = ForumReply::factory()->for($thread, 'thread')->for($parent, 'author')->create();
        $otherReply = ForumReply::factory()->for($thread, 'thread')->create();
        $reportedReply = ForumReply::factory()->for($thread, 'thread')->create();
        ForumReport::factory()->for($reportedReply, 'reply')->for($parent, 'reporter')->create();

        $this->actingAs($parent)
            ->get(route('forum.threads.show', $thread))
            ->assertOk()
            ->assertSee(route('forum.replies.edit', $ownReply), false)
            ->assertDontSee(route('forum.replies.edit', $otherReply), false)
            ->assertDontSee('data-forum-report-form="reply-'.$ownReply->id.'"', false)
            ->assertSee('data-forum-report-form="reply-'.$otherReply->id.'"', false)
            ->assertDontSee('data-forum-report-form="reply-'.$reportedReply->id.'"', false)
            ->assertSeeText('You reported this reply')
            ->assertDontSee(route('forum.replies.moderation.update', $otherReply), false);
    });

    it('offers administrators the reply moderator tools', function () {
        $thread = ForumThread::factory()->create();
        $reply = ForumReply::factory()->for($thread, 'thread')->create();
        $hiddenReply = ForumReply::factory()->for($thread, 'thread')->hidden()->create();

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('forum.threads.show', $thread))
            ->assertOk()
            ->assertSee(route('forum.replies.moderation.update', $reply), false)
            ->assertSee(route('forum.replies.moderation.update', $hiddenReply), false)
            ->assertSee('name="action" value="hide"', false)
            ->assertSee('name="action" value="unhide"', false);
    });

    it('shows the moderator tools only to administrators', function () {
        $thread = ForumThread::factory()->create();

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('forum.threads.show', $thread))
            ->assertOk()
            ->assertSee('data-forum-moderation-tools', false)
            ->assertSee(route('forum.threads.moderation.update', $thread), false);
        $this->actingAs(User::factory()->parent()->create())
            ->get(route('forum.threads.show', $thread))
            ->assertOk()
            ->assertDontSee('data-forum-moderation-tools', false);
    });

    it('offers administrators approval of a pending thread', function () {
        $thread = ForumThread::factory()->pending()->create();

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('forum.threads.show', $thread))
            ->assertOk()
            ->assertSee(route('admin.forum.pending.update', $thread), false);
    });

    it('counts the open reports for administrators', function () {
        $thread = ForumThread::factory()->create();
        $reply = ForumReply::factory()->for($thread, 'thread')->create();
        ForumReport::factory()->for($thread, 'thread')->count(2)->create();
        ForumReport::factory()->for($thread, 'thread')->for($reply, 'reply')->create();
        ForumReport::factory()->for($thread, 'thread')->resolved()->create();

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('forum.threads.show', $thread))
            ->assertOk()
            ->assertViewHas('threadOpenReports', 2)
            ->assertViewHas('replyOpenReports', [$reply->id => 1])
            ->assertSee('data-forum-open-reports="2"', false)
            ->assertSee('data-forum-open-reports="1"', false);
    });

    it('jumps to the page of a reply', function () {
        $thread = ForumThread::factory()->create();
        $replies = ForumReply::factory()->for($thread, 'thread')->count(25)
            ->sequence(fn ($sequence) => ['created_at' => now()->subMinutes(100 - $sequence->index)])
            ->create();

        $this->actingAs(User::factory()->parent()->create())
            ->get(route('forum.threads.show', ['thread' => $thread, 'reply' => $replies[21]->id]))
            ->assertRedirect(route('forum.threads.show', ['thread' => $thread, 'page' => 2]).'#reply-'.$replies[21]->id);
    });

    it('ignores a reply of another thread in the jump link', function () {
        $thread = ForumThread::factory()->create();
        $otherReply = ForumReply::factory()->create();

        $this->actingAs(User::factory()->parent()->create())
            ->get(route('forum.threads.show', ['thread' => $thread, 'reply' => $otherReply->id]))
            ->assertOk();
    });

    it('pages through the replies twenty at a time', function () {
        $thread = ForumThread::factory()->create();
        ForumReply::factory()->for($thread, 'thread')->count(21)->create();

        $response = $this->actingAs(User::factory()->parent()->create())->get(route('forum.threads.show', [$thread, 'page' => 2]));

        $response->assertOk();
        expect($response->viewData('replies'))->total()->toBe(21)->count()->toBe(1);
    });

    it('renders many replies with a fixed number of queries', function () {
        $viewer = User::factory()->parent()->create();
        $thread = ForumThread::factory()->create();
        ForumReply::factory()->for($thread, 'thread')->count(2)->create();
        $this->actingAs($viewer)->get(route('forum.threads.show', $thread))->assertOk();
        DB::enableQueryLog();
        $this->actingAs($viewer)->get(route('forum.threads.show', $thread))->assertOk();
        $fewRepliesQueries = count(DB::getQueryLog());
        ForumReply::factory()->for($thread, 'thread')->count(10)->create();
        ForumReply::factory()->for($thread, 'thread')->hidden()->count(3)->create();
        DB::flushQueryLog();

        $this->actingAs($viewer)->get(route('forum.threads.show', $thread))->assertOk();

        expect(count(DB::getQueryLog()))->toBe($fewRepliesQueries);
    });

    it('refuses teachers and parents while the forum is turned off', function () {
        $thread = ForumThread::factory()->create();
        app(Settings::class)->set('forum_enabled', false);

        $this->actingAs(User::factory()->parent()->create())
            ->get(route('forum.threads.show', $thread))
            ->assertForbidden()
            ->assertSeeText(ForumThreadPolicy::FORUM_DISABLED_MESSAGE);
    });
});

describe('edit', function () {
    it('shows the author the edit form with the thread’s values', function () {
        $parent = User::factory()->parent()->create();
        $thread = ForumThread::factory()->for($parent, 'author')->create(['title' => 'My original title']);

        $this->actingAs($parent)
            ->get(route('forum.threads.edit', $thread))
            ->assertOk()
            ->assertViewIs('forum.threads.edit')
            ->assertSee('value="My original title"', false);
    });

    it('keeps the thread’s inactive category among the choices', function () {
        $parent = User::factory()->parent()->create();
        $inactive = ForumCategory::factory()->inactive()->create(['name' => 'Archived Topics']);
        ForumCategory::factory()->inactive()->create(['name' => 'Other Archived Topic']);
        $thread = ForumThread::factory()->for($parent, 'author')->for($inactive, 'category')->create();

        $this->actingAs($parent)
            ->get(route('forum.threads.edit', $thread))
            ->assertOk()
            ->assertSeeText('Archived Topics')
            ->assertDontSeeText('Other Archived Topic');
    });

    it('forbids other members who can see the thread', function (UserRole $role) {
        $thread = ForumThread::factory()->create();

        $this->actingAs(User::factory()->{$role->value}()->create())
            ->get(route('forum.threads.edit', $thread))
            ->assertForbidden()
            ->assertSeeText(ForumThreadPolicy::NOT_AUTHOR_MESSAGE);
    })->with([UserRole::Admin, UserRole::Teacher, UserRole::Parent]);

    it('returns 404 for someone else’s pending thread', function () {
        $thread = ForumThread::factory()->pending()->create();

        $this->actingAs(User::factory()->parent()->create())
            ->get(route('forum.threads.edit', $thread))
            ->assertNotFound();
    });

    it('refuses the author of a locked thread', function () {
        $parent = User::factory()->parent()->create();
        $thread = ForumThread::factory()->locked()->for($parent, 'author')->create();

        $this->actingAs($parent)
            ->get(route('forum.threads.edit', $thread))
            ->assertForbidden()
            ->assertSeeText(ForumThreadPolicy::LOCKED_MESSAGE);
    });

    it('refuses the author while the forum is turned off', function () {
        $parent = User::factory()->parent()->create();
        $thread = ForumThread::factory()->for($parent, 'author')->create();
        app(Settings::class)->set('forum_enabled', false);

        $this->actingAs($parent)
            ->get(route('forum.threads.edit', $thread))
            ->assertForbidden()
            ->assertSeeText(ForumThreadPolicy::FORUM_DISABLED_MESSAGE);
    });
});

describe('update', function () {
    it('saves the author’s changes', function () {
        $parent = User::factory()->parent()->create();
        $thread = ForumThread::factory()->for($parent, 'author')->create();
        $category = ForumCategory::factory()->create();

        $this->actingAs($parent)
            ->put(route('forum.threads.update', $thread), [
                'forum_category_id' => $category->id,
                'title' => 'Updated thread title',
                'body' => 'Updated thread message with more details.',
            ])
            ->assertRedirectToRoute('forum.threads.show', $thread)
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status', 'Your thread was updated.');

        expect($thread->refresh())
            ->forum_category_id->toBe($category->id)
            ->title->toBe('Updated thread title')
            ->body->toBe('Updated thread message with more details.');
    });

    it('lets a thread stay in its inactive category', function () {
        $parent = User::factory()->parent()->create();
        $inactive = ForumCategory::factory()->inactive()->create();
        $thread = ForumThread::factory()->for($parent, 'author')->for($inactive, 'category')->create();

        $this->actingAs($parent)
            ->put(route('forum.threads.update', $thread), [
                'forum_category_id' => $inactive->id,
                'title' => 'Updated thread title',
                'body' => 'Updated thread message with more details.',
            ])
            ->assertSessionHasNoErrors();

        expect($thread->refresh()->title)->toBe('Updated thread title');
    });

    it('rejects moving the thread to another inactive category', function () {
        $parent = User::factory()->parent()->create();
        $thread = ForumThread::factory()->for($parent, 'author')->create();
        $inactive = ForumCategory::factory()->inactive()->create();

        $this->actingAs($parent)
            ->put(route('forum.threads.update', $thread), [
                'forum_category_id' => $inactive->id,
                'title' => 'Updated thread title',
                'body' => 'Updated thread message with more details.',
            ])
            ->assertSessionHasErrors(['forum_category_id' => 'Choose one of the open categories.']);

        expect($thread->refresh()->forum_category_id)->not->toBe($inactive->id);
    });

    it('validates the title and the message', function () {
        $parent = User::factory()->parent()->create();
        $thread = ForumThread::factory()->for($parent, 'author')->create(['title' => 'Original title']);

        $this->actingAs($parent)
            ->put(route('forum.threads.update', $thread), [
                'forum_category_id' => $thread->forum_category_id,
                'title' => 'Hey',
                'body' => 'Short',
            ])
            ->assertSessionHasErrors([
                'title' => 'The title field must be at least 5 characters.',
                'body' => 'The message field must be at least 10 characters.',
            ]);

        expect($thread->refresh()->title)->toBe('Original title');
    });

    it('ignores the status and moderation flags in the payload', function () {
        $parent = User::factory()->parent()->create();
        $thread = ForumThread::factory()->pending()->for($parent, 'author')->create();

        $this->actingAs($parent)->put(route('forum.threads.update', $thread), [
            'forum_category_id' => $thread->forum_category_id,
            'title' => 'Updated thread title',
            'body' => 'Updated thread message with more details.',
            'status' => 'published',
            'is_pinned' => '1',
        ]);

        expect($thread->refresh())
            ->status->toBe(ForumContentStatus::Pending)
            ->is_pinned->toBeFalse();
    });

    it('forbids a member who is not the author, before validating', function () {
        $thread = ForumThread::factory()->create(['title' => 'Original title']);

        $this->actingAs(User::factory()->parent()->create())
            ->put(route('forum.threads.update', $thread), [])
            ->assertForbidden()
            ->assertSessionHasNoErrors();

        expect($thread->refresh()->title)->toBe('Original title');
    });

    it('refuses changes to a locked thread', function () {
        $parent = User::factory()->parent()->create();
        $thread = ForumThread::factory()->locked()->for($parent, 'author')->create(['title' => 'Original title']);

        $this->actingAs($parent)
            ->put(route('forum.threads.update', $thread), [
                'forum_category_id' => $thread->forum_category_id,
                'title' => 'Updated thread title',
                'body' => 'Updated thread message with more details.',
            ])
            ->assertForbidden();

        expect($thread->refresh()->title)->toBe('Original title');
    });
});

describe('destroy', function () {
    it('lets the author delete the thread with its replies and reports', function () {
        $parent = User::factory()->parent()->create();
        $thread = ForumThread::factory()->for($parent, 'author')->create(['title' => 'Thread to remove']);
        $reply = ForumReply::factory()->for($thread, 'thread')->create();
        $report = ForumReport::factory()->for($thread, 'thread')->for($reply, 'reply')->create();

        $this->actingAs($parent)
            ->delete(route('forum.threads.destroy', $thread))
            ->assertRedirectToRoute('forum.index')
            ->assertSessionHas('status', '“Thread to remove” was deleted.');

        $this->assertModelMissing($thread);
        $this->assertModelMissing($reply);
        $this->assertModelMissing($report);
    });

    it('lets administrators delete anyone’s thread', function () {
        $thread = ForumThread::factory()->create();

        $this->actingAs(User::factory()->admin()->create())
            ->delete(route('forum.threads.destroy', $thread))
            ->assertRedirectToRoute('forum.index');

        $this->assertModelMissing($thread);
    });

    it('forbids other members who can see the thread', function () {
        $thread = ForumThread::factory()->create();

        $this->actingAs(User::factory()->teacher()->create())
            ->delete(route('forum.threads.destroy', $thread))
            ->assertForbidden()
            ->assertSeeText(ForumThreadPolicy::CANNOT_DELETE_MESSAGE);

        $this->assertModelExists($thread);
    });

    it('returns 404 for someone else’s hidden thread', function () {
        $thread = ForumThread::factory()->hidden()->create();

        $this->actingAs(User::factory()->parent()->create())
            ->delete(route('forum.threads.destroy', $thread))
            ->assertNotFound();

        $this->assertModelExists($thread);
    });
});
