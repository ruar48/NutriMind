{{--
    The status changes a quiz allows, as buttons (menu = false) or dropdown items (menu = true).
    only = 'primary' renders publish / close / reopen; 'secondary' renders "move back to draft"; null renders both.
    ready: the publishing checklist is complete; hasAttempts: learners attempted the quiz (no way back to draft).
--}}
@props([
    'quiz',
    'ready' => false,
    'hasAttempts' => false,
    'menu' => false,
    'only' => null,
])

@php
    $status = $quiz->status;
    $title = '“'.$quiz->title.'”';
    $variant = $menu ? 'menu' : null;
    $showPrimary = $only === null || $only === 'primary';
    $showSecondary = ($only === null || $only === 'secondary') && ! $quiz->isDraft() && ! $hasAttempts;
@endphp

@if ($showPrimary)
    @if ($quiz->isDraft())
        @if ($ready)
            <x-confirm-dialog
                :action="route('teacher.quizzes.status.update', $quiz)"
                method="PUT"
                :danger="false"
                icon="send"
                :title="'Publish '.$title.'?'"
                message="Learners in the assigned classrooms can take it right away. Its questions stay locked while it is published."
                confirm="Publish quiz"
                button="Publish"
                :button-variant="$variant ?? 'primary'"
                button-size="md"
                button-icon="send"
            >
                <x-slot:fields><input type="hidden" name="status" value="{{ \App\Enums\QuizStatus::Published->value }}"></x-slot:fields>
            </x-confirm-dialog>
        @elseif ($menu)
            <x-dropdown.item :href="route('teacher.quizzes.show', $quiz)" icon="list-checks">Finish setting up to publish</x-dropdown.item>
        @else
            <x-button icon="send" disabled title="Complete the publishing checklist first">Publish</x-button>
        @endif
    @elseif ($quiz->isPublished())
        <x-confirm-dialog
            :action="route('teacher.quizzes.status.update', $quiz)"
            method="PUT"
            :danger="false"
            icon="lock"
            :title="'Close '.$title.'?'"
            message="Learners can no longer start it. Results stay available, and you can reopen it later."
            confirm="Close quiz"
            button="Close quiz"
            :button-variant="$variant ?? 'secondary'"
            button-size="md"
            button-icon="lock"
        >
            <x-slot:fields><input type="hidden" name="status" value="{{ \App\Enums\QuizStatus::Closed->value }}"></x-slot:fields>
        </x-confirm-dialog>
    @else
        <x-confirm-dialog
            :action="route('teacher.quizzes.status.update', $quiz)"
            method="PUT"
            :danger="false"
            icon="lock-open"
            :title="'Reopen '.$title.'?'"
            message="Learners in the assigned classrooms can take it again."
            confirm="Reopen quiz"
            button="Reopen"
            :button-variant="$variant ?? 'primary'"
            button-size="md"
            button-icon="lock-open"
        >
            <x-slot:fields><input type="hidden" name="status" value="{{ \App\Enums\QuizStatus::Published->value }}"></x-slot:fields>
        </x-confirm-dialog>
    @endif
@endif

@if ($showSecondary)
    <x-confirm-dialog
        :action="route('teacher.quizzes.status.update', $quiz)"
        method="PUT"
        :danger="false"
        icon="square-pen"
        :title="'Move '.$title.' back to draft?'"
        message="Learners stop seeing it and you can edit its questions again. A changed quiz needs a new review before it is published."
        confirm="Move to draft"
        button="Move back to draft"
        :button-variant="$variant ?? 'secondary'"
        button-size="md"
        button-icon="square-pen"
    >
        <x-slot:fields><input type="hidden" name="status" value="{{ \App\Enums\QuizStatus::Draft->value }}"></x-slot:fields>
    </x-confirm-dialog>
@endif
