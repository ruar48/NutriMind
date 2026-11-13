@props([
    'text',
    'label' => 'Copy',
    'copiedLabel' => 'Copied!',
    'variant' => 'secondary',
    'size' => 'sm',
    'icon' => 'copy',
])

<x-button
    :variant="$variant"
    :size="$size"
    :icon="$icon"
    data-copy="{{ $text }}"
    data-copy-feedback="{{ $copiedLabel }}"
    data-js-only
    {{ $attributes }}
>
    <span data-copy-label>{{ $label }}</span>
    <span data-copy-status class="sr-only" aria-live="polite"></span>
</x-button>
