<?php

use Illuminate\Http\Request;

/**
 * Make the given URL the current request so query-aware components can read it.
 */
function useRequestForFilterBarTest(string $url): void
{
    app()->instance('request', Request::create($url));
}

it('renders an auto-submitting GET search form filled from the query', function () {
    useRequestForFilterBarTest('http://localhost/admin/users?search=reyes');

    $html = (string) $this->blade('<x-filter-bar action="/admin/users" search-placeholder="Search name or email" />');

    expect($html)->toContain('<form method="GET" action="/admin/users" role="search" data-auto-submit')
        ->toContain('name="search"')
        ->toContain('value="reyes"')
        ->toContain('placeholder="Search name or email"')
        ->toContain('<noscript>');
});

it('carries the sort over as hidden fields', function () {
    useRequestForFilterBarTest('http://localhost/admin/users?sort=name&direction=desc');

    $html = (string) $this->blade('<x-filter-bar />');

    expect($html)->toContain('<input type="hidden" name="sort" value="name">')
        ->toContain('<input type="hidden" name="direction" value="desc">');
});

it('offers a reset link only while a filter is active', function (string $url, bool $showsReset) {
    useRequestForFilterBarTest($url);

    $html = (string) $this->blade('<x-filter-bar reset-url="/admin/users" />');

    expect(str_contains($html, 'Reset'))->toBe($showsReset);
})->with([
    'no query' => ['http://localhost/admin/users', false],
    'only paging and sorting' => ['http://localhost/admin/users?page=2&sort=name', false],
    'an empty filter' => ['http://localhost/admin/users?status=', false],
    'a search term' => ['http://localhost/admin/users?search=ana', true],
    'a filter value' => ['http://localhost/admin/users?status=inactive', true],
]);

it('lets the caller decide whether filters are active', function () {
    useRequestForFilterBarTest('http://localhost/admin/users?tab=teachers');

    $this->blade('<x-filter-bar reset-url="/admin/users" :active="false" />')
        ->assertDontSeeText('Reset');
});

it('renders the filters passed in the slot and can drop the search field', function () {
    $html = (string) $this->blade('<x-filter-bar :search="false"><x-form.select name="status" :options="[\'active\' => \'Active\']" placeholder="All statuses" /></x-filter-bar>');

    expect($html)->toContain('name="status"')
        ->not->toContain('type="search"');
});
