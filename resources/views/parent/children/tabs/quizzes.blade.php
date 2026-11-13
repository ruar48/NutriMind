@php
    $resetUrl = route('parent.children.show', [$student, 'tab' => 'quizzes']);
    $description = trans_choice(':count attempt|:count attempts', $attempts->total())
        .($isFiltered ? ' match your filters' : ' · '.trans_choice(':count quiz taken|:count quizzes taken', $summary->quizzesTaken).', '.number_format($summary->quizzesPassed).' passed');
@endphp

<x-filter-bar
    :action="route('parent.children.show', $student)"
    :search="false"
    :reset-url="$resetUrl"
    :preserve="['tab']"
>
    <x-form.select
        name="subject"
        label="Subject"
        hide-label
        placeholder="All subjects"
        :options="$subjectOptions"
        :value="$filters['subject']"
        :old="false"
        class="sm:w-56"
    />
    <x-form.select
        name="result"
        label="Result"
        hide-label
        placeholder="Passed or not"
        :options="[
            \App\Http\Requests\Guardian\ShowChildRequest::RESULT_PASSED => 'Passed',
            \App\Http\Requests\Guardian\ShowChildRequest::RESULT_NOT_PASSED => 'Not passed',
        ]"
        :value="$filters['result']"
        :old="false"
        class="sm:w-44"
    />
</x-filter-bar>

@if ($attempts->isEmpty())
    @if ($isFiltered)
        <x-empty-state variant="card" illustration="search" title="No attempts match your filters" description="Try another subject or result, or clear the filters.">
            <x-slot:actions>
                <x-button variant="secondary" icon="rotate-ccw" :href="$resetUrl">Clear filters</x-button>
            </x-slot:actions>
        </x-empty-state>
    @else
        <x-empty-state
            variant="card"
            icon="clipboard-check"
            title="No quiz attempts yet"
            :description="'Quizzes '.$firstName.' submits in the NutriMind game appear here, with the score and whether they passed.'"
        />
    @endif
@else
    <x-learner.recent-attempts
        :attempts="$attempts"
        :attempt-url="$attemptUrl"
        title="Quiz attempts"
        :description="$description"
        data-child-attempts
    />

    @if ($attemptUrl === null)
        <p class="flex items-center gap-2 text-sm text-ink-600 dark:text-ink-300">
            <x-icon name="info" class="size-4 shrink-0 text-ink-400 dark:text-ink-500" />
            The school shares quiz scores with parents, but not the answers to each question.
        </p>
    @endif

    @if ($attempts->hasPages())
        {{ $attempts->links() }}
    @endif
@endif
