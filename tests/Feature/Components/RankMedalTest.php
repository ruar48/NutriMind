<?php

it('awards medals to the top three', function (int $rank, string $medal, string $label) {
    $this->blade('<x-rank-medal :rank="$rank" />', ['rank' => $rank])
        ->assertSee('data-medal="'.$medal.'"', false)
        ->assertSee('aria-label="'.$label.'"', false);
})->with([
    'first' => [1, 'gold', '1st place, Gold medal'],
    'second' => [2, 'silver', '2nd place, Silver medal'],
    'third' => [3, 'bronze', '3rd place, Bronze medal'],
]);

it('shows other ranks as a plain number with an ordinal label', function (int $rank, string $label) {
    $html = (string) $this->blade('<x-rank-medal :rank="$rank" />', ['rank' => $rank]);

    expect($html)->toContain('aria-label="'.$label.'"')
        ->not->toContain('data-medal');
})->with([
    'fourth' => [4, '4th place'],
    'eleventh' => [11, '11th place'],
    'twelfth' => [12, '12th place'],
    'thirteenth' => [13, '13th place'],
    'twenty-first' => [21, '21st place'],
    'twenty-second' => [22, '22nd place'],
    'one hundred and third' => [103, '103rd place'],
]);
