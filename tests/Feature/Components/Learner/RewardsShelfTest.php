<?php

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

it('shows the earned rewards latest first with their award dates', function () {
    $summary = app(LearnerProgressService::class)->summarize(LearnerProgressFixture::create()->student);

    $view = $this->blade('<x-learner.rewards-shelf :rewards="$rewards" />', ['rewards' => $summary->rewards]);

    $view->assertSeeText('Rewards')
        ->assertSeeText('2 rewards earned')
        ->assertSeeTextInOrder(['Hydration Hero', 'Nov 10, 2026', 'Breakfast Champion', 'Nov 1, 2026'])
        ->assertSee('data-earned="true"', false)
        ->assertDontSee('data-earned="false"', false);
});

it('shows an empty state when the learner has no rewards', function () {
    $summary = app(LearnerProgressService::class)->summarize(LearnerProgressFixture::newcomer());

    $view = $this->blade('<x-learner.rewards-shelf :rewards="$rewards" />', ['rewards' => $summary->rewards]);

    $view->assertSeeText('No rewards yet')
        ->assertSeeText('Badges, trophies and avatar items earned in the game')
        ->assertDontSee('data-earned', false);
});

it('uses the given title and description instead of the earned count', function () {
    $summary = app(LearnerProgressService::class)->summarize(LearnerProgressFixture::create()->student);

    $view = $this->blade(
        '<x-learner.rewards-shelf :rewards="$rewards" title="Badges" description="Collected in NutriMind" class="xl:col-span-2"><x-slot:actions><a href="/rewards">All rewards</a></x-slot:actions></x-learner.rewards-shelf>',
        ['rewards' => $summary->rewards],
    );

    $view->assertSeeText('Badges')
        ->assertSeeText('Collected in NutriMind')
        ->assertDontSeeText('2 rewards earned')
        ->assertSee('<a href="/rewards">All rewards</a>', false)
        ->assertSee('xl:col-span-2', false);
});

it('describes a single earned reward in the singular', function () {
    $summary = app(LearnerProgressService::class)->summarize(LearnerProgressFixture::create()->student);

    $view = $this->blade('<x-learner.rewards-shelf :rewards="$rewards" />', ['rewards' => $summary->rewards->take(1)]);

    $view->assertSeeText('1 reward earned')
        ->assertSeeText('Hydration Hero')
        ->assertDontSeeText('Breakfast Champion');
});
