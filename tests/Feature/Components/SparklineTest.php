<?php

it('draws the trend in the de-emphasis ink with an accent end dot', function () {
    $html = (string) $this->blade('<x-chart.sparkline label="Weekly average" format="percent" color="grape" :data="[70, 72.5, 81]" />');

    expect($html)->toContain('data-chart="sparkline"')
        ->toContain('stroke-ink-300')
        ->toContain('data-viz="grape"')
        ->toContain('aria-label="Weekly average: from 70% to 81%"')
        ->toContain('Values: 70%, 72.5%, 81%');
});

it('passes the padded domain to the hover layer', function () {
    $html = (string) $this->blade('<x-chart.sparkline :data="[10, 20]" />');

    preg_match('/data-chart-data>(.*?)<\/script>/s', $html, $matches);
    $payload = json_decode($matches[1], true);

    expect($payload['min'])->toEqual(8.8)
        ->and($payload['max'])->toEqual(21.2)
        ->and($payload['series'][0]['values'])->toEqual([10, 20]);
});

it('shows a short note instead of a line with fewer than two points', function (array $data) {
    $html = (string) $this->blade('<x-chart.sparkline :data="$data" />', ['data' => $data]);

    expect($html)->toContain('data-sparkline-empty')
        ->toContain('No trend yet')
        ->not->toContain('data-chart-data');
})->with([
    'no points' => [[]],
    'one point' => [[42]],
    'non-numeric points' => [['n/a', null]],
]);
