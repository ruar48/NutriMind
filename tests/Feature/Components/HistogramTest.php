<?php

it('draws touching buckets with the tallest one labelled', function () {
    $html = (string) $this->blade('<x-chart.histogram label="Score distribution" x-label="Score" :buckets="$buckets" />', [
        'buckets' => [
            ['label' => '0–59', 'value' => 2],
            ['label' => '60–74', 'value' => 5],
            ['label' => '75–89', 'value' => 11, 'color' => 'leaf'],
            ['label' => '90–100', 'value' => 4],
        ],
    ]);

    expect(substr_count($html, 'data-chart-hit'))->toBe(4)
        ->and($html)->toContain('data-chart="histogram"')
        ->toContain('absolute inset-0 flex items-end gap-[2px]')
        ->toContain('data-viz="leaf"')
        ->toContain('data-viz="1"')
        ->toMatch('/absolute bottom-full[^>]*>11<\/span>/')
        ->toContain('<th scope="col" class="px-3 py-2 font-semibold whitespace-nowrap">Score</th>')
        ->toContain('<td class="px-3 py-2 text-right whitespace-nowrap text-ink-700 tabular-nums dark:text-ink-200">5</td>');
});

it('scales the axis to a clean count step', function () {
    $html = (string) $this->blade('<x-chart.histogram :buckets="[[\'label\' => \'A\', \'value\' => 7], [\'label\' => \'B\', \'value\' => 3]]" />');

    expect($html)->toMatch('/top: 0%">8<\/span>/')
        ->toContain('style="height: max(2px, 87.5%)"');
});

it('shows an empty state when every bucket is zero', function () {
    $html = (string) $this->blade('<x-chart.histogram :buckets="[[\'label\' => \'0–59\', \'value\' => 0]]" empty-text="No attempts yet." />');

    expect($html)->toContain('data-chart-empty')
        ->toContain('No attempts yet.')
        ->not->toContain('data-chart-data');
});
