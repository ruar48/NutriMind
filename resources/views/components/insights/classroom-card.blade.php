{{--
    A teacher-dashboard classroom card with its quiz completion ring.
    Props: classroom (with subject and students_count), completion (?{assigned, submitted, rate}), averageScore (?float).
--}}
@props([
    'classroom',
    'completion' => null,
    'averageScore' => null,
])

@php
    $rate = $completion['rate'] ?? null;
@endphp

<x-card :href="route('teacher.classrooms.show', $classroom)" padding="none" {{ $attributes->merge(['data-insights-classroom' => $classroom->id]) }}>
    <div class="flex items-start gap-4 p-5">
        <x-progress-ring :value="$rate ?? 0" size="lg" color="leaf" :label="'Quiz completion in '.$classroom->name">
            <span class="flex flex-col items-center leading-none">
                <span class="font-display text-lg font-bold text-ink-950 dark:text-white">{{ $rate === null ? '—' : number_format($rate, 0).'%' }}</span>
                <span class="mt-1 text-[10px] font-medium tracking-wide text-ink-500 uppercase dark:text-ink-400">done</span>
            </span>
        </x-progress-ring>
        <div class="min-w-0 flex-1">
            <x-subject-chip :subject="$classroom->subject" size="sm" />
            <h3 class="mt-2 truncate font-display text-lg font-semibold tracking-tight text-ink-950 dark:text-white">{{ $classroom->name }}</h3>
            <p class="text-sm text-ink-500 dark:text-ink-400">{{ $classroom->grade_level->label() }} · Section {{ $classroom->section }}</p>
        </div>
    </div>
    <dl class="grid grid-cols-3 gap-2 border-t border-ink-900/5 px-5 py-3 text-center dark:border-white/8">
        <div class="min-w-0">
            <dt class="text-[11px] font-medium tracking-wide text-ink-500 uppercase dark:text-ink-400">Learners</dt>
            <dd class="mt-0.5 font-display text-base font-semibold text-ink-950 dark:text-white">{{ number_format($classroom->students_count) }}</dd>
        </div>
        <div class="min-w-0">
            <dt class="text-[11px] font-medium tracking-wide text-ink-500 uppercase dark:text-ink-400">Avg (30d)</dt>
            <dd class="mt-0.5"><x-score-pill :value="$averageScore" size="sm" /></dd>
        </div>
        <div class="min-w-0">
            <dt class="text-[11px] font-medium tracking-wide text-ink-500 uppercase dark:text-ink-400">Submitted</dt>
            <dd class="mt-0.5 text-sm font-semibold text-ink-900 tabular-nums dark:text-ink-50">
                {{ $completion === null ? '—' : number_format($completion['submitted']).' / '.number_format($completion['assigned']) }}
            </dd>
        </div>
    </dl>
</x-card>
