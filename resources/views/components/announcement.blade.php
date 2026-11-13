@props([
    'text',
    'id' => null,
])

@php
    $id ??= substr(hash('sha256', (string) $text), 0, 16);
@endphp

<div data-announcement="{{ $id }}" {{ $attributes->class('border-b border-sun-500/20 bg-sun-50 dark:border-sun-400/15 dark:bg-sun-400/10') }} data-print-hidden>
    <div class="mx-auto flex max-w-[1440px] items-start gap-3 px-4 py-2.5 sm:items-center sm:px-6 lg:px-8">
        <span class="grid size-7 shrink-0 place-items-center rounded-lg bg-sun-400 text-sun-950 shadow-sm">
            <x-icon name="megaphone" class="size-4" stroke-width="2" />
        </span>
        <p class="min-w-0 flex-1 pt-1 text-sm text-sun-950 sm:pt-0 dark:text-sun-100">
            <span class="font-semibold">Announcement:</span>
            {{ $text }}
        </p>
        <button type="button" data-announcement-dismiss data-js-only class="-my-1 grid size-8 shrink-0 place-items-center rounded-lg text-sun-900/70 transition hover:bg-sun-500/15 hover:text-sun-950 pointer-coarse:size-11 dark:text-sun-100/70 dark:hover:bg-sun-400/15 dark:hover:text-sun-50">
            <x-icon name="x" class="size-4" />
            <span class="sr-only">Dismiss announcement</span>
        </button>
    </div>
</div>
