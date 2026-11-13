<?php

/**
 * Decode the JSON payload a chart hands to its hover layer.
 *
 * @return array<string, mixed>
 */
function lineChartPayload(string $html): array
{
    preg_match('/<script type="application\/json" data-chart-data>(.*?)<\/script>/s', $html, $matches);

    return json_decode($matches[1] ?? 'null', true) ?? [];
}

it('draws one series with an area wash, end dot and table twin', function () {
    $html = (string) $this->blade('<x-chart.line label="Average score" format="percent" :labels="$labels" :series="$series" />', [
        'labels' => ['Week 1', 'Week 2', 'Week 3'],
        'series' => [['name' => 'Average', 'data' => [72, 80.5, 86.25]]],
    ]);

    expect($html)->toContain('data-chart="line"')
        ->toContain('fill-opacity="0.1"')
        ->toContain('vector-effect="non-scaling-stroke"')
        ->toContain('<th scope="row" class="px-3 py-2 font-medium whitespace-nowrap text-ink-800 dark:text-ink-100">Week 2</th>')
        ->toContain('80.5%')
        ->toContain('86.3%')
        ->not->toContain('data-chart-legend-item');
});

it('scales percent charts from zero to one hundred', function () {
    $payload = lineChartPayload((string) $this->blade('<x-chart.line format="percent" :labels="[\'A\', \'B\']" :series="$series" />', [
        'series' => [['name' => 'Score', 'data' => [40, 60]]],
    ]));

    expect($payload['min'])->toEqual(0)
        ->and($payload['max'])->toEqual(100)
        ->and($payload['format'])->toBe('percent')
        ->and($payload['labels'])->toBe(['A', 'B'])
        ->and($payload['series'][0])->toEqual(['name' => 'Score', 'viz' => '1', 'values' => [40, 60]]);
});

it('rounds a number axis up to a clean step', function () {
    $html = (string) $this->blade('<x-chart.line :labels="[\'Mon\', \'Tue\', \'Wed\']" :series="$series" />', [
        'series' => [['name' => 'Attempts', 'data' => [3, 17, 37]]],
    ]);

    expect(lineChartPayload($html)['max'])->toEqual(40)
        ->and($html)->toMatch('/top: 0%">40<\/span>/');
});

it('assigns categorical slots in order and adds a legend for two or more series', function () {
    $html = (string) $this->blade('<x-chart.line :labels="[\'Q1\', \'Q2\']" :series="$series" />', [
        'series' => [
            ['name' => 'Grade 5', 'data' => [70, 75]],
            ['name' => 'Grade 6', 'data' => [80, 82]],
        ],
    ]);

    expect($html)->toContain('data-viz="1"')
        ->toContain('data-viz="2"')
        ->toMatch('/<ul class="[^"]*mb-4[^"]*">/')
        ->toContain('Grade 6');
});

it('keeps an entity color stable through an explicit slot or palette key', function () {
    $payload = lineChartPayload((string) $this->blade('<x-chart.line :labels="[\'Q1\']" :series="$series" />', [
        'series' => [
            ['name' => 'Health', 'data' => [80], 'slot' => 4],
            ['name' => 'Science', 'data' => [70], 'color' => 'carrot'],
        ],
    ]));

    expect(collect($payload['series'])->pluck('viz')->all())->toBe(['4', 'carrot']);
});

it('breaks the line where values are missing', function () {
    $html = (string) $this->blade('<x-chart.line :labels="[\'W1\', \'W2\', \'W3\', \'W4\']" :series="$series" />', [
        'series' => [['name' => 'Score', 'data' => [50, 60, null, 70]]],
    ]);

    expect($html)->toMatch('/<path d="M0 \S+ L33\.333 \S+"/')
        ->toContain('—');
});

it('draws a labelled target line', function () {
    $html = (string) $this->blade('<x-chart.line format="percent" :labels="[\'W1\', \'W2\']" :series="$series" :target="75" target-label="Passing" />', [
        'series' => [['name' => 'Score', 'data' => [60, 90]]],
    ]);

    expect($html)->toContain('stroke-dasharray="5 4"')
        ->toContain('Passing 75%');
});

it('shows an empty state instead of the plot when there is no data', function (array $labels, array $series) {
    $html = (string) $this->blade('<x-chart.line :labels="$labels" :series="$series" empty-text="No attempts yet." />', [
        'labels' => $labels,
        'series' => $series,
    ]);

    expect($html)->toContain('data-chart-empty')
        ->toContain('No attempts yet.')
        ->not->toContain('data-chart-data')
        ->not->toContain('data-chart-plot');
})->with([
    'no labels' => [[], []],
    'only missing values' => [['W1', 'W2'], [['name' => 'Score', 'data' => [null, null]]]],
]);

it('escapes labels in the markup and the payload', function () {
    $html = (string) $this->blade('<x-chart.line :labels="$labels" :series="$series" />', [
        'labels' => ['<b>W1</b>'],
        'series' => [['name' => '<script>alert(1)</script>', 'data' => [5]]],
    ]);

    expect($html)->not->toContain('<script>alert(1)</script>')
        ->not->toContain('<b>W1</b>')
        ->toContain('&lt;b&gt;W1&lt;/b&gt;');
});
