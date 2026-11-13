{{--
    The school setup steps (SchoolOverview::setupChecklist()). Render it only while a step is missing.
--}}
@props([
    'steps' => [],
    'schoolName' => null,
])

@php
    $done = collect($steps)->where('done', true)->count();
    $total = count($steps);
@endphp

<x-section
    :title="'Finish setting up '.($schoolName ?: 'your school')"
    description="A few things are missing before teachers and learners can use NutriMind fully."
    icon="list-checks"
    color="sun"
    {{ $attributes->merge(['data-insights-setup' => $total - $done]) }}
>
    <x-progress :value="$done" :max="max(1, $total)" color="sun" label="Setup progress" show-value :value-text="$done.' of '.$total.' done'" />

    <ol class="mt-5 grid grid-cols-1 gap-3 md:grid-cols-2 2xl:grid-cols-3">
        @foreach ($steps as $step)
            <li @class([
                'flex min-w-0 items-start gap-3 rounded-2xl p-4 ring-1 ring-inset',
                'bg-leaf-50/60 ring-leaf-600/15 dark:bg-leaf-400/5 dark:ring-leaf-400/15' => $step['done'],
                'bg-white ring-ink-900/10 dark:bg-white/3 dark:ring-white/10' => ! $step['done'],
            ]) data-setup-step="{{ $step['key'] }}" data-done="{{ $step['done'] ? 'true' : 'false' }}">
                <x-icon
                    :name="$step['done'] ? 'circle-check' : 'circle-dashed'"
                    @class(['mt-0.5 size-5 shrink-0', 'text-leaf-600 dark:text-leaf-400' => $step['done'], 'text-sun-700 dark:text-sun-300' => ! $step['done']])
                />
                <div class="min-w-0 flex-1">
                    <p class="text-sm font-semibold text-ink-900 dark:text-ink-50">
                        {{ $step['title'] }}
                        <span class="sr-only">{{ $step['done'] ? '(done)' : '(to do)' }}</span>
                    </p>
                    <p class="mt-1 text-sm text-ink-600 dark:text-ink-300">{{ $step['description'] }}</p>
                    @unless ($step['done'])
                        <x-button variant="soft" size="sm" icon-trailing="arrow-right" :href="$step['url']" class="mt-3">{{ $step['action'] }}</x-button>
                    @endunless
                </div>
            </li>
        @endforeach
    </ol>
</x-section>
