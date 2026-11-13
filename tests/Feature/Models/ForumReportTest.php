<?php

use App\Enums\ForumReportReason;
use App\Models\ForumReply;
use App\Models\ForumReport;

it('filters reports no moderator has resolved yet', function () {
    $openReport = ForumReport::factory()->create();
    ForumReport::factory()->resolved()->create();

    expect(ForumReport::query()->unresolved()->pluck('id')->all())->toBe([$openReport->id]);
});

it('reaches the reported thread and reply, the reporter and the resolver', function () {
    $reply = ForumReply::factory()->create();
    $report = ForumReport::factory()->resolved()->for($reply, 'reply')->create(['reason' => ForumReportReason::OffTopic])->fresh();

    expect($report->reason)->toBe(ForumReportReason::OffTopic);
    expect($report->thread->is($reply->thread))->toBeTrue();
    expect($report->reply->is($reply))->toBeTrue();
    expect($report->reporter->id)->toBe($report->reporter_id);
    expect($report->resolver->isAdmin())->toBeTrue();
    expect($report->resolution)->toBe(ForumReport::RESOLUTION_DISMISSED);
});
