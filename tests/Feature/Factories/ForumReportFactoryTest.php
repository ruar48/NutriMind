<?php

use App\Models\ForumReply;
use App\Models\ForumReport;

it('reports a thread without a reply and leaves it unresolved', function () {
    $report = ForumReport::factory()->create();

    expect($report->forum_reply_id)->toBeNull();
    expect($report->resolved_at)->toBeNull();
    expect($report->reporter_id)->not->toBe($report->thread->author_id);
});

it('points a report about a reply at the thread of that reply', function () {
    $reply = ForumReply::factory()->create();

    $report = ForumReport::factory()->for($reply, 'reply')->create();

    expect($report->forum_thread_id)->toBe($reply->forum_thread_id);
});

it('creates reports an administrator dismissed', function () {
    $report = ForumReport::factory()->resolved()->create();

    expect($report->resolved_at)->not->toBeNull();
    expect($report->resolver->isAdmin())->toBeTrue();
    expect($report->resolution)->toBe(ForumReport::RESOLUTION_DISMISSED);
});
