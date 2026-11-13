@props([
    'preview',
])

@php
    $importable = $preview->importableCount();
@endphp

<x-card {{ $attributes->merge(['data-import-commit-bar' => '']) }} data-print-hidden>
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div class="flex min-w-0 items-start gap-3">
            <span @class([
                'grid size-10 shrink-0 place-items-center rounded-xl ring-1 ring-inset',
                'bg-leaf-50 text-leaf-700 ring-leaf-600/15 dark:bg-leaf-400/10 dark:text-leaf-300 dark:ring-leaf-400/20' => $importable > 0,
                'bg-berry-50 text-berry-700 ring-berry-600/15 dark:bg-berry-400/10 dark:text-berry-300 dark:ring-berry-400/20' => $importable === 0,
            ])>
                <x-icon :name="$importable > 0 ? 'rocket' : 'circle-x'" class="size-5" />
            </span>
            <div class="min-w-0">
                <p class="font-display text-base font-semibold text-ink-950 dark:text-white">
                    {{ $importable > 0 ? 'Ready to import '.trans_choice(':count learner|:count learners', $importable) : 'Nothing to import yet' }}
                </p>
                <p class="text-sm text-ink-600 dark:text-ink-300">
                    {{ $importable > 0 ? 'Rows that are skipped or have errors are left out.' : 'Fix the rows with errors in your spreadsheet and upload the file again.' }}
                </p>
            </div>
        </div>
        <div class="flex flex-col-reverse gap-2 sm:flex-row sm:items-center">
            {{ $slot }}
        </div>
    </div>
</x-card>
