@props([
    'suggestions' => [],
    'form' => 'feedback-form',
])

<x-section
    title="Suggested messages"
    description="Based on this learner’s results in your subjects. Use one as a starting point, then make it personal."
    icon="sparkles"
    color="grape"
    {{ $attributes }}
    data-monitoring-suggestions
>
    @if (count($suggestions) === 0)
        <x-empty-state compact icon="lightbulb" title="No suggestions right now" description="Suggestions appear when the learner’s scores call for praise, encouragement, guidance or a concern." />
    @else
        <ul class="flex flex-col gap-4">
            @foreach ($suggestions as $suggestion)
                <li class="rounded-2xl bg-ink-50/70 p-4 ring-1 ring-ink-900/5 ring-inset dark:bg-white/3 dark:ring-white/8" data-suggestion-type="{{ $suggestion->type->value }}">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <x-status-badge :status="$suggestion->type" size="sm" />
                        <p class="text-xs text-ink-500 dark:text-ink-400">{{ $suggestion->reason }}</p>
                    </div>
                    <p class="mt-2 text-sm font-semibold text-ink-900 dark:text-white">{{ $suggestion->title }}</p>
                    <p class="mt-1 text-sm leading-relaxed text-ink-700 dark:text-ink-200">{{ $suggestion->message }}</p>
                    <x-button
                        variant="soft"
                        size="sm"
                        icon="pencil-line"
                        class="mt-3"
                        data-js-only
                        data-monitoring-suggestion
                        :data-monitoring-suggestion-form="$form"
                        :data-monitoring-suggestion-type="$suggestion->type->value"
                        :data-monitoring-suggestion-message="$suggestion->message"
                    >Use this message</x-button>
                </li>
            @endforeach
        </ul>
    @endif
</x-section>
