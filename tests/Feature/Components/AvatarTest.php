<?php

it('builds initials from the first and last name', function (string $name, string $initials) {
    $this->blade('<x-avatar :name="$name" />', ['name' => $name])
        ->assertSee('<span class="leading-none">'.$initials.'</span>', false);
})->with([
    'two words' => ['Maria Santos', 'MS'],
    'three words' => ['Juan dela Cruz', 'JC'],
    'one word' => ['Lorna', 'LO'],
    'accented letters' => ['Ñino Ábalos', 'ÑÁ'],
    'empty name' => ['', '?'],
]);

it('gives the same name the same color every time', function () {
    $first = (string) $this->blade('<x-avatar name="Ana Reyes" />');
    $second = (string) $this->blade('<x-avatar name="  ana reyes " />');

    preg_match('/data-tone="([a-z]+)"/', $first, $tone);

    expect($tone[1] ?? null)->toBeIn(['leaf', 'carrot', 'sun', 'berry', 'grape', 'sky', 'teal', 'lime', 'pink', 'indigo'])
        ->and($second)->toContain('data-tone="'.$tone[1].'"');
});

it('shows a photo instead of initials', function () {
    $html = (string) $this->blade('<x-avatar name="Ana Reyes" src="/storage/avatars/ana.jpg" />');

    expect($html)->toContain('<img src="/storage/avatars/ana.jpg" alt=""')
        ->not->toContain('leading-none');
});

it('adds a status dot from a palette key', function () {
    $this->blade('<x-avatar name="Ana Reyes" status="leaf" />')
        ->assertSee('bg-leaf-500', false);
});

it('is decorative unless asked to be labelled', function () {
    $this->blade('<x-avatar name="Ana Reyes" />')->assertSee('aria-hidden="true"', false);

    $this->blade('<x-avatar name="Ana Reyes" :decorative="false" />')
        ->assertSee('role="img"', false)
        ->assertSee('aria-label="Ana Reyes"', false);
});

it('stacks avatars and summarizes the rest', function () {
    $html = (string) $this->blade('<x-avatar-stack :names="[\'Ana\', \'Ben\', \'Cora\', \'Dan\', \'Eva\', \'Fe\']" :max="4" />');

    expect(substr_count($html, 'data-tone='))->toBe(4)
        ->and($html)->toContain('+2')
        ->toContain('aria-label="Ana, Ben, Cora, Dan and 2 others"');
});
