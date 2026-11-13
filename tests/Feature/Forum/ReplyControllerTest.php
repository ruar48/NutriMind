<?php

use App\Enums\ForumContentStatus;
use App\Enums\Permission;
use App\Enums\UserRole;
use App\Models\ForumReply;
use App\Models\ForumThread;
use App\Models\User;
use App\Policies\ForumReplyPolicy;
use App\Policies\ForumThreadPolicy;
use App\Services\Forum\ForumPosting;
use App\Services\RolePermissions;
use App\Services\Settings;

describe('store', function () {
    it('sends guests to the sign-in page', function () {
        $thread = ForumThread::factory()->create();

        $this->post(route('forum.threads.replies.store', $thread), ['body' => 'Hello there'])->assertRedirectToRoute('login');

        $this->assertDatabaseCount('forum_replies', 0);
    });

    it('forbids learner accounts', function () {
        $thread = ForumThread::factory()->create();

        $this->actingAs(User::factory()->student()->create())
            ->post(route('forum.threads.replies.store', $thread), ['body' => 'Hello there'])
            ->assertForbidden();
    });

    it('posts the reply and marks the thread as active', function (UserRole $role) {
        $this->freezeSecond();
        $thread = ForumThread::factory()->create(['last_activity_at' => now()->subWeek()]);
        $member = User::factory()->{$role->value}()->create();

        $response = $this->actingAs($member)->post(route('forum.threads.replies.store', $thread), [
            'body' => 'We add malunggay to our tinola every week.',
        ]);

        $reply = ForumReply::query()->sole();
        $response->assertRedirect(route('forum.threads.show', $thread).'#reply-'.$reply->id)
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status', 'Your reply is posted.');
        expect($reply)
            ->author_id->toBe($member->id)
            ->forum_thread_id->toBe($thread->id)
            ->body->toBe('We add malunggay to our tinola every week.')
            ->status->toBe(ForumContentStatus::Published);
        expect($thread->refresh()->last_activity_at->toDateTimeString())->toBe(now()->toDateTimeString());
    })->with([UserRole::Admin, UserRole::Teacher, UserRole::Parent]);

    it('links to the last page when the reply lands there', function () {
        $thread = ForumThread::factory()->create();
        ForumReply::factory()->for($thread, 'thread')->count(ForumPosting::REPLIES_PER_PAGE)->create(['created_at' => now()->subHour()]);

        $response = $this->actingAs(User::factory()->parent()->create())
            ->post(route('forum.threads.replies.store', $thread), ['body' => 'Twenty-first reply']);

        $reply = ForumReply::query()->where('body', 'Twenty-first reply')->sole();
        $response->assertRedirect(route('forum.threads.show', ['thread' => $thread, 'page' => 2]).'#reply-'.$reply->id);
    });

    it('ignores fields the author may not set', function () {
        $thread = ForumThread::factory()->create();
        $parent = User::factory()->parent()->create();

        $this->actingAs($parent)->post(route('forum.threads.replies.store', $thread), [
            'body' => 'A reply with extra fields',
            'status' => 'hidden',
            'author_id' => User::factory()->parent()->create()->id,
            'forum_thread_id' => ForumThread::factory()->create()->id,
        ]);

        expect(ForumReply::query()->sole())
            ->author_id->toBe($parent->id)
            ->forum_thread_id->toBe($thread->id)
            ->status->toBe(ForumContentStatus::Published);
    });

    it('rejects a reply of the wrong length', function (array $input, string $message) {
        $thread = ForumThread::factory()->create();

        $this->actingAs(User::factory()->parent()->create())
            ->post(route('forum.threads.replies.store', $thread), $input)
            ->assertSessionHasErrors(['body' => $message]);

        $this->assertDatabaseCount('forum_replies', 0);
    })->with([
        'missing' => [[], 'The reply field is required.'],
        'too short' => [['body' => 'a'], 'The reply field must be at least 2 characters.'],
        'too long' => [['body' => str_repeat('a', 3001)], 'The reply field must not be greater than 3000 characters.'],
    ]);

    it('accepts replies at the length limits', function (int $length) {
        $thread = ForumThread::factory()->create();

        $this->actingAs(User::factory()->parent()->create())
            ->post(route('forum.threads.replies.store', $thread), ['body' => str_repeat('a', $length)])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseCount('forum_replies', 1);
    })->with([2, 3000]);

    it('refuses replies to a locked thread', function () {
        $thread = ForumThread::factory()->locked()->create();

        $this->actingAs(User::factory()->parent()->create())
            ->post(route('forum.threads.replies.store', $thread), ['body' => 'Can I still reply?'])
            ->assertForbidden()
            ->assertSeeText(ForumThreadPolicy::LOCKED_MESSAGE);

        $this->assertDatabaseCount('forum_replies', 0);
    });

    it('lets administrators reply to a locked thread', function () {
        $thread = ForumThread::factory()->locked()->create();

        $this->actingAs(User::factory()->admin()->create())
            ->post(route('forum.threads.replies.store', $thread), ['body' => 'Closing note from the school.'])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseCount('forum_replies', 1);
    });

    it('returns 404 for someone else’s pending or hidden thread', function (string $state) {
        $thread = ForumThread::factory()->{$state}()->create();

        $this->actingAs(User::factory()->parent()->create())
            ->post(route('forum.threads.replies.store', $thread), ['body' => 'Hello there'])
            ->assertNotFound();

        $this->assertDatabaseCount('forum_replies', 0);
    })->with(['pending', 'hidden']);

    it('keeps the author from replying to their own unpublished thread', function (string $state) {
        $parent = User::factory()->parent()->create();
        $thread = ForumThread::factory()->{$state}()->for($parent, 'author')->create();

        $this->actingAs($parent)
            ->post(route('forum.threads.replies.store', $thread), ['body' => 'Adding a detail'])
            ->assertRedirectToRoute('forum.threads.show', $thread)
            ->assertSessionHas('error', ForumPosting::UNPUBLISHED_THREAD_MESSAGE);

        $this->assertDatabaseCount('forum_replies', 0);
    })->with(['pending', 'hidden']);

    it('lets administrators reply to an unpublished thread', function () {
        $thread = ForumThread::factory()->pending()->create();

        $this->actingAs(User::factory()->admin()->create())
            ->post(route('forum.threads.replies.store', $thread), ['body' => 'Moderator note'])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseCount('forum_replies', 1);
    });

    it('forbids members whose posting permission was revoked', function () {
        $thread = ForumThread::factory()->create();
        app(RolePermissions::class)->revoke(UserRole::Parent, Permission::PostInForum);

        $this->actingAs(User::factory()->parent()->create())
            ->post(route('forum.threads.replies.store', $thread), ['body' => 'Hello there'])
            ->assertForbidden()
            ->assertSeeText(RolePermissions::deniedMessage(Permission::PostInForum));

        $this->assertDatabaseCount('forum_replies', 0);
    });

    it('refuses teachers and parents while the forum is turned off', function () {
        $thread = ForumThread::factory()->create();
        app(Settings::class)->set('forum_enabled', false);

        $this->actingAs(User::factory()->teacher()->create())
            ->post(route('forum.threads.replies.store', $thread), ['body' => 'Hello there'])
            ->assertForbidden();

        $this->assertDatabaseCount('forum_replies', 0);
    });
});

describe('edit', function () {
    it('shows the author the edit form with the reply', function () {
        $parent = User::factory()->parent()->create();
        $reply = ForumReply::factory()->for($parent, 'author')->create(['body' => 'My original reply']);

        $this->actingAs($parent)
            ->get(route('forum.replies.edit', $reply))
            ->assertOk()
            ->assertViewIs('forum.replies.edit')
            ->assertSeeText('My original reply')
            ->assertViewHas('replyUrl', route('forum.threads.show', $reply->forum_thread_id).'#reply-'.$reply->id);
    });

    it('forbids other members who can see the reply', function (UserRole $role) {
        $reply = ForumReply::factory()->create();

        $this->actingAs(User::factory()->{$role->value}()->create())
            ->get(route('forum.replies.edit', $reply))
            ->assertForbidden()
            ->assertSeeText(ForumReplyPolicy::NOT_AUTHOR_MESSAGE);
    })->with([UserRole::Admin, UserRole::Teacher, UserRole::Parent]);

    it('returns 404 for a reply in a thread the member cannot see', function () {
        $thread = ForumThread::factory()->hidden()->create();
        $reply = ForumReply::factory()->for($thread, 'thread')->create();

        $this->actingAs(User::factory()->parent()->create())
            ->get(route('forum.replies.edit', $reply))
            ->assertNotFound();
    });

    it('refuses the author while the thread is locked', function () {
        $parent = User::factory()->parent()->create();
        $thread = ForumThread::factory()->locked()->create();
        $reply = ForumReply::factory()->for($thread, 'thread')->for($parent, 'author')->create();

        $this->actingAs($parent)
            ->get(route('forum.replies.edit', $reply))
            ->assertForbidden()
            ->assertSeeText(ForumThreadPolicy::LOCKED_MESSAGE);
    });

    it('refuses the author while the forum is turned off', function () {
        $parent = User::factory()->parent()->create();
        $reply = ForumReply::factory()->for($parent, 'author')->create();
        app(Settings::class)->set('forum_enabled', false);

        $this->actingAs($parent)
            ->get(route('forum.replies.edit', $reply))
            ->assertForbidden()
            ->assertSeeText(ForumThreadPolicy::FORUM_DISABLED_MESSAGE);
    });
});

describe('update', function () {
    it('saves the author’s changes and returns to the reply', function () {
        $parent = User::factory()->parent()->create();
        $reply = ForumReply::factory()->for($parent, 'author')->create();

        $this->actingAs($parent)
            ->put(route('forum.replies.update', $reply), ['body' => 'Corrected reply text'])
            ->assertRedirect(route('forum.threads.show', $reply->forum_thread_id).'#reply-'.$reply->id)
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status', 'Your reply was updated.');

        expect($reply->refresh()->body)->toBe('Corrected reply text');
    });

    it('validates the reply', function () {
        $parent = User::factory()->parent()->create();
        $reply = ForumReply::factory()->for($parent, 'author')->create(['body' => 'Original reply']);

        $this->actingAs($parent)
            ->put(route('forum.replies.update', $reply), ['body' => str_repeat('a', 3001)])
            ->assertSessionHasErrors(['body' => 'The reply field must not be greater than 3000 characters.']);

        expect($reply->refresh()->body)->toBe('Original reply');
    });

    it('ignores the status in the payload', function () {
        $parent = User::factory()->parent()->create();
        $reply = ForumReply::factory()->hidden()->for($parent, 'author')->create();

        $this->actingAs($parent)->put(route('forum.replies.update', $reply), ['body' => 'Corrected reply text', 'status' => 'published']);

        expect($reply->refresh()->status)->toBe(ForumContentStatus::Hidden);
    });

    it('forbids a member who is not the author, before validating', function () {
        $reply = ForumReply::factory()->create(['body' => 'Original reply']);

        $this->actingAs(User::factory()->parent()->create())
            ->put(route('forum.replies.update', $reply), [])
            ->assertForbidden()
            ->assertSessionHasNoErrors();

        expect($reply->refresh()->body)->toBe('Original reply');
    });

    it('refuses changes while the thread is locked', function () {
        $parent = User::factory()->parent()->create();
        $thread = ForumThread::factory()->locked()->create();
        $reply = ForumReply::factory()->for($thread, 'thread')->for($parent, 'author')->create(['body' => 'Original reply']);

        $this->actingAs($parent)
            ->put(route('forum.replies.update', $reply), ['body' => 'Corrected reply text'])
            ->assertForbidden();

        expect($reply->refresh()->body)->toBe('Original reply');
    });
});

describe('destroy', function () {
    it('lets the author delete the reply', function () {
        $parent = User::factory()->parent()->create();
        $reply = ForumReply::factory()->for($parent, 'author')->create();

        $this->actingAs($parent)
            ->delete(route('forum.replies.destroy', $reply))
            ->assertRedirect(route('forum.threads.show', $reply->forum_thread_id).'#replies')
            ->assertSessionHas('status', 'The reply was deleted.');

        $this->assertModelMissing($reply);
    });

    it('lets administrators delete anyone’s reply, even on a locked thread', function () {
        $thread = ForumThread::factory()->locked()->create();
        $reply = ForumReply::factory()->for($thread, 'thread')->create();

        $this->actingAs(User::factory()->admin()->create())
            ->delete(route('forum.replies.destroy', $reply))
            ->assertSessionHas('status', 'The reply was deleted.');

        $this->assertModelMissing($reply);
    });

    it('returns to the page the reply was on, or the last page when it emptied', function () {
        $thread = ForumThread::factory()->create();
        $replies = ForumReply::factory()->for($thread, 'thread')->count(ForumPosting::REPLIES_PER_PAGE + 2)
            ->sequence(fn ($sequence) => ['created_at' => now()->subMinutes(100 - $sequence->index)])
            ->create();
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->delete(route('forum.replies.destroy', $replies[21]))
            ->assertRedirect(route('forum.threads.show', ['thread' => $thread, 'page' => 2]).'#replies');
        $this->actingAs($admin)
            ->delete(route('forum.replies.destroy', $replies[20]))
            ->assertRedirect(route('forum.threads.show', $thread).'#replies');
    });

    it('forbids other members who can see the reply', function () {
        $reply = ForumReply::factory()->create();

        $this->actingAs(User::factory()->teacher()->create())
            ->delete(route('forum.replies.destroy', $reply))
            ->assertForbidden()
            ->assertSeeText(ForumReplyPolicy::CANNOT_DELETE_MESSAGE);

        $this->assertModelExists($reply);
    });

    it('returns 404 for a reply in a thread the member cannot see', function () {
        $thread = ForumThread::factory()->pending()->create();
        $reply = ForumReply::factory()->for($thread, 'thread')->create();

        $this->actingAs(User::factory()->parent()->create())
            ->delete(route('forum.replies.destroy', $reply))
            ->assertNotFound();

        $this->assertModelExists($reply);
    });
});

it('shows authors the edit and delete actions on their own replies only', function () {
    $parent = User::factory()->parent()->create();
    $thread = ForumThread::factory()->create();
    $own = ForumReply::factory()->for($thread, 'thread')->for($parent, 'author')->create();
    $other = ForumReply::factory()->for($thread, 'thread')->create();

    $this->actingAs($parent)
        ->get(route('forum.threads.show', $thread))
        ->assertOk()
        ->assertSee(route('forum.replies.edit', $own), false)
        ->assertSee(route('forum.replies.destroy', $own), false)
        ->assertDontSee(route('forum.replies.edit', $other), false)
        ->assertDontSee(route('forum.replies.destroy', $other), false)
        ->assertSee('data-forum-report-form="reply-'.$other->id.'"', false)
        ->assertDontSee('data-forum-report-form="reply-'.$own->id.'"', false);
});
