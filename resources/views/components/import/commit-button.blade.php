@props([
    'preview',
    'action',
    'classroom' => null,
    'id',
])

@php
    $statuses = \App\Services\StudentImport\ImportRowStatus::class;
    $importable = $preview->importableCount();
    $creates = $preview->count($statuses::Create);
    $enrolls = $preview->count($statuses::Enroll);
    $parents = $preview->parentAccountsToCreate();
    $notImported = $preview->count($statuses::Skip) + $preview->count($statuses::Error);
    $label = 'Import '.trans_choice(':count learner|:count learners', $importable);
@endphp

@if ($importable === 0)
    <x-button icon="file-up" disabled data-import-commit="disabled" {{ $attributes }}>{{ $label }}</x-button>
@else
    <x-confirm-dialog
        :id="$id"
        :action="$action"
        method="POST"
        :danger="false"
        icon="file-up"
        :title="$label.'?'"
        :message="$classroom ? 'Learners are added to '.$classroom->name.'.' : 'Learners get accounts without a classroom.'"
        confirm="Import now"
        :button="$label"
        button-variant="primary"
        button-size="md"
        button-icon="file-up"
        data-import-commit="enabled"
        {{ $attributes }}
    >
        <ul class="flex flex-col gap-2">
            @if ($creates > 0)
                <li class="flex items-center gap-2"><x-icon name="user-plus" class="size-4 text-leaf-600 dark:text-leaf-400" />{{ trans_choice(':count new learner account|:count new learner accounts', $creates) }}</li>
            @endif
            @if ($enrolls > 0)
                <li class="flex items-center gap-2"><x-icon name="user-check" class="size-4 text-sky-600 dark:text-sky-400" />{{ trans_choice(':count existing learner enrolled|:count existing learners enrolled', $enrolls) }}</li>
            @endif
            @if ($parents > 0)
                <li class="flex items-center gap-2"><x-icon name="house-heart" class="size-4 text-carrot-600 dark:text-carrot-400" />{{ trans_choice(':count new parent account|:count new parent accounts', $parents) }}</li>
            @endif
            @if ($notImported > 0)
                <li class="flex items-center gap-2"><x-icon name="circle-dashed" class="size-4 text-ink-400 dark:text-ink-500" />{{ trans_choice(':count row is not imported (skipped or with errors)|:count rows are not imported (skipped or with errors)', $notImported) }}</li>
            @endif
        </ul>
        <p class="mt-3 text-ink-600 dark:text-ink-300">You see the new sign-in details once, right after the import.</p>
    </x-confirm-dialog>
@endif
