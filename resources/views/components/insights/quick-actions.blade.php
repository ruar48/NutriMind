{{--
    A grid of shortcut tiles. Props: actions (list of {label, description, icon, color, url}).
--}}
@props([
    'actions' => [],
    'title' => 'Quick actions',
    'description' => null,
])

@php
    $tones = [
        'leaf' => 'bg-leaf-50 text-leaf-700 ring-leaf-600/15 dark:bg-leaf-400/10 dark:text-leaf-300 dark:ring-leaf-400/20',
        'carrot' => 'bg-carrot-50 text-carrot-700 ring-carrot-600/15 dark:bg-carrot-400/10 dark:text-carrot-300 dark:ring-carrot-400/20',
        'sun' => 'bg-sun-50 text-sun-800 ring-sun-600/20 dark:bg-sun-400/10 dark:text-sun-300 dark:ring-sun-400/20',
        'berry' => 'bg-berry-50 text-berry-700 ring-berry-600/15 dark:bg-berry-400/10 dark:text-berry-300 dark:ring-berry-400/20',
        'grape' => 'bg-grape-50 text-grape-700 ring-grape-600/15 dark:bg-grape-400/10 dark:text-grape-300 dark:ring-grape-400/20',
        'sky' => 'bg-sky-50 text-sky-700 ring-sky-600/15 dark:bg-sky-400/10 dark:text-sky-300 dark:ring-sky-400/20',
        'teal' => 'bg-teal-50 text-teal-700 ring-teal-600/15 dark:bg-teal-400/10 dark:text-teal-300 dark:ring-teal-400/20',
        'lime' => 'bg-lime-50 text-lime-800 ring-lime-600/20 dark:bg-lime-400/10 dark:text-lime-300 dark:ring-lime-400/20',
        'pink' => 'bg-pink-50 text-pink-700 ring-pink-600/15 dark:bg-pink-400/10 dark:text-pink-300 dark:ring-pink-400/20',
        'indigo' => 'bg-indigo-50 text-indigo-700 ring-indigo-600/15 dark:bg-indigo-400/10 dark:text-indigo-300 dark:ring-indigo-400/20',
        'slate' => 'bg-slate-100 text-slate-700 ring-slate-500/15 dark:bg-slate-400/10 dark:text-slate-300 dark:ring-slate-400/20',
    ];
@endphp

<x-section :title="$title" :description="$description" icon="zap" color="sun" {{ $attributes }}>
    <div class="@container">
        <ul class="grid grid-cols-1 gap-3 @md:grid-cols-2">
            @foreach ($actions as $action)
                <li class="min-w-0">
                    <x-card :href="$action['url']" padding="sm" class="group h-full">
                        <span class="flex items-center gap-3">
                            <span @class(['grid size-11 shrink-0 place-items-center rounded-xl ring-1 ring-inset transition group-hover:scale-105', $tones[$action['color'] ?? 'leaf'] ?? $tones['leaf']])>
                                <x-icon :name="$action['icon']" class="size-5" />
                            </span>
                            <span class="min-w-0 flex-1">
                                <span class="block truncate text-sm font-semibold text-ink-900 dark:text-ink-50">{{ $action['label'] }}</span>
                                @if (filled($action['description'] ?? null))
                                    <span class="block truncate text-xs text-ink-500 dark:text-ink-400">{{ $action['description'] }}</span>
                                @endif
                            </span>
                            <x-icon name="arrow-right" class="size-4 shrink-0 text-ink-400 transition group-hover:translate-x-0.5 dark:text-ink-500" />
                        </span>
                    </x-card>
                </li>
            @endforeach
        </ul>
    </div>
</x-section>
