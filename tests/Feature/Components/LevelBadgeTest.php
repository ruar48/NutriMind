<?php

it('picks the tier color from the level', function (int $level, string $tier, string $faceClass) {
    $this->blade('<x-level-badge :level="$level" />', ['level' => $level])
        ->assertSee('data-tier="'.$tier.'"', false)
        ->assertSee($faceClass, false)
        ->assertSee('aria-label="Level '.$level.'"', false)
        ->assertSeeText((string) $level);
})->with([
    'first level' => [1, 'leaf', 'fill-leaf-600'],
    'just below the sky tier' => [9, 'leaf', 'fill-leaf-600'],
    'sky tier' => [10, 'sky', 'fill-sky-700'],
    'grape tier' => [20, 'grape', 'fill-grape-600'],
    'berry tier' => [30, 'berry', 'fill-berry-700'],
    'gold tier' => [40, 'sun', 'fill-sun-400'],
]);

it('uses dark digits on the light gold face', function () {
    $this->blade('<x-level-badge :level="45" />')
        ->assertSee('text-sun-950', false);
});

it('lets a color override the tier', function () {
    $this->blade('<x-level-badge :level="3" color="grape" />')
        ->assertSee('data-tier="grape"', false)
        ->assertSee('fill-grape-600', false);
});

it('never shows a level below one', function () {
    $this->blade('<x-level-badge :level="0" />')
        ->assertSee('aria-label="Level 1"', false);
});

it('sizes the hexagon from the size prop', function () {
    $this->blade('<x-level-badge :level="5" size="xl" />')
        ->assertSee('h-24 w-[5.45rem] text-4xl', false);
});
