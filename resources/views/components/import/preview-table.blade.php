@props([
    'preview',
    'rows',
    'status' => null,
    'baseUrl',
])

@php
    $statuses = \App\Services\StudentImport\ImportRowStatus::cases();
    $counts = $preview->counts();
    $tabUrl = fn (?\App\Services\StudentImport\ImportRowStatus $tab): string => $tab === null ? $baseUrl : $baseUrl.'?'.http_build_query(['status' => $tab->value]);
@endphp

<x-section
    title="Rows"
    description="Check what happens to each learner before you import."
    icon="list-checks"
    padding="none"
    {{ $attributes->merge(['data-import-rows' => '']) }}
>
    <div class="px-3 pt-2 sm:px-4">
        <x-tabs label="Filter rows by status">
            <x-tab :href="$tabUrl(null)" :active="$status === null" icon="list" :count="$counts['all']">All rows</x-tab>
            @foreach ($statuses as $tab)
                <x-tab :href="$tabUrl($tab)" :active="$status === $tab" :icon="$tab->icon()" :count="$counts[$tab->value]">{{ $tab->pluralLabel() }}</x-tab>
            @endforeach
        </x-tabs>
    </div>

    @if ($rows->isEmpty())
        <x-empty-state
            compact
            icon="filter"
            :title="$status?->emptyTitle() ?? 'No rows on this page'"
            description="Pick another tab to see the other rows of the file."
            data-import-rows-empty
        >
            <x-slot:actions>
                <x-button variant="secondary" size="sm" icon="list" :href="$tabUrl(null)">Show all rows</x-button>
            </x-slot:actions>
        </x-empty-state>
    @else
        <x-table flush :caption="'Rows of the uploaded file'.($status ? ': '.$status->pluralLabel() : '')">
            <x-slot:head>
                <x-table.th align="right">Row</x-table.th>
                <x-table.th>Learner</x-table.th>
                <x-table.th>Grade</x-table.th>
                <x-table.th>Guardian</x-table.th>
                <x-table.th>Status</x-table.th>
                <x-table.th>Details</x-table.th>
            </x-slot:head>

            @foreach ($rows as $row)
                @php
                    $data = $row->data;
                    $existing = $row->existingStudent;
                    $username = $existing?->username ?? $data->username ?? ($data->rawValue('username') !== '' ? $data->rawValue('username') : null);
                    $lrn = $data->lrn ?? $data->rawValue('lrn');
                    $gradeText = $data->rawValue('grade_level');
                    $facts = array_values(array_filter([
                        $data->sex?->label(),
                        $data->birthdate ? 'Born '.\Carbon\CarbonImmutable::parse($data->birthdate)->format('M j, Y') : null,
                    ]));
                    $guardianEmail = $row->guardian?->email ?? $data->rawValue('guardian_email');
                    $guardianName = $row->guardian?->displayName() ?? $data->rawValue('guardian_name');
                @endphp
                <x-table.row :data-import-row="$row->number()" :data-import-status="$row->status->value">
                    <x-table.td align="right" numeric class="align-top font-mono text-xs">{{ $row->number() }}</x-table.td>
                    <x-table.td primary class="min-w-52 align-top">
                        <div class="flex items-start gap-3">
                            <x-avatar :name="$data->displayName() !== '' ? $data->displayName() : '?'" size="sm" />
                            <div class="min-w-0">
                                <p class="truncate">{{ $data->displayName() !== '' ? $data->displayName() : 'No name' }}</p>
                                <p class="truncate text-xs font-normal text-ink-500 dark:text-ink-400">
                                    @if ($username !== null)
                                        <span class="font-mono">{{ $username }}</span>
                                    @elseif ($row->status === \App\Services\StudentImport\ImportRowStatus::Create)
                                        <span class="italic">Username generated on import</span>
                                    @else
                                        No username
                                    @endif
                                </p>
                                <p class="text-xs font-normal text-ink-500 tabular-nums dark:text-ink-400">{{ $lrn !== '' ? 'LRN '.$lrn : 'No LRN' }}</p>
                                @if ($facts !== [])
                                    <p class="text-xs font-normal text-ink-500 dark:text-ink-400">{{ implode(' · ', $facts) }}</p>
                                @endif
                            </div>
                        </div>
                    </x-table.td>
                    <x-table.td class="align-top whitespace-nowrap">
                        @if ($data->grade)
                            <x-badge :color="$data->grade->color()" size="sm">{{ $data->grade->label() }}</x-badge>
                        @elseif ($gradeText !== '')
                            <span class="text-xs">{{ $gradeText }}</span>
                        @else
                            <span class="text-ink-400 dark:text-ink-500">—</span>
                        @endif
                    </x-table.td>
                    <x-table.td class="min-w-48 align-top">
                        @if ($guardianEmail !== '' || $guardianName !== '')
                            <div class="flex min-w-0 flex-col gap-1">
                                @if ($guardianName !== '')
                                    <p class="truncate text-sm font-medium text-ink-800 dark:text-ink-100">{{ $guardianName }}</p>
                                @endif
                                @if ($guardianEmail !== '')
                                    <p class="truncate text-xs text-ink-500 dark:text-ink-400">{{ $guardianEmail }}</p>
                                @endif
                                @if ($row->guardian)
                                    <div class="flex flex-wrap items-center gap-1.5">
                                        <x-status-badge :status="$row->guardian->action" size="sm" />
                                        <span class="text-xs text-ink-500 dark:text-ink-400">{{ $row->guardian->relationship->label() }}</span>
                                    </div>
                                @endif
                            </div>
                        @else
                            <span class="text-ink-400 dark:text-ink-500">—</span>
                        @endif
                    </x-table.td>
                    <x-table.td class="align-top whitespace-nowrap"><x-status-badge :status="$row->status" /></x-table.td>
                    <x-table.td class="min-w-64 align-top">
                        @if ($row->errors !== [])
                            <ul class="flex flex-col gap-1" aria-label="Problems in row {{ $row->number() }}">
                                @foreach ($row->errors as $message)
                                    <li class="flex items-start gap-1.5 text-sm text-berry-700 dark:text-berry-300" data-import-error>
                                        <x-icon name="circle-alert" class="mt-0.5 size-4 shrink-0" />
                                        <span>{{ $message }}</span>
                                    </li>
                                @endforeach
                            </ul>
                        @elseif ($row->notes !== [])
                            <ul class="flex flex-col gap-1">
                                @foreach ($row->notes as $note)
                                    <li class="flex items-start gap-1.5 text-sm text-ink-600 dark:text-ink-300" data-import-note>
                                        <x-icon name="info" class="mt-0.5 size-4 shrink-0 text-ink-400 dark:text-ink-500" />
                                        <span>{{ $note }}</span>
                                    </li>
                                @endforeach
                            </ul>
                        @else
                            <span class="text-sm text-ink-500 dark:text-ink-400">{{ $row->status->description() }}</span>
                        @endif
                    </x-table.td>
                </x-table.row>
            @endforeach
        </x-table>

        @if ($rows->hasPages())
            <div class="border-t border-ink-900/5 px-5 py-3 dark:border-white/8">{{ $rows->links() }}</div>
        @endif
    @endif
</x-section>
