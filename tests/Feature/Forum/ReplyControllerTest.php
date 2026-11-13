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

        $this->post(route('forum.threads.replies.store', $thread), ['body' => 'Thank you!'])->assertRedirectToRoute('login');

        $this->assertDatabaseCount('forum_replies', 0);
    });

    it('forbids learner accounts', function () {
        $thread = ForumThread::factory()->create();

        $this->actingAs(User::factory()->student()->create())
            ->post(route('forum.threads.replies.store', $thread), ['body' => 'Thank you!'])
            ->assertForbidden();
    });

    it('posts the reply, marks the thread active and returns to the reply', function (UserRole $role) {
        $this->freezeSecond();
        $thread = ForumThread::factory()->create(['last_activity_at' => now()->subWeek()]);
        $author = User::factory()->{$role->value}()->create();

        $response = $this->actingAs($author)->post(route('forum.threads.replies.store', $thread), ['body' => 'We pack boiled eggs and bananas.']);

        $reply = ForumReply::query()->sole();
        $response->assertRedirect(route('forum.threads.show', $thread).'#reply-'.$reply->id)
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status', 'Your reply is posted.');
        expect($reply)
            ->author_id->toBe($author->id)
            ->forum_thread_id->toBe($thread->id)
            ->body->toBe('We pack boiled eggs and bananas.')
            ->status->toBe(ForumContentStatus::Published);
        expect($thread->refresh()->last_activity_at->toDateTimeString())->toBe(now()->toDateTimeString());
    })->with([UserRole::Admin, UserRole::Teacher, UserRole::Parent]);

    it('returns to the page that shows the new reply', function () {
        $thread = ForumThread::factory()->create();
        ForumReply::factory()->for($thread, 'thread')->count(ForumPosting::REPLIES_PER_PAGE)->create(['created_at' => now()->subHour()]);

        $response = $this->actingAs(User::factory()->parent()->create())
            ->post(route('forum.threads.replies.store', $thread), ['body' => 'Reply on the second page.']);

        $reply = ForumReply::query()->where('body', 'Reply on the second page.')->sole();
        $response->assertRedirect(route('forum.threads.show', ['thread' => $thread, 'page' => 2]).'#reply-'.$reply->id);
    });

    it('ignores the author, thread and status in the payload', function () {
        $thread = ForumThread::factory()->create();
        $parent = User::factory()->parent()->create();

        $this->actingAs($parent)->post(route('forum.threads.replies.store', $thread), [
            'body' => 'We pack boiled eggs and bananas.',
            'author_id' => User::factory()->parent()->create()->id,
            'status' => 'hidden',
            'forum_thread_id' => ForumThread::factory()->create()->id,
        ]);

        expect(ForumReply::query()->sole())
            ->author_id->toBe($parent->id)
            ->forum_thread_id->toBe($thread->id)
            ->status->toBe(ForumContentStatus::Published);
    });

    it('validates the reply length', function (array $input, string $message) {
        $thread = ForumThread::factory()->create();

        $this->actingAs(User::factory()->parent()->create())
            ->post(route('forum.threads.replies.store', $thread), $input)
            ->assertSessionHasErrors(['body' => $message]);

        $this->assertDatabaseCount('forum_replies', 0);
    })->with([
        'missing' => [[], 'The reply field is required.'],
        'too short' => [['body' => 'k'], 'The reply field must be at least 2 characters.'],
        'too long' => [['body' => str_repeat('a', 3001)], 'The reply field must not be greater than 3000 characters.'],
    ]);

    it('accepts replies at the length limits', function (int $length) {
        $thread = ForumThread::factory()->create();

        $this->actingAs(User::factory()->parent()->create())
            ->post(route('forum.threads.replies.store', $thread), ['body' => str_repeat('a', $length)])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseCount('forum_replies', 1);
    })->with(['shortest' => 2, 'longest' => 3000]);

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
            ->assertSessionHas('status', 'Your reply is posted.');

        $this->assertDatabaseCount('forum_replies', 1);
    });

    it('refuses the author’s reply to their own thread while it waits for approval', function () {
        $parent = User::factory()->parent()->create();
        $thread = ForumThread::factory()->pending()->for($parent, 'author')->create();

        $this->actingAs($parent)
            ->post(route('forum.threads.replies.store', $thread), ['body' => 'Adding more details.'])
            ->assertRedirectToRoute('forum.threads.show', $thread)
            ->assertSessionHas('error', ForumPosting::UNPUBLISHED_THREAD_MESSAGE);

        $this->assertDatabaseCount('forum_replies', 0);
    });

    it('lets administrators reply to a pending thread', function () {
        $thread = ForumThread::factory()->pending()->create();

        $this->actingAs(User::factory()->admin()->create())
            ->post(route('forum.threads.replies.store', $thread), ['body' => 'Please add the source of this tip.'])
            ->assertSessionHas('status', 'Your reply is posted.');

        $this->assertDatabaseCount('forum_replies', 1);
    });

    it('returns 404 for a thread the member cannot see', function () {
        $thread = ForumThread::factory()->hidden()->create();

        $this->actingAs(User::factory()->parent()->create())
            ->post(route('forum.threads.replies.store', $thread), ['body' => 'Hello there.'])
            ->assertNotFound();

        $this->assertDatabaseCount('forum_replies', 0);
    });

    it('forbids members whose posting permission was revoked', function () {
        $thread = ForumThread::factory()->create();
        app(RolePermissions::class)->revoke(UserRole::Parent, Permission::PostInForum);

        $this->actingAs(User::factory()->parent()->create())
            ->post(route('forum.threads.replies.store', $thread), ['body' => 'Hello there.'])
            ->assertForbidden();

        $this->assertDatabaseCount('forum_replies', 0);
    });

    it('refuses teachers and parents while the forum is turned off', function () {
        $thread = ForumThread::factory()->create();
        app(Settings::class)->set('forum_enabled', false);

        $this->actingAs(User::factory()->teacher()->create())
            ->post(route('forum.threads.replies.store', $thread), ['body' => 'Hello there.'])
            ->assertForbidden();

        $this->assertDatabaseCount('forum_replies', 0);
    });
});

describe('edit', function () {
    it('shows the author the edit form', function () {
        $parent = User::factory()->parent()->create();
        $reply = ForumReply::factory()->for($parent, 'author')->create(['body' => 'My original reply']);

        $this->actingAs($parent)
            ->get(route('forum.replies.edit', $reply))
            ->assertOk()
            ->assertViewIs('forum.replies.edit')
            ->assertSeeText('My original reply')
            ->assertSee(route('forum.threads.show', $reply->forum_thread_id).'#reply-'.$reply->id, false);
    });

    it('forbids other members', function (UserRole $role) {
        $reply = ForumReply::factory()->create();

        $this->actingAs(User::factory()->{$role->value}()->create())
            ->get(route('forum.replies.edit', $reply))
            ->assertForbidden()
            ->assertSeeText(ForumReplyPolicy::NOT_AUTHOR_MESSAGE);
    })->with([UserRole::Admin, UserRole::Teacher, UserRole::Parent]);

    it('refuses the author while the thread is locked', function () {
        $parent = User::factory()->parent()->create();
        $reply = ForumReply::factory()->for($parent, 'author')->for(ForumThread::factory()->locked(), 'thread')->create();

        $this->actingAs($parent)
            ->get(route('forum.replies.edit', $reply))
            ->assertForbidden()
            ->assertSeeText(ForumThreadPolicy::LOCKED_MESSAGE);
    });

    it('returns 404 when the reply’s thread is hidden from its author', function () {
        $parent = User::factory()->parent()->create();
        $reply = ForumReply::factory()->for($parent, 'author')->for(ForumThread::factory()->hidden(), 'thread')->create();

        $this->actingAs($parent)
            ->get(route('forum.replies.edit', $reply))
            ->assertNotFound();
    });

    it('returns 404 to other members for a reply in a thread they cannot see', function () {
        $reply = ForumReply::factory()->for(ForumThread::factory()->pending(), 'thread')->create();

        $this->actingAs(User::factory()->parent()->create())
            ->get(route('forum.replies.edit', $reply))
            ->assertNotFound();
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
            ->put(route('forum.replies.update', $reply), ['body' => 'Edited reply with a correction.'])
            ->assertRedirect(route('forum.threads.show', $reply->forum_thread_id).'#reply-'.$reply->id)
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status', 'Your reply was updated.');

        expect($reply->refresh()->body)->toBe('Edited reply with a correction.');
    });

    it('validates the reply', function () {
        $parent = User::factory()->parent()->create();
        $reply = ForumReply::factory()->for($parent, 'author')->create(['body' => 'Original reply']);

        $this->actingAs($parent)
            ->put(route('forum.replies.update', $reply), ['body' => ''])
            ->assertSessionHasErrors(['body' => 'The reply field is required.']);

        expect($reply->refresh()->body)->toBe('Original reply');
    });

    it('forbids another member, before validating', function () {
        $reply = ForumReply::factory()->create(['body' => 'Original reply']);

        $this->actingAs(User::factory()->parent()->create())
            ->put(route('forum.replies.update', $reply), ['body' => ''])
            ->assertForbidden()
            ->assertSessionHasNoErrors();

        expect($reply->refresh()->body)->toBe('Original reply');
    });

    it('refuses changes while the thread is locked', function () {
        $parent = User::factory()->parent()->create();
        $reply = ForumReply::factory()->for($parent, 'author')->for(ForumThread::factory()->locked(), 'thread')->create(['body' => 'Original reply']);

        $this->actingAs($parent)
            ->put(route('forum.replies.update', $reply), ['body' => 'Edited reply.'])
            ->assertForbidden();

        expect($reply->refresh()->body)->toBe('Original reply');
    });

    it('ignores the status in the payload', function () {
        $parent = User::factory()->parent()->create();
        $reply = ForumReply::factory()->for($parent, 'author')->create();

        $this->actingAs($parent)->put(route('forum.replies.update', $reply), ['body' => 'Edited reply.', 'status' => 'hidden']);

        expect($reply->refresh()->status)->toBe(ForumContentStatus::Published);
    });
});

describe('destroy', function () {
    it('lets the author delete the reply and returns to the replies', function () {
        $parent = User::factory()->parent()->create();
        $reply = ForumReply::factory()->for($parent, 'author')->create();

        $this->actingAs($parent)
            ->delete(route('forum.replies.destroy', $reply))
            ->assertRedirect(route('forum.threads.show', $reply->forum_thread_id).'#replies')
            ->assertSessionHas('status', 'The reply was deleted.');

        $this->assertModelMissing($reply);
    });

    it('returns to the page the deleted reply was on', function () {
        $thread = ForumThread::factory()->create();
        ForumReply::factory()->for($thread, 'thread')->count(ForumPosting::REPLIES_PER_PAGE + 1)->create(['created_at' => now()->subDay()]);
        $parent = User::factory()->parent()->create();
        $reply = ForumReply::factory()->for($thread, 'thread')->for($parent, 'author')->create();

        $this->actingAs($parent)
            ->delete(route('forum.replies.destroy', $reply))
            ->assertRedirect(route('forum.threads.show', ['thread' => $thread, 'page' => 2]).'#replies');
    });

    it('returns to the last page when the deleted reply was alone on its page', function () {
        $thread = ForumThread::factory()->create();
        ForumReply::factory()->for($thread, 'thread')->count(ForumPosting::REPLIES_PER_PAGE)->create(['created_at' => now()->subDay()]);
        $parent = User::factory()->parent()->create();
        $reply = ForumReply::factory()->for($thread, 'thread')->for($parent, 'author')->create();

        $this->actingAs($parent)
            ->delete(route('forum.replies.destroy', $reply))
            ->assertRedirect(route('forum.threads.show', $thread).'#replies');
    });

    it('lets administrators delete anyone’s reply', function () {
        $reply = ForumReply::factory()->create();

        $this->actingAs(User::factory()->admin()->create())
            ->delete(route('forum.replies.destroy', $reply))
            ->assertSessionHas('status', 'The reply was deleted.');

        $this->assertModelMissing($reply);
    });

    it('forbids other members', function () {
        $reply = ForumReply::factory()->create();

        $this->actingAs(User::factory()->teacher()->create())
            ->delete(route('forum.replies.destroy', $reply))
            ->assertForbidden()
            ->assertSeeText(ForumReplyPolicy::CANNOT_DELETE_MESSAGE);

        $this->assertModelExists($reply);
    });

    it('returns 404 for a reply in a thread the member cannot see', function () {
        $reply = ForumReply::factory()->for(ForumThread::factory()->hidden(), 'thread')->create();

        $this->actingAs(User::factory()->parent()->create())
            ->delete(route('forum.replies.destroy', $reply))
            ->assertNotFound();

        $this->assertModelExists($reply);
    });
});
