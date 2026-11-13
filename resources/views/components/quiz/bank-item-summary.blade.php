{{--
    Summary of a question bank item for lists: badges, prompt excerpt, media thumbnail, answer and usage.
    Eager-load subject, teacher and module. showSubject hides the subject chip where every item shares one subject.
--}}
@props([
    'item',
    'showAuthor' => false,
    'showSubject' => true,
])

<div {{ $attributes->class('flex min-w-0 flex-col gap-3') }}>
    <div class="flex flex-wrap items-center gap-1.5">
        <x-status-badge :status="$item->type" size="sm" />
        <x-status-badge :status="$item->difficulty" size="sm" />
        @if ($item->is_shared)
            <x-badge color="sky" icon="users" size="sm">Shared</x-badge>
        @endif
        @if ($item->reviewed_at !== null)
            <x-badge color="leaf" icon="badge-check" size="sm">Reviewed</x-badge>
        @endif
    </div>

    <div class="flex min-w-0 items-start gap-3">
        <p class="line-clamp-3 min-w-0 flex-1 text-sm font-medium wrap-break-word text-ink-900 dark:text-ink-50" title="{{ \Illuminate\Support\Str::squish($item->prompt) }}">{{ $item->prompt }}</p>
        <x-question.media :question="$item" compact />
    </div>

    <p class="truncate text-xs text-ink-500 dark:text-ink-400">
        <span class="font-medium text-ink-700 dark:text-ink-200">Answer:</span>
        {{ $item->correctAnswerText() !== '' ? $item->correctAnswerText() : 'Not set' }}
    </p>

    <div class="flex flex-wrap items-center gap-x-3 gap-y-1.5 text-xs text-ink-500 dark:text-ink-400">
        @if ($showSubject)
            <x-subject-chip :subject="$item->subject" size="sm" />
        @endif
        <span class="inline-flex items-center gap-1"><x-icon name="graduation-cap" class="size-3.5" />{{ $item->grade_level->label() }}</span>
        @if ($item->module)
            <span class="inline-flex min-w-0 items-center gap-1"><x-icon name="map" class="size-3.5 shrink-0" /><span class="truncate">{{ $item->module->title }}</span></span>
        @endif
        <span class="inline-flex items-center gap-1 tabular-nums"><x-icon name="repeat" class="size-3.5" />{{ trans_choice('Used :count time|Used :count times', $item->times_used) }}</span>
        @if ($showAuthor)
            <span class="inline-flex items-center gap-1"><x-icon name="user" class="size-3.5" />{{ $item->teacher?->name ?? 'A former teacher' }}</span>
        @endif
    </div>
</div>
