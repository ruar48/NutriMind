@props([
    'title',
    'icon' => 'circle-dot',
    'color' => 'slate',
    'time' => null,
    'href' => null,
])

@php
    $tones = [
        'leaf' => 'bg-leaf-100 text-leaf-700 dark:bg-leaf-400/15 dark:text-leaf-300',
        'carrot' => 'bg-carrot-100 text-carrot-700 dark:bg-carrot-400/15 dark:text-carrot-300',
        'sun' => 'bg-sun-100 text-sun-800 dark:bg-sun-400/15 dark:text-sun-300',
        'berry' => 'bg-berry-100 text-berry-700 dark:bg-berry-400/15 dark:text-berry-300',
        'grape' => 'bg-grape-100 text-grape-700 dark:bg-grape-400/15 dark:text-grape-300',
        'sky' => 'bg-sky-100 text-sky-700 dark:bg-sky-400/15 dark:text-sky-300',
        'teal' => 'bg-teal-100 text-teal-700 dark:bg-teal-400/15 dark:text-teal-300',
        'lime' => 'bg-lime-100 text-lime-800 dark:bg-lime-400/15 dark:text-lime-300',
        'pink' => 'bg-pink-100 text-pink-700 dark:bg-pink-400/15 dark:text-pink-300',
        'indigo' => 'bg-indigo-100 text-indigo-700 dark:bg-indigo-400/15 dark:text-indigo-300',
        'slate' => 'bg-slate-100 text-slate-600 dark:bg-slate-400/15 dark:text-slate-300',
    ];

    $tone = $tones[$color] ?? $tones['slate'];
    $isDate = $time instanceof \DateTimeInterface;
    $moment = $isDate ? \Illuminate\Support\Carbon::instance($time) : null;
@endphp

<li {{ $attributes->class('group relative flex gap-3 pb-6 last:pb-0') }}>
    <span aria-hidden="true" class="absolute top-9 bottom-1 left-4 w-px bg-ink-900/10 group-last:hidden dark:bg-white/10"></span>
    <span @class(['relative grid size-8 shrink-0 place-items-center rounded-full ring-4 ring-white dark:ring-ink-900', $tone])>
        <x-icon :name="$icon" class="size-4" />
    </span>
    <div class="min-w-0 flex-1 pt-1">
        <div class="flex flex-wrap items-baseline justify-between gap-x-3 gap-y-0.5">
            <p class="min-w-0 text-sm font-medium text-ink-900 dark:text-ink-100">
                @if ($href)
                    <a href="{{ $href }}" class="rounded hover:text-leaf-700 hover:underline dark:hover:text-leaf-300">{{ $title }}</a>
                @else
                    {{ $title }}
                @endif
            </p>
            @if ($moment)
                <time datetime="{{ $moment->toIso8601String() }}" title="{{ $moment->format('M j, Y g:i A') }}" class="shrink-0 text-xs text-ink-500 dark:text-ink-400">{{ $moment->diffForHumans() }}</time>
            @elseif (filled($time))
                <span class="shrink-0 text-xs text-ink-500 dark:text-ink-400">{{ $time }}</span>
            @endif
        </div>
        @if ($slot->hasActualContent())
            <div class="mt-1 text-sm text-ink-600 dark:text-ink-300">{{ $slot }}</div>
        @endif
    </div>
</li>
