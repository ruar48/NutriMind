@props([
    'feedback',
    'learnerUrl' => null,
    'attemptUrl' => null,
    'showLearner' => true,
    'actions' => true,
])

@php
    $student = $feedback->student;
    $attempt = $feedback->quizAttempt;
@endphp

<x-card padding="none" {{ $attributes->class('flex flex-col') }} data-feedback-card="{{ $feedback->getKey() }}">
    <div class="flex flex-1 flex-col gap-4 p-5 sm:p-6">
        <div class="flex flex-wrap items-start justify-between gap-3">
            @if ($showLearner)
                <div class="flex min-w-0 items-center gap-3">
                    <x-avatar :name="$student->name" size="md" />
                    <div class="min-w-0">
                        <p class="truncate font-semibold text-ink-900 dark:text-white">
                            @if ($learnerUrl)
                                <a href="{{ $learnerUrl }}" class="rounded hover:text-leaf-700 hover:underline dark:hover:text-leaf-300">{{ $student->name }}</a>
                            @else
                                {{ $student->name }}
                            @endif
                        </p>
                        @if ($feedback->classroom)
                            <p class="truncate text-xs text-ink-500 dark:text-ink-400">{{ $feedback->classroom->name }}</p>
                        @endif
                    </div>
                </div>
            @elseif ($feedback->classroom)
                <p class="min-w-0 truncate text-sm font-medium text-ink-700 dark:text-ink-200">{{ $feedback->classroom->name }}</p>
            @endif
            <x-status-badge :status="$feedback->type" />
        </div>

        <p class="text-sm leading-relaxed break-words whitespace-pre-line text-ink-800 dark:text-ink-100" data-feedback-message>{{ $feedback->message }}</p>

        @if ($attempt)
            <p class="flex min-w-0 items-center gap-2 text-sm text-ink-600 dark:text-ink-300">
                <x-icon name="clipboard-check" class="size-4 shrink-0 text-ink-400 dark:text-ink-500" />
                <span class="min-w-0 truncate">
                    About
                    @if ($attemptUrl)
                        <a href="{{ $attemptUrl }}" class="font-medium text-ink-800 hover:text-leaf-700 hover:underline dark:text-ink-100 dark:hover:text-leaf-300">{{ $attempt->quiz->title }}</a>
                    @else
                        <span class="font-medium text-ink-800 dark:text-ink-100">{{ $attempt->quiz->title }}</span>
                    @endif
                    · attempt {{ $attempt->attempt_number }}
                </span>
                <x-score-pill :value="(float) $attempt->percentage" size="sm" />
            </p>
        @endif

        <div class="mt-auto flex flex-wrap items-center gap-2 text-xs text-ink-500 dark:text-ink-400">
            @if ($feedback->is_visible_to_guardians)
                <x-badge color="sky" icon="eye" size="sm">Visible to parents</x-badge>
                @if ($feedback->read_at)
                    <x-badge color="leaf" icon="check-check" size="sm" :title="'Seen '.$feedback->read_at->format('M j, Y g:i A')">Seen {{ $feedback->read_at->diffForHumans() }}</x-badge>
                @else
                    <x-badge color="slate" icon="clock" size="sm">Not seen yet</x-badge>
                @endif
            @else
                <x-badge color="slate" icon="eye-off" size="sm">Private note</x-badge>
            @endif
            <span class="ml-auto">Sent <x-monitoring.time :value="$feedback->created_at" /></span>
        </div>
    </div>

    @if ($actions)
        <x-slot:footer>
            <div class="flex items-center justify-end gap-2">
                <x-button variant="ghost" size="sm" icon="pencil" :href="route('teacher.feedback.edit', $feedback)">Edit</x-button>
                <x-confirm-dialog
                    :action="route('teacher.feedback.destroy', $feedback)"
                    :title="'Delete this feedback for '.$student->name.'?'"
                    message="The message is removed for you and for the learner’s parents and guardians. This cannot be undone."
                    button="Delete"
                    button-variant="ghost"
                    confirm="Delete feedback"
                />
            </div>
        </x-slot:footer>
    @endif
</x-card>
