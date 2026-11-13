@props([
    'label' => null,
])

@if ($label)
    <div {{ $attributes->class('flex items-center gap-3') }} role="separator" aria-label="{{ $label }}">
        <span class="h-px flex-1 bg-ink-900/8 dark:bg-white/10"></span>
        <span class="text-xs font-semibold tracking-wide text-ink-500 uppercase dark:text-ink-400">{{ $label }}</span>
        <span class="h-px flex-1 bg-ink-900/8 dark:bg-white/10"></span>
    </div>
@else
    <hr {{ $attributes->class('border-0 border-t border-ink-900/8 dark:border-white/10') }}>
@endif
