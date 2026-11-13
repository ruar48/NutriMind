<?php

it('maps every palette key to its literal tone classes', function (string $color, string $expectedClasses) {
    $this->blade('<x-badge :color="$color">Label</x-badge>', ['color' => $color])
        ->assertSee($expectedClasses, false)
        ->assertSee('data-color="'.$color.'"', false);
})->with([
    'leaf' => ['leaf', 'bg-leaf-50 text-leaf-700 ring-leaf-600/20'],
    'carrot' => ['carrot', 'bg-carrot-50 text-carrot-700 ring-carrot-600/20'],
    'sun' => ['sun', 'bg-sun-50 text-sun-800 ring-sun-600/25'],
    'berry' => ['berry', 'bg-berry-50 text-berry-700 ring-berry-600/20'],
    'grape' => ['grape', 'bg-grape-50 text-grape-700 ring-grape-600/20'],
    'sky' => ['sky', 'bg-sky-50 text-sky-700 ring-sky-600/20'],
    'teal' => ['teal', 'bg-teal-50 text-teal-700 ring-teal-600/20'],
    'lime' => ['lime', 'bg-lime-50 text-lime-800 ring-lime-600/25'],
    'pink' => ['pink', 'bg-pink-50 text-pink-700 ring-pink-600/20'],
    'indigo' => ['indigo', 'bg-indigo-50 text-indigo-700 ring-indigo-600/20'],
    'slate' => ['slate', 'bg-slate-100 text-slate-700 ring-slate-500/20'],
]);

it('falls back to slate for an unknown color', function () {
    $this->blade('<x-badge color="neon">Label</x-badge>')
        ->assertSee('data-color="slate"', false)
        ->assertSee('bg-slate-100', false);
});

it('renders a status dot when asked', function () {
    $this->blade('<x-badge color="leaf" dot>Active</x-badge>')
        ->assertSee('size-1.5 rounded-full bg-leaf-500', false);
});

it('prefers an icon over the dot', function () {
    $view = $this->blade('<x-badge color="leaf" icon="check" dot>Active</x-badge>');

    $view->assertSee('data-icon="check"', false)
        ->assertDontSee('size-1.5 rounded-full', false);
});
