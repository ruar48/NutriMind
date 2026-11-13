@props([
    'templateUrl',
])

@php
    $steps = [
        ['icon' => 'download', 'title' => 'Fill in the template', 'text' => 'One learner per row. Keep the heading row.'],
        ['icon' => 'file-up', 'title' => 'Upload the CSV file', 'text' => 'Save it as CSV in Excel or Google Sheets first.'],
        ['icon' => 'list-checks', 'title' => 'Review every row', 'text' => 'See who is created, enrolled or skipped, and fix errors.'],
        ['icon' => 'key-round', 'title' => 'Import and save the sign-in details', 'text' => 'New passwords are shown once and can be downloaded once.'],
    ];
@endphp

<div {{ $attributes->class('flex flex-col gap-4') }}>
    <ol class="flex flex-col gap-3" aria-label="How importing works">
        @foreach ($steps as $step)
            <li class="flex items-start gap-3">
                <span class="relative grid size-9 shrink-0 place-items-center rounded-xl bg-leaf-50 text-leaf-700 ring-1 ring-leaf-600/15 ring-inset dark:bg-leaf-400/10 dark:text-leaf-300 dark:ring-leaf-400/20">
                    <x-icon :name="$step['icon']" class="size-4" />
                    <span aria-hidden="true" class="absolute -top-1.5 -left-1.5 grid size-4 place-items-center rounded-full bg-ink-900 text-[10px] font-bold text-white tabular-nums dark:bg-white dark:text-ink-950">{{ $loop->iteration }}</span>
                </span>
                <span class="min-w-0 text-sm">
                    <span class="block font-semibold text-ink-900 dark:text-ink-50">{{ $step['title'] }}</span>
                    <span class="block text-ink-600 dark:text-ink-300">{{ $step['text'] }}</span>
                </span>
            </li>
        @endforeach
    </ol>

    <x-button variant="soft" size="sm" icon="download" :href="$templateUrl" class="self-start">Download the template</x-button>
</div>
