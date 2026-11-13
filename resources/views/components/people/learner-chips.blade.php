@props([
    'learners',
    'max' => 3,
    'empty' => 'No linked learners',
])

@php
    $learners = collect($learners);
    $visible = $learners->take($max);
    $hiddenCount = $learners->count() - $visible->count();
@endphp

@if ($learners->isEmpty())
    <span {{ $attributes->class('text-ink-400 dark:text-ink-500') }}>{{ $empty }}</span>
@else
    <div {{ $attributes->class('flex max-w-sm flex-wrap items-center gap-1.5') }} data-learner-chips="{{ $learners->count() }}">
        @foreach ($visible as $learner)
            <a href="{{ route('admin.students.show', $learner) }}" class="inline-flex max-w-full rounded-full focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-leaf-500">
                <x-badge color="sky" icon="backpack" size="sm" class="max-w-full transition hover:brightness-95 dark:hover:brightness-125">
                    <span class="truncate">{{ $learner->name }}</span>
                    @if ($learner->pivot?->relationship)
                        <span class="font-normal text-sky-700/80 dark:text-sky-200/70">· {{ $learner->pivot->relationship->label() }}</span>
                    @endif
                </x-badge>
            </a>
        @endforeach
        @if ($hiddenCount > 0)
            <x-badge size="sm" :title="$learners->skip($max)->pluck('name')->join(', ')">
                +{{ $hiddenCount }}<span class="sr-only"> more: {{ $learners->skip($max)->pluck('name')->join(', ') }}</span>
            </x-badge>
        @endif
    </div>
@endif
