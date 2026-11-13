@props([
    'for' => null,
    'value' => null,
    'required' => false,
    'optional' => false,
])

<label @if ($for) for="{{ $for }}" @endif {{ $attributes->class('flex items-baseline gap-1 text-sm font-medium text-ink-800 dark:text-ink-100') }}>
    <span>{{ $value ?? $slot }}</span>
    @if ($required)
        <span class="text-berry-600 dark:text-berry-400" aria-hidden="true">*</span>
    @elseif ($optional)
        <span class="text-xs font-normal text-ink-500 dark:text-ink-400">(optional)</span>
    @endif
</label>
