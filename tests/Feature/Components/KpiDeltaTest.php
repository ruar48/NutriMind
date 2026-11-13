<?php

it('colors a change by its direction and whether up is good', function (float $value, string $goodWhen, string $direction, string $tone) {
    $this->blade('<x-kpi-delta :value="$value" :good-when="$goodWhen" />', ['value' => $value, 'goodWhen' => $goodWhen])
        ->assertSee('data-direction="'.$direction.'"', false)
        ->assertSee($tone, false);
})->with([
    'rising score' => [4.5, 'up', 'up', 'bg-leaf-50 text-leaf-700'],
    'falling score' => [-3.0, 'up', 'down', 'bg-berry-50 text-berry-700'],
    'rising number of at-risk learners' => [2.0, 'down', 'up', 'bg-berry-50 text-berry-700'],
    'falling number of at-risk learners' => [-2.0, 'down', 'down', 'bg-leaf-50 text-leaf-700'],
    'no change' => [0.0, 'up', 'flat', 'bg-ink-900/5 text-ink-600'],
]);

it('formats the change with its sign and unit', function (float $value, string $format, string $text) {
    $this->blade('<x-kpi-delta :value="$value" :format="$format" />', ['value' => $value, 'format' => $format])
        ->assertSeeText($text);
})->with([
    'percent' => [4.5, 'percent', '+4.5%'],
    'points' => [-2.0, 'points', '−2 pts'],
    'number' => [12.0, 'number', '+12'],
]);

it('spells the change out for screen readers with its comparison label', function () {
    $this->blade('<x-kpi-delta :value="-1.25" format="percent" label="vs last week" />')
        ->assertSee('<span class="sr-only">Down 1.3% vs last week</span>', false)
        ->assertSee('data-icon="trending-down"', false);
});
