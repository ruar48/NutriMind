<?php

use Illuminate\Http\Request;

/**
 * Make the given URL the current request so query-aware components can read it.
 */
function useRequestForTableTest(string $url): void
{
    app()->instance('request', Request::create($url));
}

it('links an unsorted column to ascending order and resets the page', function () {
    useRequestForTableTest('http://localhost/admin/users?search=ana&page=3');

    $html = (string) $this->blade('<x-table.th sort="name">Name</x-table.th>');

    expect($html)->toContain('aria-sort="none"')
        ->toContain('href="http://localhost/admin/users?search=ana&amp;sort=name&amp;direction=asc"')
        ->toContain('data-icon="arrow-up-down"')
        ->not->toContain('page=3');
});

it('flips the direction of the sorted column', function () {
    useRequestForTableTest('http://localhost/admin/users?sort=name&direction=asc');

    $html = (string) $this->blade('<x-table.th sort="name">Name</x-table.th>');

    expect($html)->toContain('aria-sort="ascending"')
        ->toContain('direction=desc')
        ->toContain('data-icon="arrow-up"')
        ->toContain('(sort descending)');
});

it('shows a descending column as sorted down', function () {
    useRequestForTableTest('http://localhost/admin/users?sort=created_at&direction=desc');

    $this->blade('<x-table.th sort="created_at" default-direction="desc">Joined</x-table.th>')
        ->assertSee('aria-sort="descending"', false)
        ->assertSee('data-icon="arrow-down"', false);
});

it('starts a column in its default direction', function () {
    useRequestForTableTest('http://localhost/teacher/students');

    $this->blade('<x-table.th sort="average" default-direction="desc">Average</x-table.th>')
        ->assertSee('direction=desc', false);
});

it('renders a plain header without sorting', function () {
    $html = (string) $this->blade('<x-table.th align="right">Actions</x-table.th>');

    expect($html)->toContain('scope="col"')
        ->toContain('text-right')
        ->not->toContain('aria-sort')
        ->not->toContain('<a ');
});

it('wraps head, body and footer in a scrollable card', function () {
    $html = (string) $this->blade(<<<'BLADE'
        <x-table caption="Learners" sticky>
            <x-slot:head><x-table.th>Name</x-table.th></x-slot:head>
            <x-table.row highlight><x-table.td primary>Ana</x-table.td><x-table.td numeric align="right">92</x-table.td></x-table.row>
            <x-slot:footer>Pagination</x-slot:footer>
        </x-table>
        BLADE);

    expect($html)->toContain('<caption class="sr-only">Learners</caption>')
        ->toContain('overflow-x-auto')
        ->toContain('max-h-[70vh] overflow-y-auto')
        ->toContain('sticky top-0')
        ->toContain('bg-sun-50/60')
        ->toMatch('/<td class="[^"]*tabular-nums[^"]*">\s*92\s*<\/td>/')
        ->toContain('Pagination');
});
