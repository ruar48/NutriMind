@props([
    'column',
    'row',
    'primary' => false,
])

@php
    $value = $row->value($column->key);
    $text = $column->display($row);
@endphp

@if ($primary)
    <div class="flex min-w-44 flex-col gap-1">
        @if ($row->url)
            <a href="{{ $row->url }}" class="rounded-sm hover:text-leaf-700 hover:underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-leaf-600 dark:hover:text-leaf-300">{{ $text }}</a>
        @else
            <span>{{ $text }}</span>
        @endif
        @if ($row->tags !== [])
            <span class="flex flex-wrap gap-1">
                @foreach ($row->tags as $tag)
                    <x-badge :color="$tag['color']" :icon="$tag['icon']" size="sm">{{ $tag['label'] }}</x-badge>
                @endforeach
            </span>
        @endif
    </div>
@else
    @switch ($column->type->value)
        @case('score')
            <x-score-pill :value="$value" size="sm" />
            @break
        @case('band')
        @case('badge')
            @if ($value instanceof \BackedEnum)
                <x-status-badge :status="$value" size="sm" />
            @else
                <span class="text-ink-400 dark:text-ink-500">{{ $text }}</span>
            @endif
            @break
        @case('subject')
            @if ($value instanceof \App\Models\Subject)
                <x-subject-chip :subject="$value" size="sm" />
            @else
                <span class="text-ink-400 dark:text-ink-500">{{ $text }}</span>
            @endif
            @break
        @case('level')
            <x-level-badge :level="(int) $value" size="sm" />
            @break
        @case('status')
            <x-badge :color="$value ? 'leaf' : 'slate'" dot size="sm">{{ $text }}</x-badge>
            @break
        @case('list')
            <span class="block max-w-72 min-w-40 text-xs leading-relaxed">{{ $text }}</span>
            @break
        @default
            <span @class(['text-ink-400 dark:text-ink-500' => $value === null || $value === []])>{{ $text }}</span>
    @endswitch
@endif
