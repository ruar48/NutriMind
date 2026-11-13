@props([
    'classroom',
    'quizzes',
])

@php
    $teacherId = auth()->id();
    $roster = (int) $classroom->students_count;
    $canCreateQuiz = auth()->user()?->can('create', \App\Models\Quiz::class) ?? false;
@endphp

<x-section
    title="Assigned quizzes"
    :description="trans_choice(':count quiz assigned to this class|:count quizzes assigned to this class', $classroom->quizzes_count)"
    icon="clipboard-check"
    color="grape"
    padding="none"
    {{ $attributes }}
    data-animate
>
    @if ($canCreateQuiz)
        <x-slot:actions>
            <x-button variant="soft" size="sm" icon="plus" :href="route('teacher.quizzes.create')">New quiz</x-button>
        </x-slot:actions>
    @endif

    @if ($quizzes->isEmpty())
        <x-empty-state
            illustration="empty-plate"
            title="No quizzes assigned yet"
            description="Assign a quiz to this class in the quiz settings. Learners see it in the game once it is published."
        >
            @if ($canCreateQuiz)
                <x-slot:actions>
                    <x-button icon="plus" :href="route('teacher.quizzes.create')">New quiz</x-button>
                </x-slot:actions>
            @endif
        </x-empty-state>
    @else
        <x-table flush :caption="'Quizzes assigned to '.$classroom->name">
            <x-slot:head>
                <x-table.th>Quiz</x-table.th>
                <x-table.th>Status</x-table.th>
                <x-table.th>Due</x-table.th>
                <x-table.th>Completion</x-table.th>
                <x-table.th align="right">Average</x-table.th>
                <x-table.th align="right"><span class="sr-only">Actions</span></x-table.th>
            </x-slot:head>

            @foreach ($quizzes as $quiz)
                @php
                    $isOwnQuiz = (int) $quiz->teacher_id === (int) $teacherId;
                    $attempted = (int) $quiz->attempted_count;
                    $average = $quiz->classroom_average === null ? null : round((float) $quiz->classroom_average, 1);
                    $dueAt = $quiz->pivot->due_at;
                @endphp
                <x-table.row data-assigned-quiz="{{ $quiz->id }}">
                    <x-table.td primary>
                        <div class="min-w-0">
                            @if ($isOwnQuiz)
                                <a href="{{ route('teacher.quizzes.show', $quiz) }}" class="block max-w-xs truncate hover:text-leaf-700 hover:underline dark:hover:text-leaf-300">{{ $quiz->title }}</a>
                            @else
                                <span class="block max-w-xs truncate">{{ $quiz->title }}</span>
                            @endif
                            <p class="text-xs font-normal text-ink-500 dark:text-ink-400">{{ trans_choice(':count question|:count questions', $quiz->questions_count) }}</p>
                        </div>
                    </x-table.td>
                    <x-table.td><x-status-badge :status="$quiz->status" size="sm" /></x-table.td>
                    <x-table.td class="whitespace-nowrap">
                        @if ($dueAt)
                            <time datetime="{{ $dueAt->toIso8601String() }}" title="{{ $dueAt->format('M j, Y g:i A') }}">{{ $dueAt->format('M j, Y') }}</time>
                            <p @class([
                                'text-xs',
                                'text-berry-700 dark:text-berry-300' => $dueAt->isPast() && $quiz->isPublished(),
                                'text-ink-500 dark:text-ink-400' => ! ($dueAt->isPast() && $quiz->isPublished()),
                            ])>{{ $dueAt->diffForHumans() }}</p>
                        @else
                            <span class="text-ink-400 dark:text-ink-500">No due date</span>
                        @endif
                    </x-table.td>
                    <x-table.td class="min-w-40" data-assigned-quiz-completion="{{ $attempted }}/{{ $roster }}">
                        <x-progress :value="$attempted" :max="max(1, $roster)" size="sm" color="grape" label="Submitted" show-value :value-text="$attempted.' of '.$roster" />
                    </x-table.td>
                    <x-table.td align="right" data-assigned-quiz-average>
                        <x-score-pill :value="$average" />
                    </x-table.td>
                    <x-table.td align="right">
                        @if ($isOwnQuiz)
                            <div class="flex justify-end gap-1">
                                <x-button variant="ghost" size="xs" icon="square-pen" :href="route('teacher.quizzes.show', $quiz)">Builder</x-button>
                                <x-button variant="ghost" size="xs" icon="chart-column" :href="route('teacher.quizzes.results.show', $quiz)">Results</x-button>
                            </div>
                        @endif
                    </x-table.td>
                </x-table.row>
            @endforeach
        </x-table>

        @if ($quizzes->hasPages())
            <div class="border-t border-ink-900/5 px-5 py-3 dark:border-white/8">{{ $quizzes->links() }}</div>
        @endif
    @endif
</x-section>
