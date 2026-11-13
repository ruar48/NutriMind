{{--
    Mission and learning-activity completion (LearningAnalytics::completion()).
--}}
@props([
    'completion',
    'title' => 'Mission & activity completion',
    'description' => 'Of what learners started in this period, how much is finished',
])

@php
    $rows = [
        ['key' => 'missions', 'label' => 'Missions', 'icon' => 'flag', 'color' => 'carrot', 'figures' => $completion['missions']],
        ['key' => 'activities', 'label' => 'Learning activities', 'icon' => 'puzzle', 'color' => 'indigo', 'figures' => $completion['activities']],
    ];
@endphp

<x-section :title="$title" :description="$description" icon="list-checks" color="carrot" {{ $attributes }}>
    <div class="flex flex-col gap-5">
        @foreach ($rows as $row)
            <div class="flex items-center gap-4" data-insights-completion="{{ $row['key'] }}">
                <x-progress-ring :value="$row['figures']['rate'] ?? 0" size="md" :color="$row['color']" :label="$row['label'].' completion'">
                    <span class="font-display text-sm font-bold text-ink-950 dark:text-white">{{ $row['figures']['rate'] === null ? '—' : number_format($row['figures']['rate'], 1).'%' }}</span>
                </x-progress-ring>
                <div class="min-w-0 flex-1">
                    <p class="flex items-center gap-2 text-sm font-semibold text-ink-900 dark:text-ink-50">
                        <x-icon :name="$row['icon']" class="size-4 text-ink-400 dark:text-ink-500" />
                        {{ $row['label'] }}
                    </p>
                    <p class="mt-1 text-sm text-ink-600 dark:text-ink-300">
                        @if ($row['figures']['started'] === 0)
                            None started in this period
                        @else
                            {{ number_format($row['figures']['completed']) }} of {{ number_format($row['figures']['started']) }} started are completed
                        @endif
                    </p>
                </div>
            </div>
        @endforeach
    </div>
</x-section>
