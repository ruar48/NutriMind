@props([
    'feedback',
    'isNew' => false,
    'compact' => false,
    'showStudent' => false,
    'href' => null,
    'attemptUrl' => null,
])

@php
    $teacherName = $feedback->teacher->name;
    $sentAt = $feedback->created_at;
@endphp

@if ($compact)
    <div {{ $attributes->class('flex min-w-0 gap-3') }} data-feedback="{{ $feedback->getKey() }}" @if ($isNew) data-feedback-new @endif>
        <x-avatar :name="$teacherName" size="md" />
        <div class="min-w-0 flex-1">
            <div class="flex flex-wrap items-center gap-x-2 gap-y-1">
                <p class="text-sm font-semibold text-ink-900 dark:text-ink-100">{{ $teacherName }}</p>
                <x-status-badge :status="$feedback->type" size="sm" />
                @if ($isNew)
                    <x-badge color="sun" size="sm" dot>New</x-badge>
                @endif
            </div>
            <p class="mt-1 line-clamp-2 text-sm text-ink-600 dark:text-ink-300">
                @if ($href)
                    <a href="{{ $href }}" class="rounded hover:text-carrot-700 hover:underline dark:hover:text-carrot-300">{{ \Illuminate\Support\Str::limit($feedback->message, 180) }}</a>
                @else
                    {{ \Illuminate\Support\Str::limit($feedback->message, 180) }}
                @endif
            </p>
            <p class="mt-1 text-xs text-ink-500 dark:text-ink-400">
                @if ($showStudent)
                    About {{ $feedback->student->name }} ·
                @endif
                <time datetime="{{ $sentAt->toIso8601String() }}" title="{{ $sentAt->format('M j, Y g:i A') }}">{{ $sentAt->diffForHumans() }}</time>
            </p>
        </div>
    </div>
@else
    @php
        $attempt = $feedback->quizAttempt;
    @endphp

    <x-card {{ $attributes }} data-feedback="{{ $feedback->getKey() }}" :data-feedback-new="$isNew ? 'true' : null">
        @if ($isNew)
            <span aria-hidden="true" class="absolute inset-y-5 left-0 w-1 rounded-r-full bg-sun-400 dark:bg-sun-300"></span>
        @endif

        <div class="flex flex-col gap-4">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div class="flex min-w-0 items-center gap-3">
                    <x-avatar :name="$teacherName" size="md" />
                    <div class="min-w-0">
                        <p class="truncate text-sm font-semibold text-ink-900 dark:text-ink-100">{{ $teacherName }}</p>
                        <p class="text-xs text-ink-500 dark:text-ink-400">
                            Teacher ·
                            <time datetime="{{ $sentAt->toIso8601String() }}" title="{{ $sentAt->diffForHumans() }}">{{ $sentAt->format('M j, Y g:i A') }}</time>
                        </p>
                    </div>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    @if ($isNew)
                        <x-badge color="sun" dot>New</x-badge>
                    @endif
                    <x-status-badge :status="$feedback->type" />
                </div>
            </div>

            <p class="text-sm leading-relaxed whitespace-pre-line text-ink-800 dark:text-ink-100">{{ $feedback->message }}</p>

            @if ($feedback->classroom || $attempt)
                <div class="flex flex-wrap items-center gap-2 border-t border-ink-900/5 pt-3 dark:border-white/8">
                    @if ($feedback->classroom)
                        <x-subject-chip
                            :name="$feedback->classroom->name"
                            :color="$feedback->classroom->subject->color"
                            :icon="$feedback->classroom->subject->icon"
                            :title="$feedback->classroom->subject->name.' · '.$feedback->classroom->name"
                            size="sm"
                        />
                    @endif
                    @if ($attempt)
                        <span class="inline-flex min-w-0 items-center gap-1.5 text-xs text-ink-600 dark:text-ink-300">
                            <x-icon name="clipboard-check" class="size-3.5 text-ink-400 dark:text-ink-500" />
                            About
                            @if ($attemptUrl)
                                <a href="{{ $attemptUrl }}" class="font-semibold text-ink-900 hover:text-carrot-700 hover:underline dark:text-ink-100 dark:hover:text-carrot-300">{{ $attempt->quiz->title }}</a>
                            @else
                                <span class="font-semibold text-ink-900 dark:text-ink-100">{{ $attempt->quiz->title }}</span>
                            @endif
                            @if ($attempt->submitted_at)
                                <x-score-pill :value="(float) $attempt->percentage" size="sm" />
                            @endif
                        </span>
                    @endif
                </div>
            @endif
        </div>
    </x-card>
@endif
