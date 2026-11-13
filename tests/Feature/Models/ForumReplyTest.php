<?php

use App\Enums\ForumContentStatus;
use App\Models\ForumReply;
use App\Models\ForumReport;

it('filters published replies', function () {
    $publishedReply = ForumReply::factory()->create();
    ForumReply::factory()->hidden()->create();

    expect(ForumReply::query()->published()->pluck('id')->all())->toBe([$publishedReply->id]);
});

it('reaches its thread, author and reports and casts its status', function () {
    $reply = ForumReply::factory()->hidden()->create();
    $report = ForumReport::factory()->for($reply, 'reply')->create();

    expect($reply->fresh()->status)->toBe(ForumContentStatus::Hidden);
    expect($reply->thread->id)->toBe($reply->forum_thread_id);
    expect($reply->author->id)->toBe($reply->author_id);
    expect($reply->reports()->sole()->is($report))->toBeTrue();
});
