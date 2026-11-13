<?php

use App\Enums\ForumContentStatus;
use App\Models\ForumCategory;
use App\Models\ForumReply;
use App\Models\ForumReport;
use App\Models\ForumThread;
use App\Models\User;

it('shows administrators every thread', function () {
    $threadIds = [
        ForumThread::factory()->create()->id,
        ForumThread::factory()->pending()->create()->id,
        ForumThread::factory()->hidden()->create()->id,
    ];

    $visibleIds = ForumThread::query()->visibleTo(User::factory()->admin()->create())->pluck('id')->all();

    expect($visibleIds)->toEqualCanonicalizing($threadIds);
});

it('shows other users published threads and their own threads', function (string $role) {
    $user = User::factory()->{$role}()->create();
    $publishedThread = ForumThread::factory()->create();
    $ownPendingThread = ForumThread::factory()->pending()->for($user, 'author')->create();
    $ownHiddenThread = ForumThread::factory()->hidden()->for($user, 'author')->create();
    ForumThread::factory()->pending()->create();
    ForumThread::factory()->hidden()->create();

    $visibleIds = ForumThread::query()->visibleTo($user)->pluck('id')->all();

    expect($visibleIds)->toEqualCanonicalizing([$publishedThread->id, $ownPendingThread->id, $ownHiddenThread->id]);
})->with(['parent', 'teacher']);

it('keeps the other constraints of the query when scoping to visible threads', function () {
    $category = ForumCategory::factory()->create();
    $parent = User::factory()->parent()->create();
    $threadInCategory = ForumThread::factory()->for($category, 'category')->create();
    ForumThread::factory()->create();
    ForumThread::factory()->pending()->for($category, 'category')->create();

    $visibleIds = $category->threads()->visibleTo($parent)->pluck('id')->all();

    expect($visibleIds)->toBe([$threadInCategory->id]);
});

it('filters published threads', function () {
    $publishedThread = ForumThread::factory()->create();
    ForumThread::factory()->pending()->create();
    ForumThread::factory()->hidden()->create();

    expect(ForumThread::query()->published()->pluck('id')->all())->toBe([$publishedThread->id]);
});

it('reaches its category, author, replies and reports', function () {
    $thread = ForumThread::factory()->create();
    $reply = ForumReply::factory()->for($thread, 'thread')->create();
    $report = ForumReport::factory()->for($thread, 'thread')->create();

    expect($thread->category->id)->toBe($thread->forum_category_id);
    expect($thread->author->id)->toBe($thread->author_id);
    expect($thread->replies()->sole()->is($reply))->toBeTrue();
    expect($thread->reports()->sole()->is($report))->toBeTrue();
});

it('casts the moderation fields', function () {
    $thread = ForumThread::factory()->pinned()->locked()->pending()->create()->fresh();

    expect($thread->status)->toBe(ForumContentStatus::Pending);
    expect($thread->is_pinned)->toBeTrue();
    expect($thread->is_locked)->toBeTrue();
});
