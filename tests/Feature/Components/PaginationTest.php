<?php

use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;

/**
 * Build a length-aware paginator for a list of numbered items.
 */
function paginatorForPaginationTest(int $total, int $perPage, int $page): LengthAwarePaginator
{
    $items = collect(range(1, max(1, $total)))->slice(($page - 1) * $perPage, $perPage)->values();

    return new LengthAwarePaginator($total === 0 ? [] : $items, $total, $perPage, $page, ['path' => 'http://localhost/teacher/students']);
}

it('summarizes the visible range and total', function () {
    $html = paginatorForPaginationTest(47, 15, 2)->links('pagination.default')->toHtml();

    expect($html)->toContain('aria-label="Pagination"')
        ->toMatch('/Showing\s+<span[^>]*>16<\/span>&ndash;<span[^>]*>30<\/span>\s+of\s+<span[^>]*>47<\/span>\s+results/');
});

it('marks the current page and links its neighbours', function () {
    $html = paginatorForPaginationTest(47, 15, 2)->links('pagination.default')->toHtml();

    expect($html)->toMatch('/<span aria-current="page"[^>]*>2<\/span>/')
        ->toContain('href="http://localhost/teacher/students?page=1" rel="prev"')
        ->toContain('href="http://localhost/teacher/students?page=3" rel="next"')
        ->toContain('aria-label="Go to page 4"')
        ->toContain('Page 2 of 4');
});

it('disables previous on the first page and next on the last page', function () {
    $first = paginatorForPaginationTest(47, 15, 1)->links('pagination.default')->toHtml();
    $last = paginatorForPaginationTest(47, 15, 4)->links('pagination.default')->toHtml();

    expect($first)->toContain('<span class="sr-only">Previous page</span>')
        ->not->toContain('rel="prev"')
        ->and($last)->toContain('<span class="sr-only">Next page</span>')
        ->not->toContain('rel="next"');
});

it('collapses distant pages into an ellipsis', function () {
    $html = paginatorForPaginationTest(300, 10, 15)->links('pagination.default')->toHtml();

    expect($html)->toContain('&hellip;')
        ->toContain('aria-label="Go to page 30"')
        ->not->toContain('aria-label="Go to page 25"');
});

it('shows only the summary when everything fits on one page', function () {
    $html = paginatorForPaginationTest(5, 15, 1)->links('pagination.default')->toHtml();

    expect($html)->toMatch('/Showing\s+<span[^>]*>1<\/span>&ndash;<span[^>]*>5<\/span>\s+of\s+<span[^>]*>5<\/span>\s+results/')
        ->not->toContain('rel="next"')
        ->not->toContain('Page 1 of 1');
});

it('renders nothing for an empty result set', function () {
    $html = paginatorForPaginationTest(0, 15, 1)->links('pagination.default')->toHtml();

    expect(trim($html))->toBe('');
});

it('renders previous and next links in the simple view', function () {
    $paginator = new Paginator(range(16, 31), 15, 2, ['path' => 'http://localhost/forum']);

    $html = $paginator->links('pagination.simple')->toHtml();

    expect($html)->toContain('href="http://localhost/forum?page=1" rel="prev"')
        ->toContain('href="http://localhost/forum?page=3" rel="next"')
        ->toMatch('/Showing\s+<span[^>]*>16<\/span>&ndash;<span[^>]*>30<\/span>/');
});

it('renders nothing in the simple view when there is a single page', function () {
    $paginator = new Paginator(range(1, 3), 15, 1, ['path' => 'http://localhost/forum']);

    expect(trim($paginator->links('pagination.simple')->toHtml()))->toBe('');
});
