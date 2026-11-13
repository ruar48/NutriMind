<?php

use App\View\Components\Icon;

it('renders a known icon as decorative inline SVG', function () {
    $view = $this->blade('<x-icon name="leaf" class="size-4" />');

    $view->assertSee('<svg', false)
        ->assertSee('data-icon="leaf"', false)
        ->assertSee('aria-hidden="true"', false)
        ->assertSee('viewBox="0 0 24 24"', false)
        ->assertSee('stroke="currentColor"', false)
        ->assertSee('<path d="M11 20A7 7 0 0 1 9.8 6.1C15.5 5 17 4.48 19 2c1 2 2 4.18 2 8 0 5.5-4.78 10-10 10Z"/>', false)
        ->assertSee('class="shrink-0 size-4"', false)
        ->assertDontSee('data-icon-fallback', false);
});

it('exposes a labelled icon as an image', function () {
    $view = $this->blade('<x-icon name="trophy" label="Top scorer" />');

    $view->assertSee('role="img"', false)
        ->assertSee('aria-label="Top scorer"', false)
        ->assertDontSee('aria-hidden', false);
});

it('renders the neutral placeholder for an unknown name without throwing', function () {
    $view = $this->blade('<x-icon name="definitely-not-an-icon" />');

    $view->assertSee('data-icon="definitely-not-an-icon"', false)
        ->assertSee('data-icon-fallback', false)
        ->assertSee('<path d="M10.1 2.182a10 10 0 0 1 3.8 0"/>', false);
});

it('lets callers override the size and stroke width', function () {
    $this->blade('<x-icon name="star" :size="16" stroke-width="2.5" />')
        ->assertSee('width="16"', false)
        ->assertSee('height="16"', false)
        ->assertSee('stroke-width="2.5"', false);
});

it('reports which names belong to the set', function () {
    expect(Icon::has('layout-dashboard'))->toBeTrue()
        ->and(Icon::has('house-heart'))->toBeTrue()
        ->and(Icon::has('definitely-not-an-icon'))->toBeFalse()
        ->and(Icon::names())->toContain('chart-no-axes-combined', 'gamepad-2', 'circle-dashed');
});

it('contains every icon the brief and the enums ask for', function () {
    $required = [
        'layout-dashboard', 'home', 'users', 'user', 'user-plus', 'user-check', 'user-x', 'user-cog', 'users-round',
        'graduation-cap', 'school', 'calendar', 'calendar-days', 'calendar-check', 'clock', 'timer', 'book-open', 'book',
        'notebook-pen', 'library', 'layers', 'layout-grid', 'list', 'list-checks', 'list-ordered', 'puzzle', 'target',
        'crosshair', 'trophy', 'award', 'medal', 'crown', 'star', 'sparkles', 'flame', 'zap', 'rocket', 'gamepad-2', 'map',
        'compass', 'flag', 'heart', 'heart-pulse', 'leaf', 'sprout', 'apple', 'carrot', 'salad', 'utensils', 'cooking-pot',
        'egg', 'fish', 'milk', 'droplet', 'glass-water', 'wheat', 'cherry', 'citrus', 'banana', 'brain', 'lightbulb',
        'chart-bar', 'chart-column', 'chart-line', 'chart-pie', 'chart-no-axes-combined', 'trending-up', 'trending-down',
        'activity', 'gauge', 'clipboard', 'clipboard-check', 'clipboard-list', 'file-text', 'file-spreadsheet', 'file-down',
        'file-up', 'download', 'upload', 'printer', 'settings', 'sliders-horizontal', 'shield', 'shield-check', 'lock',
        'unlock', 'key-round', 'mail', 'phone', 'bell', 'message-circle', 'message-square', 'messages-square', 'megaphone',
        'inbox', 'send', 'reply', 'pin', 'pin-off', 'eye', 'eye-off', 'pencil', 'square-pen', 'trash-2', 'plus', 'minus', 'x',
        'check', 'check-check', 'circle-check', 'circle-x', 'circle-alert', 'triangle-alert', 'info', 'circle-help', 'search',
        'filter', 'arrow-up-down', 'chevron-left', 'chevron-right', 'chevron-up', 'chevron-down', 'chevrons-left',
        'chevrons-right', 'arrow-left', 'arrow-right', 'arrow-up', 'arrow-down', 'arrow-up-right', 'external-link', 'link',
        'image', 'video', 'play', 'circle-play', 'log-out', 'log-in', 'menu', 'panel-left', 'sun', 'moon', 'monitor',
        'archive', 'archive-restore', 'refresh-cw', 'rotate-ccw', 'copy', 'ellipsis', 'ellipsis-vertical', 'grip-vertical',
        'shuffle', 'repeat', 'save', 'tag', 'hash', 'folder', 'folder-open', 'bookmark', 'gift', 'smile', 'party-popper',
        'wifi-off', 'cloud-upload', 'cloud-check', 'database', 'globe', 'palette', 'mouse-pointer-click', 'hand-heart',
        'badge-check', 'scroll-text', 'presentation', 'house-heart',
    ];

    $enumIcons = collect(glob(app_path('Enums/*.php')))
        ->map(fn (string $path) => 'App\\Enums\\'.pathinfo($path, PATHINFO_FILENAME))
        ->filter(fn (string $enum) => enum_exists($enum) && method_exists($enum, 'icon'))
        ->flatMap(fn (string $enum) => array_map(fn ($case) => $case->icon(), $enum::cases()));

    $missing = collect($required)->merge($enumIcons)->unique()->reject(fn (string $name) => Icon::has($name))->values()->all();

    expect($missing)->toBe([]);
});
