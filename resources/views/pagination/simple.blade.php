@if ($paginator->hasPages())
    <nav role="navigation" aria-label="Pagination" class="flex items-center justify-between gap-3">
        @if ($paginator->onFirstPage())
            <span aria-disabled="true" class="inline-flex h-10 items-center gap-1.5 rounded-xl px-3 text-sm font-semibold text-ink-300 pointer-coarse:h-11 dark:text-ink-600">
                <x-icon name="chevron-left" class="size-4" />
                Previous
            </span>
        @else
            <a href="{{ $paginator->previousPageUrl() }}" rel="prev" class="inline-flex h-10 items-center gap-1.5 rounded-xl px-3 text-sm font-semibold text-ink-700 ring-1 ring-ink-900/10 transition ring-inset hover:bg-white hover:text-ink-950 pointer-coarse:h-11 dark:text-ink-200 dark:ring-white/10 dark:hover:bg-white/8 dark:hover:text-white">
                <x-icon name="chevron-left" class="size-4" />
                Previous
            </a>
        @endif

        @if (method_exists($paginator, 'firstItem') && $paginator->firstItem())
            <p class="hidden text-sm text-ink-600 tabular-nums sm:block dark:text-ink-300">
                Showing <span class="font-semibold text-ink-900 dark:text-white">{{ number_format($paginator->firstItem()) }}</span>&ndash;<span class="font-semibold text-ink-900 dark:text-white">{{ number_format($paginator->lastItem()) }}</span>
            </p>
        @endif

        @if ($paginator->hasMorePages())
            <a href="{{ $paginator->nextPageUrl() }}" rel="next" class="inline-flex h-10 items-center gap-1.5 rounded-xl px-3 text-sm font-semibold text-ink-700 ring-1 ring-ink-900/10 transition ring-inset hover:bg-white hover:text-ink-950 pointer-coarse:h-11 dark:text-ink-200 dark:ring-white/10 dark:hover:bg-white/8 dark:hover:text-white">
                Next
                <x-icon name="chevron-right" class="size-4" />
            </a>
        @else
            <span aria-disabled="true" class="inline-flex h-10 items-center gap-1.5 rounded-xl px-3 text-sm font-semibold text-ink-300 pointer-coarse:h-11 dark:text-ink-600">
                Next
                <x-icon name="chevron-right" class="size-4" />
            </span>
        @endif
    </nav>
@endif
