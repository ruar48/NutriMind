{{--
    Offline-sync health of the learners in scope (LearningAnalytics::syncHealth()).
--}}
@props([
    'health',
    'title' => 'Offline sync health',
    'description' => null,
])

@php
    $overdueDays = \App\Services\LearnerProgressSummary::SYNC_OVERDUE_AFTER_DAYS;
    $description ??= 'Whether the game has sent each learner’s progress in the last '.$overdueDays.' days';
@endphp

<x-section :title="$title" :description="$description" icon="cloud-check" color="sky" {{ $attributes }}>
    <x-chart.stacked-bar
        variant="thin"
        label="Learners by sync status"
        label-heading="Sync status"
        value-heading="Learners"
        :segments="[
            ['label' => 'Up to date', 'value' => $health['upToDate'], 'color' => 'leaf'],
            ['label' => 'Overdue (over '.$overdueDays.' days)', 'value' => $health['overdue'], 'color' => 'sun'],
            ['label' => 'Played, never synced', 'value' => $health['neverSynced'], 'color' => 'berry'],
            ['label' => 'Not played yet', 'value' => $health['notPlayed'], 'color' => 'slate'],
        ]"
        empty-text="No learners in this view yet."
    />

    <div class="mt-5 flex items-start gap-3 rounded-xl bg-sky-50 px-4 py-3 dark:bg-sky-400/10">
        <x-icon name="wifi-off" class="mt-0.5 size-5 shrink-0 text-sky-700 dark:text-sky-300" />
        <p class="text-sm text-ink-700 dark:text-ink-200">
            @if ($health['attempts'] === 0)
                No quiz attempts in this period yet.
            @else
                <span class="font-semibold text-ink-950 dark:text-white">{{ number_format($health['offlineShare'], 1) }}%</span>
                of quiz attempts were played offline ({{ number_format($health['offlineAttempts']) }} of {{ number_format($health['attempts']) }}).
            @endif
        </p>
    </div>
</x-section>
