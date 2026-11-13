@props([
    'id' => null,
])

<p @if ($id) id="{{ $id }}" @endif {{ $attributes->class('text-xs text-ink-500 dark:text-ink-400') }}>{{ $slot }}</p>
