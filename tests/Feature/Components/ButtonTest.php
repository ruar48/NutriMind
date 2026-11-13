<?php

it('renders a non-submitting button by default', function () {
    $view = $this->blade('<x-button>Save</x-button>');

    $view->assertSee('<button', false)
        ->assertSee('type="button"', false)
        ->assertSeeText('Save')
        ->assertDontSee('<a ', false);
});

it('renders the requested button type', function () {
    $this->blade('<x-button type="submit">Save</x-button>')
        ->assertSee('type="submit"', false);
});

it('renders an anchor without a type when an href is given', function () {
    $view = $this->blade('<x-button href="/classrooms">Classrooms</x-button>');

    $view->assertSee('<a href="/classrooms"', false)
        ->assertDontSee('<button', false)
        ->assertDontSee('type="button"', false);
});

it('applies the classes of each variant', function (string $variant, string $expectedClass) {
    $this->blade('<x-button :variant="$variant">Go</x-button>', ['variant' => $variant])
        ->assertSee($expectedClass, false);
})->with([
    'primary' => ['primary', 'bg-leaf-600'],
    'secondary' => ['secondary', 'ring-ink-900/12'],
    'ghost' => ['ghost', 'hover:bg-ink-900/5'],
    'soft' => ['soft', 'bg-leaf-50'],
    'danger' => ['danger', 'bg-berry-600'],
    'link' => ['link', 'underline-offset-4'],
]);

it('falls back to the primary variant for an unknown variant', function () {
    $this->blade('<x-button variant="neon">Go</x-button>')
        ->assertSee('bg-leaf-600', false);
});

it('gives link buttons no fixed height or padding', function () {
    $this->blade('<x-button variant="link">Details</x-button>')
        ->assertDontSee('h-10', false)
        ->assertDontSee('px-4', false);
});

it('disables a button', function () {
    $this->blade('<x-button disabled>Save</x-button>')
        ->assertSee('disabled', false);
});

it('renders a disabled link as an aria-disabled anchor without an href', function () {
    $view = $this->blade('<x-button href="/quizzes" disabled>Quizzes</x-button>');

    $view->assertSee('aria-disabled="true"', false)
        ->assertDontSee('href="/quizzes"', false);
});

it('marks a loading button busy and disabled', function () {
    $view = $this->blade('<x-button type="submit" loading>Saving</x-button>');

    $view->assertSee('data-loading', false)
        ->assertSee('aria-busy="true"', false)
        ->assertSee('disabled', false);
});

it('renders leading and trailing icons', function () {
    $view = $this->blade('<x-button icon="plus" icon-trailing="arrow-right">New quiz</x-button>');

    $view->assertSee('data-icon="plus"', false)
        ->assertSee('data-icon="arrow-right"', false);
});

it('merges caller classes and attributes', function () {
    $this->blade('<x-button class="w-full" data-testid="save">Save</x-button>')
        ->assertSee('w-full', false)
        ->assertSee('data-testid="save"', false);
});

it('labels an icon button for assistive technology and pointer users', function () {
    $view = $this->blade('<x-icon-button icon="pencil" label="Edit classroom" />');

    $view->assertSee('title="Edit classroom"', false)
        ->assertSee('<span class="sr-only">Edit classroom</span>', false)
        ->assertSee('data-icon="pencil"', false);
});
