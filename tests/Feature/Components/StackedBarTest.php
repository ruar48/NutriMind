<?php

use App\Enums\PerformanceBand;

it('splits one whole into proportional blocks with shares printed where they fit', function () {
    $html = (string) $this->blade('<x-chart.stacked-bar label="Performance bands" :segments="$segments" />', [
        'segments' => [
            ['label' => PerformanceBand::Outstanding->label(), 'value' => 6, 'color' => PerformanceBand::Outstanding->color()],
            ['label' => PerformanceBand::VerySatisfactory->label(), 'value' => 3, 'color' => PerformanceBand::VerySatisfactory->color()],
            ['label' => PerformanceBand::DidNotMeetExpectations->label(), 'value' => 1, 'color' => PerformanceBand::DidNotMeetExpectations->color()],
        ],
    ]);

    expect($html)->toContain('data-chart="stacked"')
        ->toContain('style="flex: 6 1 0%"')
        ->toContain('style="flex: 1 1 0%"')
        ->toContain('@container')
        ->toContain('@min-[2.75rem]:block')
        ->toContain('data-viz="teal"')
        ->toContain('aria-label="Performance bands: Outstanding 6 (60%), Very Satisfactory 3 (30%), Did Not Meet Expectations 1 (10%)"');
});

it('draws nothing for a zero part but keeps it in the legend and table', function () {
    $html = (string) $this->blade('<x-chart.stacked-bar :segments="$segments" />', [
        'segments' => [
            ['label' => 'Completed', 'value' => 4, 'color' => 'leaf'],
            ['label' => 'Not started', 'value' => 0, 'color' => 'slate'],
        ],
    ]);

    expect(substr_count($html, 'data-chart-mark'))->toBe(1)
        ->and($html)->toContain('<span>Not started</span>')
        ->toMatch('/<th scope="row"[^>]*>Not started<\/th>/');
});

it('renders the thin variant without printed values but with figures in the legend', function () {
    $html = (string) $this->blade('<x-chart.stacked-bar variant="thin" :segments="$segments" />', [
        'segments' => [
            ['label' => 'Completed', 'value' => 3],
            ['label' => 'In progress', 'value' => 1],
        ],
    ]);

    expect($html)->toContain('h-2 overflow-hidden rounded-full')
        ->not->toContain('@min-[2.75rem]:block')
        ->toContain('3 · 75%')
        ->toContain('1 · 25%');
});

it('shows an empty state when the whole is zero', function () {
    $html = (string) $this->blade('<x-chart.stacked-bar :segments="[]" empty-text="No learners in this class yet." />');

    expect($html)->toContain('data-chart-empty')
        ->toContain('No learners in this class yet.')
        ->not->toContain('data-chart-mark');
});
