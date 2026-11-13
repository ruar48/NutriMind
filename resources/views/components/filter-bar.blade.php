@props([
    'action' => null,
    'search' => true,
    'searchName' => 'search',
    'searchLabel' => 'Search',
    'searchPlaceholder' => 'Search…',
    'resetUrl' => null,
    'active' => null,
    'preserve' => ['sort', 'direction'],
])

@php
    $action ??= url()->current();
    $searchValue = request()->query($searchName);
    $searchValue = is_string($searchValue) ? $searchValue : '';
    $preserved = collect($preserve)
        ->mapWithKeys(fn ($key) => [$key => request()->query($key)])
        ->filter(fn ($value) => is_string($value) && $value !== '');
    $isActive = $active ?? collect(request()->query())
        ->except(array_merge(['page'], (array) $preserve))
        ->filter(fn ($value) => filled($value))
        ->isNotEmpty();
    $searchId = 'filter-'.\Illuminate\Support\Str::slug($searchName);
@endphp

<form method="GET" action="{{ $action }}" role="search" data-auto-submit {{ $attributes->class('flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-end') }} data-print-hidden>
    @foreach ($preserved as $key => $preservedValue)
        <input type="hidden" name="{{ $key }}" value="{{ $preservedValue }}">
    @endforeach

    @if ($search)
        <div class="relative min-w-0 sm:min-w-64 sm:flex-1">
            <label for="{{ $searchId }}" class="sr-only">{{ $searchLabel }}</label>
            <x-icon name="search" class="pointer-events-none absolute top-1/2 left-3.5 size-4 -translate-y-1/2 text-ink-400 dark:text-ink-500" />
            <input
                id="{{ $searchId }}"
                type="search"
                name="{{ $searchName }}"
                value="{{ $searchValue }}"
                placeholder="{{ $searchPlaceholder }}"
                autocomplete="off"
                enterkeyhint="search"
                class="block h-11 w-full rounded-xl border-0 bg-white pr-3.5 pl-10 text-sm text-ink-900 shadow-xs ring-1 ring-ink-900/12 transition ring-inset placeholder:text-ink-400 focus:ring-2 focus:ring-leaf-500 focus:ring-inset focus:outline-hidden dark:bg-white/5 dark:text-ink-50 dark:ring-white/12 dark:placeholder:text-ink-500 dark:focus:ring-leaf-400"
            >
        </div>
    @endif

    {{ $slot }}

    <noscript>
        <x-button type="submit" variant="secondary" icon="filter">Apply filters</x-button>
    </noscript>

    @if ($resetUrl && $isActive)
        <x-button :href="$resetUrl" variant="ghost" icon="rotate-ccw">Reset</x-button>
    @endif
</form>
