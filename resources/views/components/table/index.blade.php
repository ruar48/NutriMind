@props([
    'caption' => null,
    'sticky' => false,
    'flush' => false,
])

<div {{ $attributes->class([
    'relative overflow-hidden',
    'rounded-2xl bg-white shadow-card ring-1 ring-ink-900/5 dark:bg-ink-900 dark:shadow-none dark:ring-white/8' => ! $flush,
]) }} data-print-avoid-break>
    <div @class(['overflow-x-auto overscroll-x-contain', 'max-h-[70vh] overflow-y-auto' => $sticky])>
        <table class="min-w-full text-left text-sm">
            @if ($caption)
                <caption class="sr-only">{{ $caption }}</caption>
            @endif
            @isset($head)
                <thead @class([
                    'border-b border-ink-900/5 bg-ink-50/80 text-xs text-ink-500 dark:border-white/8 dark:bg-white/3 dark:text-ink-400',
                    'sticky top-0 z-10 backdrop-blur-sm' => $sticky,
                ])>
                    <tr>{{ $head }}</tr>
                </thead>
            @endisset
            <tbody class="divide-y divide-ink-900/5 dark:divide-white/8">
                {{ $slot }}
            </tbody>
        </table>
    </div>
    @isset($footer)
        <div class="border-t border-ink-900/5 px-4 py-3 sm:px-5 dark:border-white/8">
            {{ $footer }}
        </div>
    @endisset
</div>
