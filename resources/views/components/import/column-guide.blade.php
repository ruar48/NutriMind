@props([
    'templateUrl',
    'classroomGrade' => null,
])

@php
    $maxRows = number_format(\App\Services\StudentImport\ImportColumns::maxRows());
    $relationships = implode(', ', array_map(fn (\App\Enums\GuardianRelationship $relationship): string => $relationship->value, \App\Enums\GuardianRelationship::cases()));

    $columns = [
        [
            'name' => 'first_name, last_name',
            'need' => 'required',
            'accepts' => 'Up to 60 characters each. Or use one name column instead (up to 120 characters).',
        ],
        [
            'name' => 'lrn',
            'need' => 'optional',
            'accepts' => '12 digits. A learner who already has this LRN is enrolled instead of created. Format the column as Text in Excel so LRNs keep every digit.',
        ],
        [
            'name' => 'grade_level',
            'need' => $classroomGrade ? 'optional' : 'required',
            'accepts' => '5, 6, Grade 5 or Grade 6.'.($classroomGrade ? ' Blank cells use '.$classroomGrade->label().'; other grades are rejected.' : ''),
        ],
        [
            'name' => 'sex',
            'need' => 'optional',
            'accepts' => 'male, female, M or F.',
        ],
        [
            'name' => 'birthdate',
            'need' => 'optional',
            'accepts' => 'YYYY-MM-DD or MM/DD/YYYY, before today.',
        ],
        [
            'name' => 'username',
            'need' => 'optional',
            'accepts' => 'Letters, numbers, dashes and underscores, up to 30 characters. Generated when blank.',
        ],
        [
            'name' => 'guardian_name',
            'need' => 'optional',
            'accepts' => 'Needed to create a new parent account, up to 120 characters.',
        ],
        [
            'name' => 'guardian_email',
            'need' => 'optional',
            'accepts' => 'Links the parent account with this email, or creates one with a generated password.',
        ],
        [
            'name' => 'guardian_relationship',
            'need' => 'optional',
            'accepts' => $relationships.'. Blank means guardian.',
        ],
    ];
@endphp

<x-section
    title="Columns"
    :description="'Headings are not case-sensitive and the order does not matter. Up to '.$maxRows.' learners per file; comma- or semicolon-separated.'"
    icon="table"
    color="sky"
    padding="none"
    {{ $attributes }}
>
    <x-slot:actions>
        <x-button variant="secondary" size="sm" icon="download" :href="$templateUrl">Template</x-button>
    </x-slot:actions>

    <x-table flush caption="Columns of the learner CSV file">
        <x-slot:head>
            <x-table.th>Column</x-table.th>
            <x-table.th>Needed</x-table.th>
            <x-table.th>Accepted values</x-table.th>
        </x-slot:head>

        @foreach ($columns as $column)
            <x-table.row data-import-column="{{ $column['name'] }}">
                <x-table.td primary class="whitespace-nowrap">
                    <code class="rounded-md bg-ink-900/5 px-1.5 py-0.5 font-mono text-xs text-ink-900 dark:bg-white/8 dark:text-ink-50">{{ $column['name'] }}</code>
                </x-table.td>
                <x-table.td class="whitespace-nowrap">
                    @if ($column['need'] === 'required')
                        <x-badge color="carrot" size="sm" icon="circle-alert">Required</x-badge>
                    @else
                        <span class="text-xs text-ink-500 dark:text-ink-400">Optional</span>
                    @endif
                </x-table.td>
                <x-table.td class="min-w-64">{{ $column['accepts'] }}</x-table.td>
            </x-table.row>
        @endforeach
    </x-table>
</x-section>
