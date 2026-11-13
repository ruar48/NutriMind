@props([
    'missions' => [],
    'title' => 'Missions',
    'description' => 'The learner’s latest mission progress in the game',
])

<x-section :title="$title" :description="$description" icon="flag" color="carrot" padding="none" {{ $attributes }}>
    @isset($actions)
        <x-slot:actions>{{ $actions }}</x-slot:actions>
    @endisset

    @if (count($missions) === 0)
        <x-empty-state compact icon="flag" title="No missions started yet" description="Missions appear here once the learner starts them in the game." />
    @else
        <ul class="divide-y divide-ink-900/5 dark:divide-white/8">
            @foreach ($missions as $progress)
                @php
                    $mission = $progress->mission;
                    $moment = $progress->completed_at ?? $progress->updated_at;
                @endphp
                <li class="flex flex-col gap-3 px-5 py-4 sm:px-6">
                    <div class="flex flex-wrap items-start justify-between gap-x-4 gap-y-2">
                        <div class="min-w-0">
                            <p class="font-medium break-words text-ink-900 dark:text-ink-100">{{ $mission->title }}</p>
                            <div class="mt-1.5 flex flex-wrap items-center gap-2">
                                <x-status-badge :status="$mission->type" size="sm" />
                                <x-subject-chip :subject="$mission->subject" size="sm" />
                            </div>
                        </div>
                        <x-status-badge :status="$progress->status" />
                    </div>

                    <x-progress :value="$progress->progress_percent" :color="$progress->status->color()" size="sm" label="Progress" show-value />

                    <div class="flex flex-wrap items-center justify-between gap-x-4 gap-y-1 text-xs text-ink-500 dark:text-ink-400">
                        @if ($moment)
                            <span>
                                {{ $progress->completed_at ? 'Completed' : 'Updated' }}
                                <time datetime="{{ $moment->toIso8601String() }}" title="{{ $moment->format('M j, Y g:i A') }}">{{ $moment->diffForHumans() }}</time>
                            </span>
                        @endif
                        @if ($progress->points_earned > 0)
                            <span class="inline-flex items-center gap-1 font-semibold text-sun-800 tabular-nums dark:text-sun-300">
                                <x-icon name="sparkles" class="size-3.5" />
                                +{{ number_format($progress->points_earned) }} XP
                            </span>
                        @endif
                    </div>
                </li>
            @endforeach
        </ul>
    @endif
</x-section>
