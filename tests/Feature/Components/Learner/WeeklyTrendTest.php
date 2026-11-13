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

/**
 * Decode the JSON payload the weekly trend chart hands to its hover layer.
 *
 * @return array<string, mixed>
 */
function weeklyTrendPayload(string $html): array
{
    preg_match('/<script type="application\/json" data-chart-data>(.*?)<\/script>/s', $html, $matches);

    return json_decode($matches[1] ?? 'null', true) ?? [];
}

it('charts the weekly averages against the 75% passing mark', function () {
    $summary = app(LearnerProgressService::class)->summarize(LearnerProgressFixture::create()->student);

    $view = $this->blade('<x-learner.weekly-trend :weeks="$weeks" />', ['weeks' => $summary->weeklyScores]);

    $payload = weeklyTrendPayload((string) $view);
    expect($payload['format'])->toBe('percent');
    expect($payload['labels'])->toBe(['Sep 21', 'Sep 28', 'Oct 5', 'Oct 12', 'Oct 19', 'Oct 26', 'Nov 2', 'Nov 9']);
    expect($payload['series'][0]['name'])->toBe('Average score');
    expect($payload['series'][0]['values'])->toEqual([50, null, null, null, 60, null, 95, 75]);
    $view->assertSeeText('Weekly quiz scores')
        ->assertSeeText('Passing mark 75%')
        ->assertSeeText('5 quiz attempts in the last 8 weeks')
        ->assertDontSee('data-chart-empty', false);
});

it('shows the chart’s empty state when no attempts were submitted', function () {
    $summary = app(LearnerProgressService::class)->summarize(LearnerProgressFixture::newcomer());

    $view = $this->blade('<x-learner.weekly-trend :weeks="$weeks" />', ['weeks' => $summary->weeklyScores]);

    $view->assertSee('data-chart-empty', false)
        ->assertSeeText('Scores appear here once the learner submits quizzes.')
        ->assertSeeText('0 quiz attempts in the last 8 weeks');
});

it('accepts a custom title, description and header actions', function () {
    $summary = app(LearnerProgressService::class)->summarize(LearnerProgressFixture::newcomer());

    $view = $this->blade(
        '<x-learner.weekly-trend :weeks="$weeks" title="Health scores" description="Your subject only" class="lg:col-span-2"><x-slot:actions><a href="/quizzes">All quizzes</a></x-slot:actions></x-learner.weekly-trend>',
        ['weeks' => $summary->weeklyScores],
    );

    $view->assertSeeText('Health scores')
        ->assertSeeText('Your subject only')
        ->assertDontSeeText('Weekly quiz scores')
        ->assertSee('<a href="/quizzes">All quizzes</a>', false)
        ->assertSee('lg:col-span-2', false);
});
