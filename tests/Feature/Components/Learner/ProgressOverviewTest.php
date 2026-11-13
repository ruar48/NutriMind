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

it('renders the score, quiz, mission and activity tiles', function () {
    $summary = app(LearnerProgressService::class)->summarize(LearnerProgressFixture::create()->student);

    $view = $this->blade('<x-learner.progress-overview :summary="$summary" />', ['summary' => $summary]);

    $view->assertSeeTextInOrder([
        'Average score', '73.8%', 'Did Not Meet Expectations',
        'Quizzes taken', '4', '2 passed',
        'Missions completed', '3', '1 in progress',
        'Activities completed', '2', 'of 4 available', 'Completion', '50%',
    ])
        ->assertSee('data-count-up="73.8"', false)
        ->assertSee('data-count-decimals="1"', false);
});

it('drops a trailing zero decimal from the average score', function () {
    $fixture = LearnerProgressFixture::create();
    $summary = app(LearnerProgressService::class)->summarize($fixture->student, [$fixture->health->id]);

    $view = $this->blade('<x-learner.progress-overview :summary="$summary" />', ['summary' => $summary]);

    $view->assertSeeText('75%')
        ->assertDontSeeText('75.0%')
        ->assertSeeText('Fairly Satisfactory');
});

it('renders empty tiles for a learner without progress', function () {
    $summary = app(LearnerProgressService::class)->summarize(LearnerProgressFixture::newcomer());

    $view = $this->blade('<x-learner.progress-overview :summary="$summary" />', ['summary' => $summary]);

    $view->assertSeeTextInOrder(['Average score', '—', 'No quiz attempts yet', '0 passed', '0 in progress', 'No activities for this grade yet'])
        ->assertDontSeeText('Completion')
        ->assertDontSee('role="progressbar"', false);
});
