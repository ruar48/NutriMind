@props([
    'navigation' => [],
    'portal' => [],
    'schoolName' => null,
    'homeUrl' => null,
])

@php
    $portalTones = [
        'leaf' => ['chip' => 'bg-leaf-400/15 text-leaf-100 ring-leaf-300/25', 'dot' => 'bg-leaf-300'],
        'grape' => ['chip' => 'bg-grape-400/20 text-grape-100 ring-grape-300/30', 'dot' => 'bg-grape-300'],
        'carrot' => ['chip' => 'bg-carrot-400/20 text-carrot-100 ring-carrot-300/30', 'dot' => 'bg-carrot-300'],
        'sun' => ['chip' => 'bg-sun-400/20 text-sun-100 ring-sun-300/30', 'dot' => 'bg-sun-300'],
        'berry' => ['chip' => 'bg-berry-400/20 text-berry-100 ring-berry-300/30', 'dot' => 'bg-berry-300'],
        'sky' => ['chip' => 'bg-sky-400/20 text-sky-100 ring-sky-300/30', 'dot' => 'bg-sky-300'],
        'teal' => ['chip' => 'bg-teal-400/20 text-teal-100 ring-teal-300/30', 'dot' => 'bg-teal-300'],
        'lime' => ['chip' => 'bg-lime-400/20 text-lime-100 ring-lime-300/30', 'dot' => 'bg-lime-300'],
        'pink' => ['chip' => 'bg-pink-400/20 text-pink-100 ring-pink-300/30', 'dot' => 'bg-pink-300'],
        'indigo' => ['chip' => 'bg-indigo-400/20 text-indigo-100 ring-indigo-300/30', 'dot' => 'bg-indigo-300'],
        'slate' => ['chip' => 'bg-slate-400/20 text-slate-100 ring-slate-300/30', 'dot' => 'bg-slate-300'],
    ];

    $tone = $portalTones[$portal['color'] ?? 'leaf'] ?? $portalTones['leaf'];
    $homeUrl ??= url('/');
@endphp

<div data-sidebar-backdrop class="pointer-events-none fixed inset-0 z-40 bg-ink-950/60 opacity-0 backdrop-blur-sm transition-opacity duration-300 data-open:pointer-events-auto data-open:opacity-100 lg:hidden" aria-hidden="true"></div>

<aside
    id="app-sidebar"
    data-sidebar
    aria-label="Main navigation"
    class="fixed inset-y-0 left-0 z-50 flex w-68 flex-col bg-forest text-white/70 shadow-2xl lg:shadow-none dark:bg-forest-deep dark:ring-1 dark:ring-white/5"
    data-print-hidden
>
    <div aria-hidden="true" class="pointer-events-none absolute inset-x-0 top-0 h-48 bg-[radial-gradient(120%_80%_at_0%_0%,rgb(75_185_127/0.18),transparent_70%)]"></div>

    <div class="relative flex h-16 shrink-0 items-center justify-between gap-3 px-5">
        <a href="{{ $homeUrl }}" class="rounded-lg focus-visible:outline-leaf-300">
            <x-logo inverted size="sm" />
        </a>
        <a href="#" data-sidebar-close class="grid size-10 place-items-center rounded-xl text-white/60 transition hover:bg-white/10 hover:text-white lg:hidden pointer-coarse:size-11">
            <x-icon name="x" class="size-5" />
            <span class="sr-only">Close menu</span>
        </a>
    </div>

    @if (filled($portal['label'] ?? null))
        <div class="relative px-5 pb-2">
            <span @class(['inline-flex items-center gap-2 rounded-full px-2.5 py-1 text-[11px] font-semibold tracking-wide ring-1 ring-inset', $tone['chip']])>
                <span aria-hidden="true" @class(['size-1.5 rounded-full', $tone['dot']])></span>
                {{ $portal['label'] }}
            </span>
        </div>
    @endif

    <nav class="scrollbar-thin relative flex-1 overflow-y-auto overscroll-contain px-3 pb-6" aria-label="Portal">
        @foreach ($navigation as $section)
            @php $items = $section['items'] ?? []; @endphp
            @if (count($items) > 0)
                <div class="mt-5 first:mt-3">
                    @if (filled($section['label'] ?? null))
                        <p class="px-3 pb-1.5 text-[11px] font-semibold tracking-[0.14em] text-white/55 uppercase">{{ $section['label'] }}</p>
                    @endif
                    <ul class="space-y-0.5">
                        @foreach ($items as $item)
                            @php $isActive = (bool) ($item['active'] ?? false); @endphp
                            <li>
                                <a
                                    href="{{ $item['url'] }}"
                                    @if ($isActive) aria-current="page" @endif
                                    @class([
                                        'group relative flex h-10 items-center gap-3 rounded-xl px-3 text-sm font-medium transition pointer-coarse:h-11 focus-visible:outline-leaf-300',
                                        'bg-leaf-400/12 text-white shadow-[0_0_26px_-8px_rgb(75_185_127/0.7),inset_0_0_0_1px_rgb(128_211_166/0.16)]' => $isActive,
                                        'hover:bg-white/6 hover:text-white' => ! $isActive,
                                    ])
                                >
                                    @if ($isActive)
                                        <span aria-hidden="true" class="absolute top-1/2 -left-3 h-6 w-[3px] -translate-y-1/2 rounded-r-full bg-leaf-400 shadow-[0_0_12px_rgb(75_185_127/0.9)]"></span>
                                    @endif
                                    <x-icon :name="$item['icon'] ?? 'circle-dot'" @class(['size-[1.15rem]', 'text-leaf-300' => $isActive, 'text-white/45 transition group-hover:text-white/85' => ! $isActive]) />
                                    <span class="min-w-0 flex-1 truncate">{{ $item['label'] }}</span>
                                    @if (filled($item['badge'] ?? null))
                                        <span class="min-w-5 rounded-full bg-sun-400 px-1.5 text-center text-[11px] leading-5 font-bold text-ink-950 tabular-nums">{{ $item['badge'] }}</span>
                                    @endif
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif
        @endforeach
    </nav>

    <div class="relative shrink-0 border-t border-white/8 px-5 py-4">
        <p class="truncate text-xs font-semibold text-white/80" title="{{ $schoolName }}">{{ $schoolName }}</p>
        <p class="mt-0.5 text-[11px] text-white/55">NutriMind Web · v{{ config('nutrimind.version', '1.0') }}</p>
    </div>
</aside>
