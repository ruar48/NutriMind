<?php

use App\Enums\ForumReportReason;
use App\Enums\Permission;
use App\Enums\UserRole;
use App\Http\Controllers\Forum\ReportController;
use App\Models\ForumReply;
use App\Models\ForumReport;
use App\Models\ForumThread;
use App\Models\User;
use App\Policies\ForumThreadPolicy;
use App\Services\RolePermissions;
use App\Services\Settings;

it('sends guests to the sign-in page', function () {
    $thread = ForumThread::factory()->create();

    $this->post(route('forum.reports.store'), ['forum_thread_id' => $thread->id, 'reason' => 'spam'])
        ->assertRedirectToRoute('login');
});

it('forbids learner accounts', function () {
    $thread = ForumThread::factory()->create();

    $this->actingAs(User::factory()->student()->create())
        ->post(route('forum.reports.store'), ['forum_thread_id' => $thread->id, 'reason' => 'spam'])
        ->assertForbidden();
});

it('reports a thread to the moderators', function (UserRole $role) {
    $thread = ForumThread::factory()->create();
    $reporter = User::factory()->{$role->value}()->create();

    $this->actingAs($reporter)
        ->post(route('forum.reports.store'), [
            'forum_thread_id' => $thread->id,
            'reason' => ForumReportReason::Spam->value,
            'details' => 'Selling slimming tea.',
        ])
        ->assertRedirect(route('forum.threads.show', $thread))
        ->assertSessionHasNoErrors()
        ->assertSessionHas('status', 'Thank you. A moderator will review your report.');

    expect(ForumReport::query()->sole())
        ->reporter_id->toBe($reporter->id)
        ->forum_thread_id->toBe($thread->id)
        ->forum_reply_id->toBeNull()
        ->reason->toBe(ForumReportReason::Spam)
        ->details->toBe('Selling slimming tea.')
        ->resolved_at->toBeNull();
})->with([UserRole::Teacher, UserRole::Parent]);

it('reports a reply and returns to it', function () {
    $reply = ForumReply::factory()->create();

    $this->actingAs(User::factory()->parent()->create())
        ->post(route('forum.reports.store'), [
            'forum_thread_id' => $reply->forum_thread_id,
            'forum_reply_id' => $reply->id,
            'reason' => ForumReportReason::Harassment->value,
        ])
        ->assertRedirect(route('forum.threads.show', ['thread' => $reply->forum_thread_id, 'reply' => $reply->id]))
        ->assertSessionHasNoErrors();

    expect(ForumReport::query()->sole())
        ->forum_thread_id->toBe($reply->forum_thread_id)
        ->forum_reply_id->toBe($reply->id)
        ->reason->toBe(ForumReportReason::Harassment)
        ->details->toBeNull();
});

it('returns to the page the report was sent from', function () {
    $thread = ForumThread::factory()->create();
    $page = route('forum.threads.show', [$thread, 'page' => 2]);

    $this->actingAs(User::factory()->parent()->create())
        ->from($page)
        ->post(route('forum.reports.store'), ['forum_thread_id' => $thread->id, 'reason' => 'spam'])
        ->assertRedirect($page);
});

it('ignores resolution fields in the payload', function () {
    $thread = ForumThread::factory()->create();

    $this->actingAs(User::factory()->parent()->create())->post(route('forum.reports.store'), [
        'forum_thread_id' => $thread->id,
        'reason' => 'spam',
        'resolved_at' => now()->toDateTimeString(),
        'resolution' => ForumReport::RESOLUTION_DISMISSED,
        'reporter_id' => User::factory()->admin()->create()->id,
    ]);

    expect(ForumReport::query()->sole())
        ->resolved_at->toBeNull()
        ->resolution->toBeNull();
});

it('refuses a report of the reporter’s own thread', function () {
    $parent = User::factory()->parent()->create();
    $thread = ForumThread::factory()->for($parent, 'author')->create();

    $this->actingAs($parent)
        ->post(route('forum.reports.store'), ['forum_thread_id' => $thread->id, 'reason' => 'spam'])
        ->assertForbidden()
        ->assertSeeText(ForumThreadPolicy::OWN_CONTENT_MESSAGE);

    $this->assertDatabaseCount('forum_reports', 0);
});

it('refuses a report of the reporter’s own reply', function () {
    $parent = User::factory()->parent()->create();
    $reply = ForumReply::factory()->for($parent, 'author')->create();

    $this->actingAs($parent)
        ->post(route('forum.reports.store'), ['forum_thread_id' => $reply->forum_thread_id, 'forum_reply_id' => $reply->id, 'reason' => 'spam'])
        ->assertForbidden()
        ->assertSeeText(ForumThreadPolicy::OWN_CONTENT_MESSAGE);

    $this->assertDatabaseCount('forum_reports', 0);
});

it('returns 404 for a thread the reporter cannot see', function (string $state) {
    $thread = ForumThread::factory()->{$state}()->create();

    $this->actingAs(User::factory()->parent()->create())
        ->post(route('forum.reports.store'), ['forum_thread_id' => $thread->id, 'reason' => 'spam'])
        ->assertNotFound();

    $this->assertDatabaseCount('forum_reports', 0);
})->with(['pending', 'hidden']);

it('returns 404 for a reply in a thread the reporter cannot see', function () {
    $reply = ForumReply::factory()->for(ForumThread::factory()->hidden(), 'thread')->create();

    $this->actingAs(User::factory()->parent()->create())
        ->post(route('forum.reports.store'), ['forum_thread_id' => $reply->forum_thread_id, 'forum_reply_id' => $reply->id, 'reason' => 'spam'])
        ->assertNotFound();

    $this->assertDatabaseCount('forum_reports', 0);
});

it('rejects a reply that belongs to another thread', function () {
    $thread = ForumThread::factory()->create();
    $otherReply = ForumReply::factory()->create();

    $this->actingAs(User::factory()->parent()->create())
        ->post(route('forum.reports.store'), ['forum_thread_id' => $thread->id, 'forum_reply_id' => $otherReply->id, 'reason' => 'spam'])
        ->assertSessionHasErrorsIn('report', ['forum_reply_id' => 'This reply is not part of the thread.']);

    $this->assertDatabaseCount('forum_reports', 0);
});

it('refuses a second open report of the same post by the same reporter', function () {
    $parent = User::factory()->parent()->create();
    $thread = ForumThread::factory()->create();
    ForumReport::factory()->for($thread, 'thread')->for($parent, 'reporter')->create();

    $this->actingAs($parent)
        ->post(route('forum.reports.store'), ['forum_thread_id' => $thread->id, 'reason' => 'spam'])
        ->assertRedirect(route('forum.threads.show', $thread))
        ->assertSessionHas('error', ReportController::ALREADY_REPORTED_MESSAGE);

    $this->assertDatabaseCount('forum_reports', 1);
});

it('refuses a second open report of the same reply', function () {
    $parent = User::factory()->parent()->create();
    $reply = ForumReply::factory()->create();
    ForumReport::factory()->for($reply, 'reply')->for($parent, 'reporter')->create();

    $this->actingAs($parent)
        ->post(route('forum.reports.store'), ['forum_thread_id' => $reply->forum_thread_id, 'forum_reply_id' => $reply->id, 'reason' => 'spam'])
        ->assertSessionHas('error', 'You already reported this.');

    $this->assertDatabaseCount('forum_reports', 1);
});

it('accepts a new report once the earlier one was resolved', function () {
    $parent = User::factory()->parent()->create();
    $thread = ForumThread::factory()->create();
    ForumReport::factory()->for($thread, 'thread')->for($parent, 'reporter')->resolved()->create();

    $this->actingAs($parent)
        ->post(route('forum.reports.store'), ['forum_thread_id' => $thread->id, 'reason' => 'spam'])
        ->assertSessionHas('status');

    $this->assertDatabaseCount('forum_reports', 2);
});

it('treats a thread and its replies as separate posts', function () {
    $parent = User::factory()->parent()->create();
    $reply = ForumReply::factory()->create();
    ForumReport::factory()->for($reply->thread, 'thread')->for($parent, 'reporter')->create();

    $this->actingAs($parent)
        ->post(route('forum.reports.store'), ['forum_thread_id' => $reply->forum_thread_id, 'forum_reply_id' => $reply->id, 'reason' => 'spam'])
        ->assertSessionHas('status');

    $this->assertDatabaseCount('forum_reports', 2);
});

it('lets another member report the same post', function () {
    $thread = ForumThread::factory()->create();
    ForumReport::factory()->for($thread, 'thread')->create();

    $this->actingAs(User::factory()->parent()->create())
        ->post(route('forum.reports.store'), ['forum_thread_id' => $thread->id, 'reason' => 'spam'])
        ->assertSessionHas('status');

    $this->assertDatabaseCount('forum_reports', 2);
});

it('validates the report in its own error bag', function (Closure $payload, string $field, string $message) {
    $this->actingAs(User::factory()->parent()->create())
        ->post(route('forum.reports.store'), $payload())
        ->assertSessionHasErrorsIn('report', [$field => $message]);

    $this->assertDatabaseCount('forum_reports', 0);
})->with([
    'missing thread' => [fn (): array => ['reason' => 'spam'], 'forum_thread_id', 'Choose the post you want to report.'],
    'unknown thread' => [fn (): array => ['forum_thread_id' => 999, 'reason' => 'spam'], 'forum_thread_id', 'This thread no longer exists.'],
    'missing reason' => [fn (): array => ['forum_thread_id' => ForumThread::factory()->create()->id], 'reason', 'Choose a reason for your report.'],
    'unknown reason' => [fn (): array => ['forum_thread_id' => ForumThread::factory()->create()->id, 'reason' => 'boring'], 'reason', 'Choose one of the listed reasons.'],
    'details too long' => [fn (): array => ['forum_thread_id' => ForumThread::factory()->create()->id, 'reason' => 'other', 'details' => str_repeat('a', 501)], 'details', 'The details field must not be greater than 500 characters.'],
]);

it('shows the report again on the thread page when it was not valid', function () {
    $thread = ForumThread::factory()->create();
    $parent = User::factory()->parent()->create();

    $this->actingAs($parent)
        ->from(route('forum.threads.show', $thread))
        ->post(route('forum.reports.store'), ['forum_thread_id' => $thread->id, 'details' => 'Please check this.'])
        ->assertRedirect(route('forum.threads.show', $thread));

    $this->actingAs($parent)
        ->get(route('forum.threads.show', $thread))
        ->assertOk()
        ->assertSee('data-forum-report-retry', false)
        ->assertSeeText('Choose a reason for your report.')
        ->assertSeeText('Please check this.');
});

it('refuses teachers and parents while the forum is turned off', function () {
    $thread = ForumThread::factory()->create();
    app(Settings::class)->set('forum_enabled', false);

    $this->actingAs(User::factory()->parent()->create())
        ->post(route('forum.reports.store'), ['forum_thread_id' => $thread->id, 'reason' => 'spam'])
        ->assertForbidden();

    $this->assertDatabaseCount('forum_reports', 0);
});

it('forbids a role whose forum access was revoked', function () {
    $thread = ForumThread::factory()->create();
    app(RolePermissions::class)->revoke(UserRole::Parent, Permission::AccessForum);

    $this->actingAs(User::factory()->parent()->create())
        ->post(route('forum.reports.store'), ['forum_thread_id' => $thread->id, 'reason' => 'spam'])
        ->assertForbidden();

    $this->assertDatabaseCount('forum_reports', 0);
});
