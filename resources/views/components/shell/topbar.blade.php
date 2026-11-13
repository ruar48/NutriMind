@props([
    'user' => null,
    'searchUrl' => null,
    'searchPlaceholder' => 'Search…',
    'activeSchoolYear' => null,
    'activeTerm' => null,
    'homeUrl' => null,
])

@php
    $homeUrl ??= url('/');
    $currentSearch = request()->query('search');
    $currentSearch = is_string($currentSearch) ? $currentSearch : '';
@endphp

<header class="sticky top-0 z-30 border-b border-ink-900/6 bg-paper/80 backdrop-blur-xl dark:border-white/8 dark:bg-ink-950/75" data-print-hidden>
    <div class="mx-auto flex h-16 max-w-[1440px] items-center gap-2 px-4 sm:gap-3 sm:px-6 lg:px-8">
        <a
            href="#app-sidebar"
            role="button"
            data-sidebar-toggle
            aria-controls="app-sidebar"
            aria-expanded="false"
            class="-ml-1 grid size-10 place-items-center rounded-xl text-ink-600 transition hover:bg-ink-900/5 hover:text-ink-950 lg:hidden pointer-coarse:size-11 dark:text-ink-300 dark:hover:bg-white/8 dark:hover:text-white"
        >
            <x-icon name="menu" class="size-5" />
            <span class="sr-only">Open menu</span>
        </a>

        <a href="{{ $homeUrl }}" class="rounded-lg lg:hidden">
            <x-logo mark-only size="sm" />
        </a>

        @if ($searchUrl)
            <form method="GET" action="{{ $searchUrl }}" role="search" class="hidden min-w-0 flex-1 md:block md:max-w-md">
                <label for="global-search" class="sr-only">Search</label>
                <div class="relative">
                    <x-icon name="search" class="pointer-events-none absolute top-1/2 left-3.5 size-4 -translate-y-1/2 text-ink-400 dark:text-ink-500" />
                    <input
                        id="global-search"
                        type="search"
                        name="search"
                        value="{{ $currentSearch }}"
                        placeholder="{{ $searchPlaceholder }}"
                        autocomplete="off"
                        enterkeyhint="search"
                        data-search-shortcut
                        class="block h-10 w-full rounded-xl border-0 bg-white/80 pr-10 pl-10 text-sm text-ink-900 shadow-xs ring-1 ring-ink-900/10 transition ring-inset placeholder:text-ink-400 focus:bg-white focus:ring-2 focus:ring-leaf-500 focus:outline-hidden dark:bg-white/5 dark:text-ink-50 dark:ring-white/10 dark:placeholder:text-ink-500 dark:focus:bg-white/8 dark:focus:ring-leaf-400"
                    >
                    <kbd class="pointer-events-none absolute top-1/2 right-3 hidden -translate-y-1/2 rounded-md bg-ink-900/5 px-1.5 font-sans text-[11px] font-semibold text-ink-500 ring-1 ring-ink-900/8 lg:block dark:bg-white/8 dark:text-ink-400 dark:ring-white/10" aria-hidden="true">/</kbd>
                </div>
            </form>

            <x-dropdown variant="icon" icon="search" label="Search" align="left" width="lg" class="md:hidden">
                <form method="GET" action="{{ $searchUrl }}" role="search" class="p-1.5">
                    <label for="global-search-mobile" class="sr-only">Search</label>
                    <input
                        id="global-search-mobile"
                        type="search"
                        name="search"
                        value="{{ $currentSearch }}"
                        placeholder="{{ $searchPlaceholder }}"
                        autocomplete="off"
                        enterkeyhint="search"
                        class="block h-11 w-full rounded-xl border-0 bg-white px-3.5 text-sm text-ink-900 ring-1 ring-ink-900/12 ring-inset focus:ring-2 focus:ring-leaf-500 focus:outline-hidden dark:bg-white/5 dark:text-ink-50 dark:ring-white/12"
                    >
                </form>
            </x-dropdown>
        @endif

        <div class="ml-auto flex items-center gap-1 sm:gap-2">
            @if ($activeSchoolYear)
                <span class="hidden h-8 items-center gap-1.5 rounded-full bg-white px-3 text-xs font-semibold whitespace-nowrap text-ink-700 ring-1 ring-ink-900/8 xl:inline-flex dark:bg-white/5 dark:text-ink-200 dark:ring-white/10">
                    <x-icon name="calendar-days" class="size-3.5 text-leaf-600 dark:text-leaf-400" />
                    SY {{ $activeSchoolYear->name }}
                    @if ($activeTerm)
                        <span class="text-ink-300 dark:text-ink-600" aria-hidden="true">·</span>
                        {{ $activeTerm->name }}
                    @endif
                </span>
            @endif

            <x-theme-switch />

            @if ($user)
                <x-shell.user-menu :user="$user" />
            @endif
        </div>
    </div>
</header>
