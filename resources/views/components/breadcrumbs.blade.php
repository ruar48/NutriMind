@props([
    'items' => [],
])

@php
    $items = array_values(array_filter($items, fn ($item) => is_array($item) && filled($item['label'] ?? null)));
    $parentIndex = count($items) - 2;
@endphp

@if (count($items) > 0)
    <nav aria-label="Breadcrumb" {{ $attributes }}>
        <ol class="flex flex-wrap items-center gap-x-1 gap-y-1 text-sm">
            @foreach ($items as $index => $item)
                @php
                    $isCurrent = $loop->last;
                    $url = $item['url'] ?? null;
                @endphp
                <li @class([
                    'flex min-w-0 items-center gap-1',
                    'max-sm:hidden' => $index !== $parentIndex,
                ])>
                    @if (! $loop->first)
                        <x-icon name="chevron-right" @class(['size-3.5 text-ink-400 dark:text-ink-500', 'max-sm:hidden' => $index === $parentIndex]) />
                    @endif
                    @if ($index === $parentIndex)
                        <x-icon name="chevron-left" class="size-3.5 text-ink-400 sm:hidden dark:text-ink-500" />
                    @endif
                    @if ($url && ! $isCurrent)
                        <a href="{{ $url }}" class="truncate rounded-md px-1 py-0.5 font-medium text-ink-500 transition hover:bg-ink-900/5 hover:text-ink-900 pointer-coarse:py-2.5 dark:text-ink-400 dark:hover:bg-white/5 dark:hover:text-white">{{ $item['label'] }}</a>
                    @else
                        <span @if ($isCurrent) aria-current="page" @endif class="truncate px-1 font-medium text-ink-800 dark:text-ink-100">{{ $item['label'] }}</span>
                    @endif
                </li>
            @endforeach
        </ol>
    </nav>
@endif
