<?php

it('draws one segment per part with the total in the center', function () {
    $html = (string) $this->blade('<x-chart.donut label="Learners by status" center-label="learners" :items="$items" />', [
        'items' => [
            ['label' => 'Completed', 'value' => 6, 'color' => 'leaf'],
            ['label' => 'In progress', 'value' => 3, 'color' => 'sky'],
            ['label' => 'Not started', 'value' => 1, 'color' => 'slate'],
        ],
    ]);

    expect(preg_match_all('/<circle\s+data-chart-mark/', $html))->toBe(3)
        ->and($html)->toContain('data-chart="donut"')
        ->toMatch('/font-display[^>]*>10<\/p>/')
        ->toContain('learners')
        ->toContain('data-viz="leaf"')
        ->toContain('60%')
        ->toContain('30%');
});

it('folds parts beyond five into Other', function () {
    $html = (string) $this->blade('<x-chart.donut :items="$items" />', [
        'items' => collect(range(1, 7))->map(fn (int $i) => ['label' => 'Part '.$i, 'value' => $i])->all(),
    ]);

    expect($html)->toContain('Part 4')
        ->not->toContain('Part 5')
        ->toContain('Other')
        ->toContain('data-viz="other"')
        ->toMatch('/Other<\/span>\s*<span[^>]*>18<\/span>/');
});

it('lists value and share in the table twin', function () {
    $html = (string) $this->blade('<x-chart.donut label="Rewards by type" :items="$items" />', [
        'items' => [
            ['label' => 'Badge', 'value' => 3],
            ['label' => 'Trophy', 'value' => 1],
        ],
    ]);

    expect($html)->toContain('<th scope="col" class="px-3 py-2 font-semibold whitespace-nowrap text-right">Share</th>')
        ->toContain('75%')
        ->toContain('25%');
});

it('shows an empty state when every part is zero', function () {
    $html = (string) $this->blade('<x-chart.donut :items="[[\'label\' => \'Badge\', \'value\' => 0]]" empty-text="No rewards earned yet." />');

    expect($html)->toContain('data-chart-empty')
        ->toContain('No rewards earned yet.')
        ->not->toContain('data-chart-mark');
});
