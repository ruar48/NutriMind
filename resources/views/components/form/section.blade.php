@props([
    'title',
    'description' => null,
    'columns' => 1,
])

<section {{ $attributes->class('@container min-w-0') }}>
    <div class="grid grid-cols-1 gap-x-10 gap-y-4 @3xl:grid-cols-3">
        <div class="@3xl:pt-2">
            <h2 class="font-display text-base font-semibold tracking-tight text-ink-950 dark:text-white">{{ $title }}</h2>
            @if ($description)
                <p class="mt-1 text-sm text-ink-600 dark:text-ink-300">{{ $description }}</p>
            @endif
            @isset($aside)
                <div class="mt-4">{{ $aside }}</div>
            @endisset
        </div>
        <div class="@container min-w-0 rounded-2xl bg-white p-5 shadow-card ring-1 ring-ink-900/5 sm:p-6 @3xl:col-span-2 dark:bg-ink-900 dark:shadow-none dark:ring-white/8">
            <div @class(['grid grid-cols-1 gap-5', '@lg:grid-cols-2' => (int) $columns === 2])>
                {{ $slot }}
            </div>
        </div>
    </div>
</section>
