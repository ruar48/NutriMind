@props([
    'sort' => null,
    'align' => 'left',
    'defaultDirection' => 'asc',
])

@php
    $alignments = [
        'left' => ['cell' => 'text-left', 'link' => 'justify-start'],
        'center' => ['cell' => 'text-center', 'link' => 'justify-center'],
        'right' => ['cell' => 'text-right', 'link' => 'justify-end'],
    ];

    $alignment = $alignments[$align] ?? $alignments['left'];
    $isSortable = filled($sort);
    $currentSort = request()->query('sort');
    $currentDirection = request()->query('direction') === 'desc' ? 'desc' : 'asc';
    $isSorted = $isSortable && $currentSort === $sort;
    $nextDirection = $isSorted
        ? ($currentDirection === 'asc' ? 'desc' : 'asc')
        : ($defaultDirection === 'desc' ? 'desc' : 'asc');
    $url = $isSortable ? request()->fullUrlWithQuery(['sort' => $sort, 'direction' => $nextDirection, 'page' => null]) : null;
    $ariaSort = $isSorted ? ($currentDirection === 'asc' ? 'ascending' : 'descending') : 'none';
@endphp

<th
    scope="col"
    @if ($isSortable) aria-sort="{{ $ariaSort }}" @endif
    {{ $attributes->class(['px-4 py-3 font-semibold whitespace-nowrap first:pl-5 last:pr-5', $alignment['cell']]) }}
>
    @if ($isSortable)
        <a href="{{ $url }}" @class([
            'group -mx-1 inline-flex items-center gap-1 rounded-md px-1 py-0.5 transition hover:text-ink-900 dark:hover:text-white',
            $alignment['link'],
            'text-ink-900 dark:text-white' => $isSorted,
        ])>
            {{ $slot }}
            <x-icon
                :name="$isSorted ? ($currentDirection === 'asc' ? 'arrow-up' : 'arrow-down') : 'arrow-up-down'"
                @class(['size-3.5', 'text-leaf-600 dark:text-leaf-400' => $isSorted, 'text-ink-300 group-hover:text-ink-500 dark:text-ink-600 dark:group-hover:text-ink-400' => ! $isSorted])
                stroke-width="2.25"
            />
            <span class="sr-only">(sort {{ $nextDirection === 'asc' ? 'ascending' : 'descending' }})</span>
        </a>
    @else
        {{ $slot }}
    @endif
</th>
