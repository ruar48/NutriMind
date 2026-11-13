<?php

it('draws one capped bar per category with its table twin', function () {
    $html = (string) $this->blade('<x-chart.bar label="Average by subject" format="percent" :labels="$labels" :series="$series" />', [
        'labels' => ['Health', 'Science', 'PE'],
        'series' => [['name' => 'Average', 'data' => [82, 74.5, 91]]],
    ]);

    expect(substr_count($html, 'data-chart-hit'))->toBe(3)
        ->and($html)->toContain('data-chart="bar"')
        ->toContain('max-w-6 rounded-t-[4px]')
        ->toContain('style="height: max(2px, 82%)"')
        ->toContain('74.5%')
        ->toContain('<th scope="row" class="px-3 py-2 font-medium whitespace-nowrap text-ink-800 dark:text-ink-100">PE</th>');
});

it('labels only the tallest bar by default', function () {
    $html = (string) $this->blade('<x-chart.bar :labels="[\'A\', \'B\', \'C\']" :series="$series" />', [
        'series' => [['name' => 'Learners', 'data' => [4, 9, 6]]],
    ]);

    expect(substr_count($html, 'absolute bottom-full left-1/2'))->toBe(1)
        ->and($html)->toMatch('/absolute bottom-full[^>]*>9<\/span>/');
});

it('labels every bar when asked', function () {
    $html = (string) $this->blade('<x-chart.bar value-labels="all" :labels="[\'A\', \'B\']" :series="$series" />', [
        'series' => [['name' => 'Learners', 'data' => [4, 9]]],
    ]);

    expect(substr_count($html, 'absolute bottom-full left-1/2'))->toBe(2);
});

it('groups at most three series with a legend', function () {
    $html = (string) $this->blade('<x-chart.bar :labels="[\'Q1\']" :series="$series" />', [
        'series' => [
            ['name' => 'Grade 5', 'data' => [70]],
            ['name' => 'Grade 6', 'data' => [80]],
            ['name' => 'All', 'data' => [75]],
            ['name' => 'Ignored', 'data' => [99]],
        ],
    ]);

    expect(substr_count($html, 'data-chart-mark'))->toBe(3)
        ->and($html)->not->toContain('Ignored')
        ->toContain('data-viz="3"');
});

it('shows an empty state without data', function () {
    $html = (string) $this->blade('<x-chart.bar :labels="[]" :series="[]" empty-text="No quizzes taken yet." />');

    expect($html)->toContain('data-chart-empty')
        ->toContain('No quizzes taken yet.')
        ->not->toContain('data-chart-data');
});
