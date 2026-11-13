@props([
    'classrooms',
    'max' => 2,
    'empty' => 'No classroom',
])

@php
    $classrooms = collect($classrooms);
    $visible = $classrooms->take($max);
    $hiddenCount = $classrooms->count() - $visible->count();
@endphp

@if ($classrooms->isEmpty())
    <span {{ $attributes->class('text-ink-400 dark:text-ink-500') }}>{{ $empty }}</span>
@else
    <div {{ $attributes->class('flex max-w-xs flex-wrap items-center gap-1.5') }} data-classroom-chips="{{ $classrooms->count() }}">
        @foreach ($visible as $classroom)
            <x-subject-chip
                :name="$classroom->name"
                :color="$classroom->subject->color"
                :icon="$classroom->subject->icon"
                size="sm"
                :title="$classroom->subject->name.' · '.$classroom->name"
            />
        @endforeach
        @if ($hiddenCount > 0)
            <x-badge size="sm" :title="$classrooms->skip($max)->pluck('name')->join(', ')" data-classroom-chips-more>
                +{{ $hiddenCount }}<span class="sr-only"> more: {{ $classrooms->skip($max)->pluck('name')->join(', ') }}</span>
            </x-badge>
        @endif
    </div>
@endif
