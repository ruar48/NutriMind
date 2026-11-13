@props([
    'preview',
    'classroom' => null,
])

@php
    $statuses = \App\Services\StudentImport\ImportRowStatus::class;
    $counts = $preview->counts();
    $importable = $preview->importableCount();
    $errorCount = $counts[$statuses::Error->value];
    $parentsToCreate = $preview->parentAccountsToCreate();
    $linksToAdd = $preview->guardianLinksToAdd();
    $largeImportThreshold = 100;
    $tiles = [
        ['status' => $statuses::Create, 'hint' => 'accounts to create'],
        ['status' => $statuses::Enroll, 'hint' => $classroom ? 'learners to enroll' : 'choose a classroom to enroll'],
        ['status' => $statuses::Skip, 'hint' => 'nothing changes'],
        ['status' => $statuses::Error, 'hint' => 'rows not imported'],
    ];
@endphp

<div {{ $attributes->class('flex flex-col gap-4') }} data-import-summary>
    <div class="grid grid-cols-2 gap-4 lg:grid-cols-5">
        <x-stat-card
            label="Rows"
            :value="number_format($counts['all'])"
            icon="file-spreadsheet"
            color="indigo"
            hint="learners in the file"
            class="col-span-2 lg:col-span-1"
            data-import-count="all"
            data-animate
            style="--stagger: 1"
        />
        @foreach ($tiles as $tile)
            <x-stat-card
                :label="$tile['status']->pluralLabel()"
                :value="number_format($counts[$tile['status']->value])"
                :icon="$tile['status']->icon()"
                :color="$tile['status']->color()"
                :hint="$tile['hint']"
                :data-import-count="$tile['status']->value"
                data-animate
                :style="'--stagger: '.($loop->iteration + 1)"
            />
        @endforeach
    </div>

    @if ($importable === 0)
        <x-alert variant="danger" title="Nothing can be imported" data-import-notice="nothing">
            Every row is skipped or has errors. Fix the rows in your spreadsheet, save it as CSV and upload it again.
        </x-alert>
    @elseif ($errorCount > 0)
        <x-alert variant="warning" :title="trans_choice(':count row has errors|:count rows have errors', $errorCount)" data-import-notice="errors">
            {{ trans_choice('It is not imported.|They are not imported.', $errorCount) }}
            Fix the {{ \Illuminate\Support\Str::plural('row', $errorCount) }} in your spreadsheet and upload the file again, or import the other {{ trans_choice(':count learner|:count learners', $importable) }} now.
        </x-alert>
    @endif

    @if ($importable > 0 && $linksToAdd > 0)
        <x-alert variant="info" icon="house-heart" title="Parents and guardians" data-import-notice="guardians">
            {{ trans_choice(':count learner is linked to a parent account.|:count learners are linked to parent accounts.', $linksToAdd) }}
            @if ($parentsToCreate > 0)
                {{ trans_choice(':count new parent account is created.|:count new parent accounts are created.', $parentsToCreate) }}
                Parents sign in to the Parent Portal with their email address and the generated password.
            @endif
        </x-alert>
    @endif

    @if ($importable > $largeImportThreshold)
        <x-alert variant="info" icon="hourglass" title="This is a large import" data-import-notice="large">
            Creating {{ number_format($importable) }} accounts can take a few minutes. Keep the page open until the result appears.
        </x-alert>
    @endif
</div>
