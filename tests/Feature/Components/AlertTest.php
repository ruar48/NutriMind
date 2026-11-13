<?php

it('pairs each variant with its role, icon and tone', function (string $variant, string $role, string $icon, string $tone) {
    $this->blade('<x-alert :variant="$variant" title="Heads up">Message</x-alert>', ['variant' => $variant])
        ->assertSee('role="'.$role.'"', false)
        ->assertSee('data-icon="'.$icon.'"', false)
        ->assertSee($tone, false)
        ->assertSeeText('Heads up')
        ->assertSeeText('Message');
})->with([
    'info' => ['info', 'status', 'info', 'bg-sky-50'],
    'success' => ['success', 'status', 'circle-check', 'bg-leaf-50'],
    'warning' => ['warning', 'alert', 'triangle-alert', 'bg-sun-50'],
    'danger' => ['danger', 'alert', 'circle-alert', 'bg-berry-50'],
]);

it('falls back to the info variant', function () {
    $this->blade('<x-alert variant="unknown">Message</x-alert>')
        ->assertSee('bg-sky-50', false);
});

it('adds a dismiss button that only appears with JavaScript', function () {
    $html = (string) $this->blade('<x-alert dismissible>Saved</x-alert>');

    expect($html)->toContain('data-dismissible')
        ->toMatch('/<button type="button" data-dismiss data-js-only/')
        ->toContain('<span class="sr-only">Dismiss</span>');
});

it('uses a custom icon', function () {
    $this->blade('<x-alert icon="wifi-off">Offline attempts are syncing.</x-alert>')
        ->assertSee('data-icon="wifi-off"', false);
});
