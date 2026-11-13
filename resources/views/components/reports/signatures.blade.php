@props([
    'preparedBy',
    'preparedRole',
    'schoolHead' => null,
])

<div {{ $attributes->class('mt-14 grid grid-cols-2 gap-12 text-sm text-ink-950') }} data-print-avoid-break data-report-signatures>
    <div>
        <p class="text-ink-600">Prepared by:</p>
        <div class="mt-12 border-b border-ink-900" aria-hidden="true"></div>
        <p class="mt-1.5 font-semibold tracking-wide uppercase">{{ $preparedBy }}</p>
        <p class="text-xs text-ink-600">{{ $preparedRole }}</p>
    </div>
    <div>
        <p class="text-ink-600">Noted by:</p>
        <div class="mt-12 border-b border-ink-900" aria-hidden="true"></div>
        <p class="mt-1.5 min-h-5 font-semibold tracking-wide uppercase">{{ filled($schoolHead) ? $schoolHead : '' }}</p>
        <p class="text-xs text-ink-600">School Head</p>
    </div>
</div>
