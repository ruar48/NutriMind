@props([
    'summary',
])

@php
    $student = $summary->student;
    $profile = $student->studentProfile;
    $grade = $profile?->grade_level;
    $syncOverdue = $summary->isSyncOverdue();
@endphp

<x-card {{ $attributes }}>
    <div class="flex flex-col gap-6 lg:flex-row lg:items-center lg:justify-between">
        <div class="flex min-w-0 items-start gap-4">
            <x-avatar :name="$student->name" size="xl" />

            <div class="min-w-0">
                <h2 class="font-display text-xl font-bold tracking-tight break-words text-ink-950 sm:text-2xl dark:text-white">{{ $student->name }}</h2>

                <p class="mt-1 flex flex-wrap items-center gap-x-4 gap-y-1 text-sm text-ink-500 dark:text-ink-400">
                    <span class="inline-flex items-center gap-1.5">
                        <x-icon name="id-card" class="size-4" />
                        @if ($profile?->lrn)
                            <span>LRN <span class="tabular-nums">{{ $profile->lrn }}</span></span>
                        @else
                            <span>No LRN on file</span>
                        @endif
                    </span>
                    @if (filled($student->username))
                        <span class="inline-flex min-w-0 items-center gap-1.5">
                            <x-icon name="user-round" class="size-4" />
                            <span class="truncate">Username {{ $student->username }}</span>
                        </span>
                    @endif
                </p>

                <div class="mt-3 flex flex-wrap items-center gap-2">
                    @if ($grade)
                        <x-badge :color="$grade->color()" :icon="$grade->icon()">{{ $grade->label() }}</x-badge>
                    @endif
                    @forelse ($summary->classrooms as $classroom)
                        <x-subject-chip
                            :name="$classroom->name"
                            :color="$classroom->subject->color"
                            :icon="$classroom->subject->icon"
                            :title="$classroom->subject->name.' · '.$classroom->name"
                        />
                    @empty
                        <span class="text-sm text-ink-500 dark:text-ink-400">Not enrolled in a classroom</span>
                    @endforelse
                </div>
            </div>
        </div>

        <div class="flex w-full min-w-0 items-center gap-4 lg:w-80 lg:shrink-0">
            <x-level-badge :level="$summary->level" size="lg" />
            <div class="min-w-0 flex-1">
                <p class="text-sm text-ink-600 dark:text-ink-300">
                    <span class="font-display text-lg font-bold text-ink-950 tabular-nums dark:text-white">{{ number_format($summary->totalPoints) }}</span>
                    XP earned
                </p>
                <x-xp-bar :progress="$summary->levelProgress" :max-level="$summary->maxLevel" class="mt-2" />
            </div>
        </div>
    </div>

    <x-slot:footer>
        <div class="flex flex-col gap-3">
            <dl class="flex flex-wrap items-center gap-x-8 gap-y-2 text-sm">
                <div class="flex items-center gap-2">
                    <dt class="flex items-center gap-1.5 text-ink-500 dark:text-ink-400">
                        <x-icon name="gamepad-2" class="size-4" />
                        Last active
                    </dt>
                    <dd class="font-medium text-ink-900 dark:text-ink-100">
                        @if ($summary->lastActiveAt)
                            <time datetime="{{ $summary->lastActiveAt->toIso8601String() }}" title="{{ $summary->lastActiveAt->format('M j, Y g:i A') }}">{{ $summary->lastActiveAt->diffForHumans() }}</time>
                        @else
                            <span class="font-normal text-ink-500 dark:text-ink-400">Not played yet</span>
                        @endif
                    </dd>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <dt class="flex items-center gap-1.5 text-ink-500 dark:text-ink-400">
                        <x-icon name="cloud-check" class="size-4" />
                        Last synced
                    </dt>
                    <dd class="flex flex-wrap items-center gap-2 font-medium text-ink-900 dark:text-ink-100">
                        @if ($summary->lastSyncedAt)
                            <time datetime="{{ $summary->lastSyncedAt->toIso8601String() }}" title="{{ $summary->lastSyncedAt->format('M j, Y g:i A') }}">{{ $summary->lastSyncedAt->diffForHumans() }}</time>
                        @else
                            <span class="font-normal text-ink-500 dark:text-ink-400">Never synced</span>
                        @endif
                        @if ($syncOverdue)
                            <x-badge color="sun" icon="wifi-off" size="sm">Sync overdue</x-badge>
                        @endif
                    </dd>
                </div>
            </dl>

            @if ($syncOverdue)
                <x-alert variant="warning" title="Game progress may be out of date">
                    @if ($summary->lastSyncedAt)
                        The game last sent progress {{ $summary->lastSyncedAt->diffForHumans() }}. Anything played offline since then appears here once the NutriMind game reconnects to the internet.
                    @else
                        The game has not sent this learner’s progress yet. Anything played offline appears here once the NutriMind game reconnects to the internet.
                    @endif
                </x-alert>
            @endif
        </div>
    </x-slot:footer>
</x-card>
