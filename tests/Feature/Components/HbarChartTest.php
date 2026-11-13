<?php

it('ranks the rows from the largest value with a table twin', function () {
    $html = (string) $this->blade('<x-chart.hbar label="Mission completion" format="percent" :items="$items" />', [
        'items' => [
            ['label' => 'Grade 5 – Rizal', 'value' => 42.5],
            ['label' => 'Grade 6 – Bonifacio', 'value' => 88],
            ['label' => 'Grade 5 – Mabini', 'value' => 61],
        ],
    ]);

    expect($html)->toContain('data-chart="hbar"')
        ->toMatch('/Grade 6 – Bonifacio.*Grade 5 – Mabini.*Grade 5 – Rizal/s')
        ->toContain('style="width: max(4px, 88%)"')
        ->toContain('42.5%')
        ->toContain('rounded-full bg-(--viz)/15');
});

it('keeps the given order when sorting is off and limits the rows', function () {
    $html = (string) $this->blade('<x-chart.hbar :sorted="false" :limit="2" :items="$items" />', [
        'items' => [
            ['label' => 'First', 'value' => 1],
            ['label' => 'Second', 'value' => 5],
            ['label' => 'Third', 'value' => 9],
        ],
    ]);

    expect($html)->toMatch('/First.*Second/s')
        ->not->toContain('Third');
});

it('scales counts against the largest value without a track', function () {
    $html = (string) $this->blade('<x-chart.hbar :items="$items" />', [
        'items' => [
            ['label' => 'Question 4', 'value' => 12, 'sublabel' => 'Most chose “Candy”'],
            ['label' => 'Question 9', 'value' => 6],
        ],
    ]);

    expect($html)->toContain('style="width: max(4px, 100%)"')
        ->toContain('style="width: max(4px, 50%)"')
        ->toContain('Most chose “Candy”')
        ->not->toContain('bg-(--viz)/15');
});

it('links a row label when a url is given', function () {
    $this->blade('<x-chart.hbar :items="[[\'label\' => \'Ana Reyes\', \'value\' => 3, \'url\' => \'/teacher/students/5\']]" />')
        ->assertSee('<a href="/teacher/students/5"', false);
});

it('shows an empty state without values', function () {
    $html = (string) $this->blade('<x-chart.hbar :items="[[\'label\' => \'Nobody\', \'value\' => null]]" empty-text="Nothing to rank yet." />');

    expect($html)->toContain('data-chart-empty')
        ->toContain('Nothing to rank yet.');
});
