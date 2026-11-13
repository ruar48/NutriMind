@props([
    'attempts' => [],
    'attemptUrl' => null,
    'title' => 'Recent quiz attempts',
    'description' => 'The latest quizzes the learner submitted',
])

<x-section :title="$title" :description="$description" icon="clipboard-check" color="grape" {{ $attributes }}>
    @isset($actions)
        <x-slot:actions>{{ $actions }}</x-slot:actions>
    @endisset

    @if (count($attempts) === 0)
        <x-empty-state compact icon="clipboard-check" title="No quiz attempts yet" description="Quizzes the learner submits in the game appear here." />
    @else
        <x-timeline>
            @foreach ($attempts as $attempt)
                <x-timeline.item
                    :title="$attempt->quiz->title"
                    :time="$attempt->submitted_at"
                    :icon="$attempt->passed ? 'circle-check' : 'triangle-alert'"
                    :color="$attempt->passed ? 'leaf' : 'berry'"
                    :href="$attemptUrl instanceof \Closure ? $attemptUrl($attempt) : null"
                >
                    <div class="mt-1.5 flex flex-wrap items-center gap-2">
                        <x-score-pill :value="(float) $attempt->percentage" size="sm" />
                        <x-badge :color="$attempt->passed ? 'leaf' : 'berry'" :icon="$attempt->passed ? 'check' : 'x'" size="sm">{{ $attempt->passed ? 'Passed' : 'Not passed' }}</x-badge>
                        <x-subject-chip :subject="$attempt->quiz->subject" size="sm" />
                        @if ($attempt->is_offline)
                            <x-badge color="sky" icon="wifi-off" size="sm">Played offline</x-badge>
                        @endif
                    </div>
                    <p class="mt-1 text-xs text-ink-500 tabular-nums dark:text-ink-400">
                        Attempt {{ $attempt->attempt_number }} · {{ $attempt->score }} / {{ $attempt->max_score }} points
                    </p>
                </x-timeline.item>
            @endforeach
        </x-timeline>
    @endif
</x-section>
