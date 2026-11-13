{{--
    Learners per DepEd performance band (LearningAnalytics::bandDistribution()).
--}}
@props([
    'bands' => [],
    'title' => 'Performance bands',
    'description' => 'Learners by their average quiz result',
])

@php
    $learners = array_sum($bands);
    $needsAttention = $bands[\App\Enums\PerformanceBand::DidNotMeetExpectations->value] ?? 0;
    $assessed = $learners - ($bands[\App\Enums\PerformanceBand::NoData->value] ?? 0);
@endphp

<x-section :title="$title" :description="$description" icon="gauge" color="teal" {{ $attributes }}>
    <x-chart.stacked-bar
        label="Learners by performance band"
        label-heading="Band"
        value-heading="Learners"
        :segments="collect(\App\Enums\PerformanceBand::cases())->map(fn (\App\Enums\PerformanceBand $band): array => [
            'label' => $band->label(),
            'value' => $bands[$band->value] ?? 0,
            'color' => $band->color(),
        ])->all()"
        empty-text="No learners in this view yet."
    />

    @if ($learners > 0)
        <dl class="mt-5 grid grid-cols-2 gap-3 text-sm">
            <div class="rounded-xl bg-ink-50 px-3 py-2.5 dark:bg-white/5">
                <dt class="text-ink-500 dark:text-ink-400">Assessed</dt>
                <dd class="mt-0.5 font-display text-lg font-semibold text-ink-950 dark:text-white">{{ number_format($assessed) }} <span class="text-sm font-normal text-ink-500 dark:text-ink-400">of {{ number_format($learners) }}</span></dd>
            </div>
            <div class="rounded-xl bg-berry-50 px-3 py-2.5 dark:bg-berry-400/10">
                <dt class="flex items-center gap-1.5 text-berry-800 dark:text-berry-200">
                    <x-icon name="triangle-alert" class="size-4" />
                    Below 75%
                </dt>
                <dd class="mt-0.5 font-display text-lg font-semibold text-ink-950 dark:text-white">{{ number_format($needsAttention) }}</dd>
            </div>
        </dl>
    @endif
</x-section>
