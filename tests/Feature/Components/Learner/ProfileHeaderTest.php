<?php

use App\Services\LearnerProgressService;
use App\Services\Settings;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Tests\Fixtures\LearnerProgressFixture;

beforeEach(function () {
    $this->lazyLoadingWasPrevented = Model::preventsLazyLoading();
    Model::preventLazyLoading();
    $this->travelTo(CarbonImmutable::parse(LearnerProgressFixture::NOW));
});

afterEach(function () {
    Model::preventLazyLoading($this->lazyLoadingWasPrevented);
});

it('renders the learner’s identity, classrooms, level and activity times', function () {
    $summary = app(LearnerProgressService::class)->summarize(LearnerProgressFixture::create()->student);

    $view = $this->blade('<x-learner.profile-header :summary="$summary" />', ['summary' => $summary]);

    $view->assertSeeText('Juan Dela Cruz')
        ->assertSeeText('LRN 100000000001')
        ->assertSeeText('Username jdelacruz')
        ->assertSeeText('Grade 5')
        ->assertSeeText('Health 5 – Sampaguita')
        ->assertSeeText('Science 5 – Rosal')
        ->assertDontSeeText('Math 5 – Mabini')
        ->assertSee('title="Health · Health 5 – Sampaguita"', false)
        ->assertSee('aria-label="Level 3"', false)
        ->assertSeeText('1,240')
        ->assertSeeText('240 / 500 XP')
        ->assertSeeText('260 XP to level 4')
        ->assertSee('title="Nov 12, 2026 6:00 PM">16 hours ago</time>', false)
        ->assertSee('title="Nov 12, 2026 6:05 PM">15 hours ago</time>', false)
        ->assertDontSeeText('Sync overdue')
        ->assertDontSeeText('Game progress may be out of date');
});

it('renders a learner who has not played or joined a classroom yet', function () {
    $summary = app(LearnerProgressService::class)->summarize(LearnerProgressFixture::newcomer());

    $view = $this->blade('<x-learner.profile-header :summary="$summary" />', ['summary' => $summary]);

    $view->assertSeeText('Bea Santos')
        ->assertSeeText('No LRN on file')
        ->assertSeeText('Not enrolled in a classroom')
        ->assertSee('aria-label="Level 1"', false)
        ->assertSeeText('0 / 500 XP')
        ->assertSeeText('Not played yet')
        ->assertSeeText('Never synced')
        ->assertDontSeeText('Sync overdue');
});

it('warns when the game has not synced for more than seven days', function () {
    $student = LearnerProgressFixture::newcomer(['last_active_at' => '2026-11-04 08:00:00', 'last_synced_at' => '2026-11-04 09:00:00']);
    $summary = app(LearnerProgressService::class)->summarize($student);

    $view = $this->blade('<x-learner.profile-header :summary="$summary" />', ['summary' => $summary]);

    $view->assertSeeText('Sync overdue')
        ->assertSee('role="alert"', false)
        ->assertSeeText('Game progress may be out of date')
        ->assertSeeText('The game last sent progress 1 week ago.');
});

it('warns when the learner played but the game never synced', function () {
    $summary = app(LearnerProgressService::class)->summarize(LearnerProgressFixture::newcomer(['last_active_at' => '2026-11-12 08:00:00']));

    $view = $this->blade('<x-learner.profile-header :summary="$summary" />', ['summary' => $summary]);

    $view->assertSeeText('Never synced')
        ->assertSeeText('Sync overdue')
        ->assertSeeText('The game has not sent this learner’s progress yet.');
});

it('shows the maximum level from the gamification settings', function () {
    app(Settings::class)->set('max_level', 3);
    $summary = app(LearnerProgressService::class)->summarize(LearnerProgressFixture::create()->student);

    $view = $this->blade('<x-learner.profile-header :summary="$summary" />', ['summary' => $summary]);

    $view->assertSee('aria-label="Level 3"', false)
        ->assertSeeText('Max level')
        ->assertDontSeeText('to level 4');
});

it('escapes the learner’s name', function () {
    $summary = app(LearnerProgressService::class)->summarize(LearnerProgressFixture::newcomer(name: '<script>alert("x")</script> Cruz'));

    $view = $this->blade('<x-learner.profile-header :summary="$summary" />', ['summary' => $summary]);

    $view->assertSee('&lt;script&gt;', false)
        ->assertDontSee('<script>alert("x")</script>', false);
});
