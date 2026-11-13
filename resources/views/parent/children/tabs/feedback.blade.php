@php
    $resetUrl = route('parent.children.show', [$student, 'tab' => 'feedback']);
@endphp

<div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
    <div class="min-w-0">
        <h2 class="font-display text-lg font-semibold tracking-tight text-ink-950 dark:text-white">Messages from teachers</h2>
        <p class="text-sm text-ink-600 dark:text-ink-300">
            Notes {{ $firstName }}’s teachers shared with you. New messages are marked as read when you open this page.
        </p>
    </div>

    <x-filter-bar
        :action="route('parent.children.show', $student)"
        :search="false"
        :reset-url="$resetUrl"
        :preserve="['tab']"
        class="sm:shrink-0"
    >
        <x-form.select
            name="type"
            label="Type of message"
            hide-label
            placeholder="All messages"
            :options="\App\Enums\FeedbackType::options()"
            :value="$filters['type']"
            :old="false"
            class="sm:w-48"
        />
    </x-filter-bar>
</div>

@if ($feedback->isEmpty())
    @if ($isFiltered)
        <x-empty-state variant="card" illustration="search" title="No messages of this type" description="Choose another type of message, or show them all.">
            <x-slot:actions>
                <x-button variant="secondary" icon="rotate-ccw" :href="$resetUrl">Show all messages</x-button>
            </x-slot:actions>
        </x-empty-state>
    @else
        <x-empty-state
            variant="card"
            icon="message-circle"
            title="No messages yet"
            :description="'When '.$firstName.'’s teachers share praise, encouragement or advice with you, it appears here.'"
        />
    @endif
@else
    <ul class="flex flex-col gap-4" data-child-feedback-list>
        @foreach ($feedback as $message)
            <li>
                <x-parent.feedback-card
                    :feedback="$message"
                    :is-new="in_array($message->getKey(), $newFeedbackIds, true)"
                    :attempt-url="$feedbackAttemptUrl($message)"
                    data-animate
                    :style="'--stagger: '.min($loop->iteration, 6)"
                />
            </li>
        @endforeach
    </ul>

    @if ($feedback->hasPages())
        {{ $feedback->links() }}
    @endif
@endif
