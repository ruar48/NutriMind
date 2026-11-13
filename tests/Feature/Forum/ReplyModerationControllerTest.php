<?php

use App\Enums\ForumContentStatus;
use App\Enums\UserRole;
use App\Models\ForumReply;
use App\Models\ForumReport;
use App\Models\User;
use App\Policies\ForumThreadPolicy;

it('sends guests to the sign-in page', function () {
    $reply = ForumReply::factory()->create();

    $this->put(route('forum.replies.moderation.update', $reply), ['action' => 'hide'])->assertRedirectToRoute('login');

    expect($reply->refresh()->status)->toBe(ForumContentStatus::Published);
});

it('forbids learner accounts', function () {
    $reply = ForumReply::factory()->create();

    $this->actingAs(User::factory()->student()->create())
        ->put(route('forum.replies.moderation.update', $reply), ['action' => 'hide'])
        ->assertForbidden();
});

it('lets only administrators moderate replies', function (UserRole $role) {
    $reply = ForumReply::factory()->create();

    $this->actingAs(User::factory()->{$role->value}()->create())
        ->put(route('forum.replies.moderation.update', $reply), ['action' => 'hide'])
        ->assertForbidden()
        ->assertSeeText(ForumThreadPolicy::MODERATORS_ONLY_MESSAGE);

    expect($reply->refresh()->status)->toBe(ForumContentStatus::Published);
})->with([UserRole::Teacher, UserRole::Parent]);

it('hides a reply, resolves its open reports and returns to it', function () {
    $this->freezeSecond();
    $admin = User::factory()->admin()->create();
    $reply = ForumReply::factory()->create();
    $replyReports = ForumReport::factory()->for($reply, 'reply')->count(2)->create();
    $threadReport = ForumReport::factory()->for($reply->thread, 'thread')->create();

    $this->actingAs($admin)
        ->put(route('forum.replies.moderation.update', $reply), ['action' => 'hide'])
        ->assertRedirect(route('forum.threads.show', $reply->forum_thread_id).'#reply-'.$reply->id)
        ->assertSessionHas('status', 'The reply is hidden. Readers see a moderator notice instead.');

    expect($reply->refresh()->status)->toBe(ForumContentStatus::Hidden);
    $replyReports->each(fn (ForumReport $report) => expect($report->refresh())
        ->resolution->toBe(ForumReport::RESOLUTION_CONTENT_HIDDEN)
        ->resolved_by->toBe($admin->id));
    expect($threadReport->refresh()->resolved_at)->toBeNull();
});

it('shows a hidden reply again', function () {
    $reply = ForumReply::factory()->hidden()->create();

    $this->actingAs(User::factory()->admin()->create())
        ->put(route('forum.replies.moderation.update', $reply), ['action' => 'unhide'])
        ->assertSessionHas('status', 'The reply is visible again.');

    expect($reply->refresh()->status)->toBe(ForumContentStatus::Published);
});

it('validates the action', function (array $input, string $message) {
    $reply = ForumReply::factory()->create();

    $this->actingAs(User::factory()->admin()->create())
        ->put(route('forum.replies.moderation.update', $reply), $input)
        ->assertSessionHasErrors(['action' => $message]);

    expect($reply->refresh()->status)->toBe(ForumContentStatus::Published);
})->with([
    'missing' => [[], 'Choose a moderation action.'],
    'thread-only tool' => [['action' => 'pin'], 'Replies can only be hidden or shown again.'],
]);

it('returns 404 for an unknown reply', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->put(route('forum.replies.moderation.update', 999), ['action' => 'hide'])
        ->assertNotFound();
});
