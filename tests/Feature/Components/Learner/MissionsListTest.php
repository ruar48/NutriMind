<?php

use App\Models\Mission;
use App\Services\LearnerProgressService;
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

it('lists each mission with its type, subject, status, progress and points', function () {
    $summary = app(LearnerProgressService::class)->summarize(LearnerProgressFixture::create()->student);

    $view = $this->blade('<x-learner.missions-list :missions="$missions" />', ['missions' => $summary->recentMissions]);

    $view->assertSeeText('Missions')
        ->assertSeeTextInOrder([
            'Snack Swap Hero', 'Challenge', 'Health', 'In progress', 'Progress', '40%', 'Updated',
            'Rainbow Plate', 'Story mission', 'Health', 'Completed', 'Progress', '100%', 'Completed', '+100 XP',
            'Water Warrior', 'Daily', 'Science', 'Completed',
            'Label Reader', 'Daily', 'Science', 'Not started', '0%',
            'Market Explorer', 'Weekly', 'Mathematics', 'Completed',
        ])
        ->assertSee('title="Nov 13, 2026 7:00 AM">3 hours ago</time>', false)
        ->assertSee('title="Nov 12, 2026 8:00 AM">1 day ago</time>', false);
});

it('shows an empty state when the learner has not started a mission', function () {
    $summary = app(LearnerProgressService::class)->summarize(LearnerProgressFixture::newcomer());

    $view = $this->blade('<x-learner.missions-list :missions="$missions" />', ['missions' => $summary->recentMissions]);

    $view->assertSeeText('No missions started yet')
        ->assertSeeText('Missions appear here once the learner starts them in the game.');
});

it('accepts a custom title, description, header actions and root attributes', function () {
    $summary = app(LearnerProgressService::class)->summarize(LearnerProgressFixture::newcomer());

    $view = $this->blade(
        '<x-learner.missions-list :missions="$missions" title="Health missions" description="Your subject only" class="xl:col-span-2"><x-slot:actions><a href="/missions">All missions</a></x-slot:actions></x-learner.missions-list>',
        ['missions' => $summary->recentMissions],
    );

    $view->assertSeeText('Health missions')
        ->assertSeeText('Your subject only')
        ->assertDontSeeText('The learner’s latest mission progress in the game')
        ->assertSee('<a href="/missions">All missions</a>', false)
        ->assertSee('xl:col-span-2', false);
});

it('escapes mission titles', function () {
    $fixture = LearnerProgressFixture::create();
    Mission::query()->whereKey($fixture->missions['snackSwap']->mission_id)->update(['title' => '<b onclick="x()">Snack</b>']);
    $summary = app(LearnerProgressService::class)->summarize($fixture->student);

    $view = $this->blade('<x-learner.missions-list :missions="$missions" />', ['missions' => $summary->recentMissions]);

    $view->assertSee('&lt;b onclick=&quot;x()&quot;&gt;Snack&lt;/b&gt;', false)
        ->assertDontSee('<b onclick="x()">', false);
});
