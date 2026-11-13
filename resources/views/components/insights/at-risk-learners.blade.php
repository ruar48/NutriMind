{{--
    Learners who need attention (LearningAnalytics::atRiskLearners()).
    Props: atRisk (result array), url (?Closure(User): ?string — the learner page), viewAllUrl.
--}}
@props([
    'atRisk' => ['count' => 0, 'lowScore' => 0, 'inactive' => 0, 'learners' => []],
    'url' => null,
    'viewAllUrl' => null,
    'title' => 'Needs attention',
    'description' => null,
])

@php
    $inactiveDays = \App\Services\Analytics\LearningAnalytics::INACTIVE_AFTER_DAYS;
    $description ??= 'Average below 75% or no game activity for '.$inactiveDays.'+ days';
    $hidden = $atRisk['count'] - count($atRisk['learners']);
@endphp

<x-section :title="$title" :description="$description" icon="triangle-alert" color="berry" {{ $attributes }}>
    @if ($viewAllUrl && $atRisk['count'] > 0)
        <x-slot:actions>
            <x-button variant="ghost" size="sm" icon-trailing="arrow-right" :href="$viewAllUrl">View learners</x-button>
        </x-slot:actions>
    @endif

    @if ($atRisk['count'] === 0)
        <x-empty-state compact illustration="trophy" title="Everyone is on track" description="No learner is below 75% or away from the game right now." />
    @else
        <div class="mb-4 flex flex-wrap gap-2">
            <x-badge color="berry" icon="triangle-alert">{{ trans_choice(':count low average|:count low averages', $atRisk['lowScore']) }}</x-badge>
            <x-badge color="sun" icon="clock">{{ trans_choice(':count inactive|:count inactive', $atRisk['inactive']) }}</x-badge>
        </div>

        <ul class="flex flex-col divide-y divide-ink-900/5 dark:divide-white/8">
            @foreach ($atRisk['learners'] as $entry)
                @php
                    $student = $entry['student'];
                    $link = $url instanceof \Closure ? $url($student) : null;
                @endphp
                <li class="flex items-center gap-3 py-3" data-insights-at-risk="{{ $student->id }}">
                    <x-avatar :name="$student->name" size="sm" />
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-sm font-semibold text-ink-900 dark:text-ink-50">
                            @if ($link)
                                <a href="{{ $link }}" class="hover:underline">{{ $student->name }}</a>
                            @else
                                {{ $student->name }}
                            @endif
                        </p>
                        <div class="mt-1 flex flex-wrap gap-1.5">
                            @if ($entry['lowScore'])
                                <x-badge color="berry" size="sm" icon="triangle-alert">{{ $entry['band']->shortLabel() }} · {{ number_format($entry['averageScore'], 1) }}%</x-badge>
                            @endif
                            @if ($entry['inactive'])
                                <x-badge color="sun" size="sm" icon="clock">
                                    @if ($entry['lastActiveAt'] === null)
                                        Hasn’t played yet
                                    @else
                                        Inactive {{ (int) $entry['lastActiveAt']->startOfDay()->diffInDays(now()->startOfDay()) }} days
                                    @endif
                                </x-badge>
                            @endif
                        </div>
                    </div>
                    @if ($entry['lastActiveAt'] && ! $entry['inactive'])
                        <time class="hidden shrink-0 text-xs text-ink-500 sm:block dark:text-ink-400" datetime="{{ $entry['lastActiveAt']->toIso8601String() }}" title="{{ $entry['lastActiveAt']->format('M j, Y g:i A') }}">
                            Played {{ $entry['lastActiveAt']->diffForHumans() }}
                        </time>
                    @endif
                </li>
            @endforeach
        </ul>

        @if ($hidden > 0)
            <p class="mt-3 text-xs text-ink-500 dark:text-ink-400">and {{ trans_choice(':count more learner|:count more learners', $hidden) }}.</p>
        @endif
    @endif
</x-section>
