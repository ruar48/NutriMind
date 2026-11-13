<?php

use App\Enums\ForumContentStatus;
use App\Enums\ForumReportReason;
use App\Enums\UserRole;
use App\Models\ForumReply;
use App\Models\ForumReport;
use App\Models\ForumThread;
use App\Models\User;
use App\Services\Forum\ForumModeration;
use Illuminate\Support\Facades\DB;

describe('index', function () {
    it('sends guests to the sign-in page', function () {
        $this->get(route('admin.forum.reports.index'))->assertRedirectToRoute('login');
    });

    it('forbids every role but administrators', function (UserRole $role) {
        $this->actingAs(User::factory()->{$role->value}()->create())
            ->get(route('admin.forum.reports.index'))
            ->assertForbidden();
    })->with([UserRole::Teacher, UserRole::Parent, UserRole::Student]);

    it('lists the open reports with the reported post, reason and reporter', function () {
        $thread = ForumThread::factory()->create(['title' => 'Detox tea for kids']);
        $reply = ForumReply::factory()->for($thread, 'thread')->create(['body' => 'Buy my slimming tea now!']);
        ForumReport::factory()->for($thread, 'thread')->for($reply, 'reply')->for(User::factory()->parent()->create(['name' => 'Lorna Cruz']), 'reporter')
            ->create(['reason' => ForumReportReason::Spam, 'details' => 'Selling products.']);
        ForumReport::factory()->for($thread, 'thread')->create(['reason' => ForumReportReason::Misinformation]);

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.forum.reports.index'))
            ->assertOk()
            ->assertViewIs('admin.forum.reports.index')
            ->assertSeeText('Detox tea for kids')
            ->assertSeeText('Buy my slimming tea now!')
            ->assertSeeText('Spam or advertising')
            ->assertSeeText('Selling products.')
            ->assertSeeText('Misinformation')
            ->assertSeeText('Lorna Cruz')
            ->assertSee(route('forum.threads.show', ['thread' => $thread, 'reply' => $reply->id]), false)
            ->assertSee(route('forum.threads.show', $thread), false);
    });

    it('leaves out resolved reports by default', function () {
        ForumReport::factory()->for(ForumThread::factory()->state(['title' => 'Open report thread']), 'thread')->create();
        ForumReport::factory()->resolved()->for(ForumThread::factory()->state(['title' => 'Resolved report thread']), 'thread')->create();

        $response = $this->actingAs(User::factory()->admin()->create())->get(route('admin.forum.reports.index'));

        $response->assertOk()
            ->assertSeeText('Open report thread')
            ->assertDontSeeText('Resolved report thread');
        expect($response->viewData('status'))->toBe('open');
    });

    it('lists resolved reports with their resolution and moderator', function () {
        $moderator = User::factory()->admin()->create(['name' => 'Principal Reyes']);
        ForumReport::factory()->for(ForumThread::factory()->state(['title' => 'Open report thread']), 'thread')->create();
        ForumReport::factory()->for(ForumThread::factory()->state(['title' => 'Dismissed report thread']), 'thread')
            ->create(['resolved_at' => now(), 'resolved_by' => $moderator->id, 'resolution' => ForumReport::RESOLUTION_DISMISSED]);
        ForumReport::factory()->for(ForumThread::factory()->hidden()->state(['title' => 'Hidden report thread']), 'thread')
            ->create(['resolved_at' => now(), 'resolved_by' => $moderator->id, 'resolution' => ForumReport::RESOLUTION_CONTENT_HIDDEN]);

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.forum.reports.index', ['status' => 'resolved']))
            ->assertOk()
            ->assertDontSeeText('Open report thread')
            ->assertSeeText('Dismissed report thread')
            ->assertSeeText('Hidden report thread')
            ->assertSeeText('Dismissed')
            ->assertSeeText('Content hidden')
            ->assertSeeText('by Principal Reyes');
    });

    it('lists open reports before resolved ones when every report is shown', function () {
        ForumReport::factory()->resolved()->for(ForumThread::factory()->state(['title' => 'Resolved recently']), 'thread')->create(['created_at' => now()]);
        ForumReport::factory()->for(ForumThread::factory()->state(['title' => 'Open since last week']), 'thread')->create(['created_at' => now()->subWeek()]);

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.forum.reports.index', ['status' => 'all']))
            ->assertOk()
            ->assertSeeTextInOrder(['Open since last week', 'Resolved recently']);
    });

    it('falls back to the open reports for an unknown status', function () {
        $response = $this->actingAs(User::factory()->admin()->create())->get(route('admin.forum.reports.index', ['status' => 'everything']));

        $response->assertOk();
        expect($response->viewData('status'))->toBe('open');
    });

    it('filters by reason', function () {
        ForumReport::factory()->for(ForumThread::factory()->state(['title' => 'Spammy thread']), 'thread')->create(['reason' => ForumReportReason::Spam]);
        ForumReport::factory()->for(ForumThread::factory()->state(['title' => 'Off-topic thread']), 'thread')->create(['reason' => ForumReportReason::OffTopic]);

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.forum.reports.index', ['reason' => 'spam']))
            ->assertOk()
            ->assertSeeText('Spammy thread')
            ->assertDontSeeText('Off-topic thread');
    });

    it('searches the reported posts, details and reporters', function (string $search) {
        $reporter = User::factory()->parent()->create(['name' => 'Lorna Cruz']);
        $reply = ForumReply::factory()->for(ForumThread::factory()->state(['title' => 'Water bottles']), 'thread')->create(['body' => 'Soda is just as good as water.']);
        ForumReport::factory()->for($reply->thread, 'thread')->for($reply, 'reply')->for($reporter, 'reporter')->create(['details' => 'Misleading health claim.']);
        ForumReport::factory()->for(ForumThread::factory()->state(['title' => 'Unrelated thread']), 'thread')->create(['details' => 'Other details.']);

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.forum.reports.index', ['search' => $search]))
            ->assertOk()
            ->assertSeeText('Water bottles')
            ->assertDontSeeText('Unrelated thread');
    })->with([
        'thread title' => 'bottles',
        'reply body' => 'soda is',
        'details' => 'misleading',
        'reporter' => 'Lorna',
    ]);

    it('shows the open and pending counts in the moderation tabs', function () {
        ForumReport::factory()->count(2)->create();
        ForumReport::factory()->resolved()->create();
        ForumThread::factory()->pending()->count(3)->create();

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.forum.reports.index'))
            ->assertOk()
            ->assertViewHas('openCount', 2)
            ->assertViewHas('pendingCount', 3)
            ->assertSee(route('admin.forum.pending.index'), false);
    });

    it('offers dismiss and hide for open reports only', function () {
        $open = ForumReport::factory()->create();
        $resolved = ForumReport::factory()->resolved()->create();

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.forum.reports.index', ['status' => 'all']))
            ->assertOk()
            ->assertSee(route('admin.forum.reports.update', $open), false)
            ->assertDontSee(route('admin.forum.reports.update', $resolved), false);
    });

    it('shows a calm empty state when nothing is open', function () {
        $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.forum.reports.index'))
            ->assertOk()
            ->assertSeeText('No open reports')
            ->assertSee(route('admin.forum.reports.index', ['status' => 'resolved']), false);
    });

    it('offers to clear the filters when nothing matches', function () {
        ForumReport::factory()->create();

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.forum.reports.index', ['search' => 'nothing matches this']))
            ->assertOk()
            ->assertSeeText('No reports match your filters');
    });

    it('escapes the reported content and the details', function () {
        $reply = ForumReply::factory()->create(['body' => '<script>alert("reply")</script>']);
        ForumReport::factory()->for($reply->thread, 'thread')->for($reply, 'reply')->create(['details' => '<img src=x onerror=alert(1)>']);

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.forum.reports.index'))
            ->assertOk()
            ->assertDontSee('<script>alert("reply")</script>', false)
            ->assertDontSee('<img src=x onerror=alert(1)>', false);
    });

    it('pages through the reports', function () {
        ForumReport::factory()->count(21)->create();

        $response = $this->actingAs(User::factory()->admin()->create())->get(route('admin.forum.reports.index'));

        $response->assertOk();
        expect($response->viewData('reports'))->total()->toBe(21)->count()->toBe(20);
    });

    it('renders many reports with a fixed number of queries', function () {
        $admin = User::factory()->admin()->create();
        ForumReport::factory()->create();
        ForumReport::factory()->for(ForumReply::factory(), 'reply')->create();
        ForumReport::factory()->resolved()->create();
        $this->actingAs($admin)->get(route('admin.forum.reports.index', ['status' => 'all']))->assertOk();
        DB::enableQueryLog();
        $this->actingAs($admin)->get(route('admin.forum.reports.index', ['status' => 'all']))->assertOk();
        $fewReportsQueries = count(DB::getQueryLog());
        ForumReport::factory()->for(ForumReply::factory(), 'reply')->count(4)->create();
        ForumReport::factory()->resolved()->count(4)->create();
        DB::flushQueryLog();

        $this->actingAs($admin)->get(route('admin.forum.reports.index', ['status' => 'all']))->assertOk();

        expect(count(DB::getQueryLog()))->toBe($fewReportsQueries);
    });
});

describe('update', function () {
    it('forbids every role but administrators', function (UserRole $role) {
        $report = ForumReport::factory()->create();

        $this->actingAs(User::factory()->{$role->value}()->create())
            ->put(route('admin.forum.reports.update', $report), ['decision' => 'dismiss'])
            ->assertForbidden();

        expect($report->refresh()->resolved_at)->toBeNull();
    })->with([UserRole::Teacher, UserRole::Parent]);

    it('dismisses a report and keeps the post', function () {
        $this->freezeSecond();
        $admin = User::factory()->admin()->create();
        $thread = ForumThread::factory()->create();
        $report = ForumReport::factory()->for($thread, 'thread')->create();
        $otherReport = ForumReport::factory()->for($thread, 'thread')->create();

        $this->actingAs($admin)
            ->from(route('admin.forum.reports.index', ['reason' => 'spam']))
            ->put(route('admin.forum.reports.update', $report), ['decision' => 'dismiss'])
            ->assertRedirect(route('admin.forum.reports.index', ['reason' => 'spam']))
            ->assertSessionHas('status', 'Report dismissed. The post stays visible.');

        expect($report->refresh())
            ->resolution->toBe(ForumReport::RESOLUTION_DISMISSED)
            ->resolved_by->toBe($admin->id)
            ->and($report->resolved_at->toDateTimeString())->toBe(now()->toDateTimeString());
        expect($thread->refresh()->status)->toBe(ForumContentStatus::Published);
        expect($otherReport->refresh()->resolved_at)->toBeNull();
    });

    it('hides a reported thread and resolves every open report about it', function () {
        $admin = User::factory()->admin()->create();
        $thread = ForumThread::factory()->create();
        $reports = ForumReport::factory()->for($thread, 'thread')->count(2)->create();
        $replyReport = ForumReport::factory()->for(ForumReply::factory()->for($thread, 'thread'), 'reply')->create();
        $dismissed = ForumReport::factory()->for($thread, 'thread')->resolved()->create();

        $this->actingAs($admin)
            ->put(route('admin.forum.reports.update', $reports[0]), ['decision' => 'hide'])
            ->assertRedirectToRoute('admin.forum.reports.index')
            ->assertSessionHas('status', 'The reported thread is hidden and 2 reports about it are resolved.');

        expect($thread->refresh()->status)->toBe(ForumContentStatus::Hidden);
        $reports->each(fn (ForumReport $report) => expect($report->refresh())
            ->resolution->toBe(ForumReport::RESOLUTION_CONTENT_HIDDEN)
            ->resolved_by->toBe($admin->id));
        expect($replyReport->refresh()->resolved_at)->toBeNull();
        expect($dismissed->refresh()->resolution)->toBe(ForumReport::RESOLUTION_DISMISSED);
    });

    it('hides a reported reply and keeps its thread', function () {
        $reply = ForumReply::factory()->create();
        $report = ForumReport::factory()->for($reply, 'reply')->create();
        $threadReport = ForumReport::factory()->for($reply->thread, 'thread')->create();

        $this->actingAs(User::factory()->admin()->create())
            ->put(route('admin.forum.reports.update', $report), ['decision' => 'hide'])
            ->assertSessionHas('status', 'The reported reply is hidden and the report is resolved.');

        expect($reply->refresh()->status)->toBe(ForumContentStatus::Hidden);
        expect($reply->thread->refresh()->status)->toBe(ForumContentStatus::Published);
        expect($report->refresh()->resolution)->toBe(ForumReport::RESOLUTION_CONTENT_HIDDEN);
        expect($threadReport->refresh()->resolved_at)->toBeNull();
    });

    it('refuses a report that was already resolved', function () {
        $report = ForumReport::factory()->resolved()->create();
        $resolvedBy = $report->resolved_by;

        $this->actingAs(User::factory()->admin()->create())
            ->put(route('admin.forum.reports.update', $report), ['decision' => 'hide'])
            ->assertSessionHas('error', ForumModeration::REPORT_RESOLVED_MESSAGE);

        expect($report->refresh())
            ->resolution->toBe(ForumReport::RESOLUTION_DISMISSED)
            ->resolved_by->toBe($resolvedBy);
        expect($report->thread->refresh()->status)->toBe(ForumContentStatus::Published);
    });

    it('validates the decision', function (array $input) {
        $report = ForumReport::factory()->create();

        $this->actingAs(User::factory()->admin()->create())
            ->put(route('admin.forum.reports.update', $report), $input)
            ->assertSessionHasErrors(['decision' => 'Choose whether to dismiss the report or hide the content.']);

        expect($report->refresh()->resolved_at)->toBeNull();
    })->with([
        'missing' => [[]],
        'unknown' => [['decision' => 'delete']],
    ]);

    it('returns 404 for an unknown report', function () {
        $this->actingAs(User::factory()->admin()->create())
            ->put(route('admin.forum.reports.update', 999), ['decision' => 'dismiss'])
            ->assertNotFound();
    });
});
