@props([
    'cancel' => null,
    'cancelLabel' => 'Cancel',
    'submit' => 'Save changes',
    'submitIcon' => 'check',
    'submitVariant' => 'primary',
    'sticky' => true,
])

<div {{ $attributes->class([
    'sticky bottom-0 z-20 -mx-4 border-t border-ink-900/8 bg-paper/85 px-4 py-3 pb-[max(0.75rem,env(safe-area-inset-bottom))] backdrop-blur-md sm:-mx-6 sm:px-6 lg:-mx-8 lg:px-8 dark:border-white/8 dark:bg-ink-950/85' => $sticky,
    'pt-2' => ! $sticky,
]) }} data-print-hidden>
    <div class="flex flex-col-reverse gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div class="flex min-w-0 flex-wrap items-center gap-2 text-sm text-ink-500 dark:text-ink-400">
            {{ $slot }}
        </div>
        <div class="flex flex-col-reverse gap-2 sm:flex-row sm:items-center">
            @if ($cancel)
                <x-button :href="$cancel" variant="ghost">{{ $cancelLabel }}</x-button>
            @endif
            <x-button type="submit" :variant="$submitVariant" :icon="$submitIcon">{{ $submit }}</x-button>
        </div>
    </div>
</div>
