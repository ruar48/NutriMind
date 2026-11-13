<?php

use App\Enums\ForumContentStatus;
use App\Enums\UserRole;
use App\Models\ForumReply;
use App\Models\ForumReport;
use App\Models\ForumThread;
use App\Models\User;
use App\Policies\ForumThreadPolicy;
use App\Services\Forum\ForumModeration;

it('sends guests to the sign-in page', function () {
    $thread = ForumThread::factory()->create();

    $this->put(route('forum.threads.moderation.update', $thread), ['action' => 'pin'])->assertRedirectToRoute('login');

    expect($thread->refresh()->is_pinned)->toBeFalse();
});

it('forbids learner accounts', function () {
    $thread = ForumThread::factory()->create();

    $this->actingAs(User::factory()->student()->create())
        ->put(route('forum.threads.moderation.update', $thread), ['action' => 'pin'])
        ->assertForbidden();
});

it('lets only administrators moderate', function (UserRole $role) {
    $thread = ForumThread::factory()->create();

    $this->actingAs(User::factory()->{$role->value}()->create())
        ->put(route('forum.threads.moderation.update', $thread), ['action' => 'pin'])
        ->assertForbidden()
        ->assertSeeText(ForumThreadPolicy::MODERATORS_ONLY_MESSAGE);

    expect($thread->refresh()->is_pinned)->toBeFalse();
})->with([UserRole::Teacher, UserRole::Parent]);

it('applies each moderator tool to the thread', function (Closure $thread, string $action, array $expected, string $message) {
    $thread = $thread();

    $this->actingAs(User::factory()->admin()->create())
        ->put(route('forum.threads.moderation.update', $thread), ['action' => $action])
        ->assertRedirectToRoute('forum.threads.show', $thread)
        ->assertSessionHasNoErrors()
        ->assertSessionHas('status', $message);

    expect($thread->refresh()->only(array_keys($expected)))->toEqual($expected);
})->with([
    'pin' => [fn () => ForumThread::factory()->create(['title' => 'Snack ideas']), 'pin', ['is_pinned' => true], '“Snack ideas” is pinned to the top of the forum.'],
    'unpin' => [fn () => ForumThread::factory()->pinned()->create(['title' => 'Snack ideas']), 'unpin', ['is_pinned' => false], '“Snack ideas” is no longer pinned.'],
    'lock' => [fn () => ForumThread::factory()->create(['title' => 'Snack ideas']), 'lock', ['is_locked' => true], '“Snack ideas” is locked. Only administrators can reply now.'],
    'unlock' => [fn () => ForumThread::factory()->locked()->create(['title' => 'Snack ideas']), 'unlock', ['is_locked' => false], '“Snack ideas” is open for replies again.'],
    'hide' => [fn () => ForumThread::factory()->create(['title' => 'Snack ideas']), 'hide', ['status' => ForumContentStatus::Hidden], '“Snack ideas” is hidden from the forum.'],
    'show again' => [fn () => ForumThread::factory()->hidden()->create(['title' => 'Snack ideas']), 'unhide', ['status' => ForumContentStatus::Published], '“Snack ideas” is visible in the forum again.'],
]);

it('resolves the open reports about a thread it hides, and only those', function () {
    $this->freezeSecond();
    $admin = User::factory()->admin()->create();
    $thread = ForumThread::factory()->create();
    $threadReport = ForumReport::factory()->for($thread, 'thread')->create();
    $replyReport = ForumReport::factory()->for(ForumReply::factory()->for($thread, 'thread'), 'reply')->create();
    $otherReport = ForumReport::factory()->create();

    $this->actingAs($admin)->put(route('forum.threads.moderation.update', $thread), ['action' => 'hide']);

    expect($threadReport->refresh())
        ->resolution->toBe(ForumReport::RESOLUTION_CONTENT_HIDDEN)
        ->resolved_by->toBe($admin->id)
        ->and($threadReport->resolved_at->toDateTimeString())->toBe(now()->toDateTimeString());
    expect($replyReport->refresh()->resolved_at)->toBeNull();
    expect($otherReport->refresh()->resolved_at)->toBeNull();
});

it('refuses to show a pending thread instead of approving it', function () {
    $thread = ForumThread::factory()->pending()->create();

    $this->actingAs(User::factory()->admin()->create())
        ->put(route('forum.threads.moderation.update', $thread), ['action' => 'unhide'])
        ->assertRedirectToRoute('forum.threads.show', $thread)
        ->assertSessionHas('error', ForumModeration::PENDING_UNHIDE_MESSAGE);

    expect($thread->refresh()->status)->toBe(ForumContentStatus::Pending);
});

it('validates the action', function (array $input, string $message) {
    $thread = ForumThread::factory()->create();

    $this->actingAs(User::factory()->admin()->create())
        ->put(route('forum.threads.moderation.update', $thread), $input)
        ->assertSessionHasErrors(['action' => $message]);
})->with([
    'missing' => [[], 'Choose a moderation action.'],
    'unknown' => [['action' => 'delete'], 'Choose pin, unpin, lock, unlock, hide or show again.'],
]);

it('returns 404 for an unknown thread', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->put(route('forum.threads.moderation.update', 999), ['action' => 'pin'])
        ->assertNotFound();
});
