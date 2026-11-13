@props([
    'columns' => [],
    'rows' => [],
    'caption' => null,
    'summary' => 'View as table',
])

<details data-chart-table {{ $attributes->class('group/table mt-4') }}>
    <summary class="inline-flex items-center gap-1.5 rounded-lg py-1 text-xs font-semibold text-ink-500 transition hover:text-ink-900 pointer-coarse:py-2.5 dark:text-ink-400 dark:hover:text-white" data-print-hidden>
        <x-icon name="table" class="size-3.5" />
        {{ $summary }}
        <x-icon name="chevron-down" class="size-3.5 transition group-open/table:rotate-180" />
    </summary>
    <div class="mt-3 overflow-x-auto rounded-xl ring-1 ring-ink-900/8 dark:ring-white/10">
        @if ($slot->hasActualContent())
            {{ $slot }}
        @else
            <table class="min-w-full text-left text-xs">
                @if ($caption)
                    <caption class="sr-only">{{ $caption }}</caption>
                @endif
                <thead class="bg-ink-50/80 text-ink-500 dark:bg-white/3 dark:text-ink-400">
                    <tr>
                        @foreach ($columns as $column)
                            <th scope="col" @class(['px-3 py-2 font-semibold whitespace-nowrap', 'text-right' => ! $loop->first])>{{ $column }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody class="divide-y divide-ink-900/5 dark:divide-white/8">
                    @foreach ($rows as $row)
                        <tr>
                            @foreach (array_values($row) as $cell)
                                @if ($loop->first)
                                    <th scope="row" class="px-3 py-2 font-medium whitespace-nowrap text-ink-800 dark:text-ink-100">{{ $cell }}</th>
                                @else
                                    <td class="px-3 py-2 text-right whitespace-nowrap text-ink-700 tabular-nums dark:text-ink-200">{{ $cell }}</td>
                                @endif
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
</details>
