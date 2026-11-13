@props([
    'title',
    'description' => null,
    'breadcrumbs' => [],
    'eyebrow' => null,
])

<header {{ $attributes->class('flex flex-col gap-4') }} data-animate>
    @if (count($breadcrumbs) > 0)
        <x-breadcrumbs :items="$breadcrumbs" />
    @endif

    <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div class="flex min-w-0 items-start gap-4">
            @isset($leading)
                <div class="shrink-0">{{ $leading }}</div>
            @endisset
            <div class="min-w-0">
                @if ($eyebrow)
                    <p class="mb-1 text-xs font-semibold tracking-[0.14em] text-leaf-700 uppercase dark:text-leaf-300">{{ $eyebrow }}</p>
                @endif
                <h1 class="font-display text-2xl font-bold tracking-tight text-ink-950 sm:text-[1.875rem] sm:leading-tight dark:text-white">{{ $title }}</h1>
                @if ($description)
                    <p class="mt-1.5 max-w-3xl text-sm text-ink-600 sm:text-[0.9375rem] dark:text-ink-300">{{ $description }}</p>
                @endif
                @isset($meta)
                    <div class="mt-3 flex flex-wrap items-center gap-2">{{ $meta }}</div>
                @endisset
            </div>
        </div>

        @isset($actions)
            <div class="flex flex-wrap items-center gap-2 sm:shrink-0 sm:justify-end">
                {{ $actions }}
            </div>
        @endisset
    </div>
</header>
