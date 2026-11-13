@if ($paginator->total() > 0)
    <nav role="navigation" aria-label="Pagination" class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <p class="text-sm text-ink-600 dark:text-ink-300">
            Showing
            <span class="font-semibold text-ink-900 tabular-nums dark:text-white">{{ number_format($paginator->firstItem()) }}</span>&ndash;<span class="font-semibold text-ink-900 tabular-nums dark:text-white">{{ number_format($paginator->lastItem()) }}</span>
            of
            <span class="font-semibold text-ink-900 tabular-nums dark:text-white">{{ number_format($paginator->total()) }}</span>
            {{ \Illuminate\Support\Str::plural('result', $paginator->total()) }}
        </p>

        @if ($paginator->hasPages())
            <div class="flex items-center gap-1">
                @if ($paginator->onFirstPage())
                    <span aria-disabled="true" class="grid size-10 place-items-center rounded-xl text-ink-300 pointer-coarse:size-11 dark:text-ink-600">
                        <x-icon name="chevron-left" class="size-4" />
                        <span class="sr-only">Previous page</span>
                    </span>
                @else
                    <a href="{{ $paginator->previousPageUrl() }}" rel="prev" aria-label="Previous page" class="grid size-10 place-items-center rounded-xl text-ink-600 ring-1 ring-ink-900/10 transition ring-inset hover:bg-white hover:text-ink-950 pointer-coarse:size-11 dark:text-ink-300 dark:ring-white/10 dark:hover:bg-white/8 dark:hover:text-white">
                        <x-icon name="chevron-left" class="size-4" />
                    </a>
                @endif

                <span class="px-3 text-sm font-medium text-ink-700 tabular-nums sm:hidden dark:text-ink-200">
                    Page {{ $paginator->currentPage() }} of {{ $paginator->lastPage() }}
                </span>

                <div class="hidden items-center gap-1 sm:flex">
                    @foreach ($elements as $element)
                        @if (is_string($element))
                            <span aria-hidden="true" class="grid size-10 place-items-center text-sm text-ink-400 dark:text-ink-500">&hellip;</span>
                        @endif

                        @if (is_array($element))
                            @foreach ($element as $page => $url)
                                @if ($page == $paginator->currentPage())
                                    <span aria-current="page" class="grid size-10 place-items-center rounded-xl bg-leaf-600 text-sm font-semibold text-white tabular-nums shadow-[inset_0_1px_0_rgb(255_255_255/0.18)] dark:bg-leaf-500">{{ $page }}</span>
                                @else
                                    <a href="{{ $url }}" aria-label="Go to page {{ $page }}" class="grid size-10 place-items-center rounded-xl text-sm font-medium text-ink-600 tabular-nums transition hover:bg-white hover:text-ink-950 hover:ring-1 hover:ring-ink-900/10 dark:text-ink-300 dark:hover:bg-white/8 dark:hover:text-white dark:hover:ring-white/10">{{ $page }}</a>
                                @endif
                            @endforeach
                        @endif
                    @endforeach
                </div>

                @if ($paginator->hasMorePages())
                    <a href="{{ $paginator->nextPageUrl() }}" rel="next" aria-label="Next page" class="grid size-10 place-items-center rounded-xl text-ink-600 ring-1 ring-ink-900/10 transition ring-inset hover:bg-white hover:text-ink-950 pointer-coarse:size-11 dark:text-ink-300 dark:ring-white/10 dark:hover:bg-white/8 dark:hover:text-white">
                        <x-icon name="chevron-right" class="size-4" />
                    </a>
                @else
                    <span aria-disabled="true" class="grid size-10 place-items-center rounded-xl text-ink-300 pointer-coarse:size-11 dark:text-ink-600">
                        <x-icon name="chevron-right" class="size-4" />
                        <span class="sr-only">Next page</span>
                    </span>
                @endif
            </div>
        @endif
    </nav>
@endif
