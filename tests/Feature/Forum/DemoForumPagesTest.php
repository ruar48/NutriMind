<?php

use App\Models\ForumCategory;
use App\Models\ForumReply;
use App\Models\ForumThread;
use App\Models\User;
use Database\Seeders\Support\Catalog\PeopleCatalog;

use function Pest\Laravel\travelTo;

it('renders every forum and moderation page over the seeded demo school', function () {
    travelTo(now()->setDate(2026, 11, 13)->setTime(10, 0));
    $this->seed();
    $admin = User::query()->where('email', PeopleCatalog::ADMIN['email'])->sole();
    $parent = User::query()->where('email', PeopleCatalog::DEMO_PARENT_EMAIL)->sole();
    $teacher = User::query()->where('email', 'teacher@nutrimind.test')->sole();
    $hiddenReply = ForumReply::query()->where('status', 'hidden')->sole();

    foreach ([$admin, $teacher, $parent] as $member) {
        $this->actingAs($member)->get(route('forum.index'))->assertOk();
        $this->actingAs($member)->get(route('forum.threads.create'))->assertOk();

        foreach (ForumCategory::query()->get() as $category) {
            $this->actingAs($member)->get(route('forum.categories.show', $category))->assertOk();
        }

        foreach (ForumThread::query()->visibleTo($member)->get() as $thread) {
            $this->actingAs($member)->get(route('forum.threads.show', $thread))->assertOk();
        }
    }

    $this->actingAs($parent)
        ->get(route('forum.threads.show', $hiddenReply->forum_thread_id))
        ->assertSeeText('This reply was hidden by a moderator.');
    $this->actingAs($admin)->get(route('admin.forum.reports.index'))->assertOk()->assertViewHas('openCount', 3)->assertViewHas('pendingCount', 2);
    $this->actingAs($admin)->get(route('admin.forum.reports.index', ['status' => 'all']))->assertOk();
    $this->actingAs($admin)->get(route('admin.forum.pending.index'))->assertOk()->assertSeeText('Waiting for approval');
    $this->actingAs($admin)->get(route('admin.forum-categories.index'))->assertOk();
    $this->actingAs($admin)->get(route('admin.forum-categories.edit', ForumCategory::query()->first()))->assertOk();
});
