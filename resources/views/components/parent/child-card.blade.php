@props([
    'student',
    'relationship' => null,
    'classrooms' => [],
    'stats' => null,
    'feedback' => null,
    'href' => null,
    'showClassrooms' => false,
    'headingLevel' => 'h3',
])

@php
    $profile = $student->studentProfile;
    $grade = $profile?->grade_level;
    $classrooms = collect($classrooms);
    $sections = $classrooms->pluck('section')->filter()->unique()->values();
    $headingTag = in_array($headingLevel, ['h2', 'h3'], true) ? $headingLevel : 'h3';
    $averageScore = $stats['averageScore'] ?? null;
    $band = \App\Enums\PerformanceBand::fromScore($averageScore);
    $lastActiveAt = $stats['lastActiveAt'] ?? null;
@endphp

<x-card padding="none" {{ $attributes->class('flex h-full flex-col') }} data-child-card="{{ $student->getKey() }}">
    <div class="flex flex-1 flex-col gap-5 p-5 sm:p-6">
        <div class="flex min-w-0 items-start gap-4">
            <x-avatar :name="$student->name" size="lg" />
            <div class="min-w-0 flex-1">
                <{{ $headingTag }} class="font-display text-lg font-bold tracking-tight break-words text-ink-950 dark:text-white">
                    @if ($href)
                        <a href="{{ $href }}" class="rounded hover:text-carrot-700 hover:underline dark:hover:text-carrot-300">{{ $student->name }}</a>
                    @else
                        {{ $student->name }}
                    @endif
                </{{ $headingTag }}>
                <p class="mt-0.5 text-sm text-ink-500 dark:text-ink-400">
                    {{ $grade?->label() ?? 'Grade not set' }}
                    ·
                    @if ($sections->isNotEmpty())
                        {{ $sections->count() === 1 ? 'Section '.$sections->first() : 'Sections '.$sections->join(', ') }}
                    @else
                        Not enrolled in a class yet
                    @endif
                </p>
                <div class="mt-2 flex flex-wrap items-center gap-2">
                    @if ($relationship instanceof \App\Enums\GuardianRelationship)
                        <x-badge color="carrot" icon="house-heart" size="sm"><span class="sr-only">Your relationship:</span> {{ $relationship->label() }}</x-badge>
                    @endif
                    @unless ($student->is_active)
                        <x-badge color="slate" icon="ban" size="sm">Game account inactive</x-badge>
                    @endunless
                </div>
            </div>
        </div>

        @if ($showClassrooms)
            <div class="flex flex-wrap gap-2" data-child-classrooms>
                @forelse ($classrooms as $classroom)
                    <x-subject-chip
                        :name="$classroom->name"
                        :color="$classroom->subject->color"
                        :icon="$classroom->subject->icon"
                        :title="$classroom->subject->name.' · '.$classroom->name"
                        size="sm"
                    />
                @empty
                    <span class="text-sm text-ink-500 dark:text-ink-400">No classes yet</span>
                @endforelse
            </div>
        @endif

        @if (is_array($stats))
            <div class="flex items-center gap-3">
                <x-level-badge :level="$stats['levelProgress']['level']" size="md" />
                <div class="min-w-0 flex-1">
                    <p class="text-sm text-ink-600 dark:text-ink-300">
                        <span class="font-display text-base font-bold text-ink-950 tabular-nums dark:text-white">{{ number_format($stats['totalPoints']) }}</span>
                        XP earned
                    </p>
                    <x-xp-bar :progress="$stats['levelProgress']" :max-level="$stats['maxLevel']" size="sm" :show-labels="false" class="mt-1.5" />
                </div>
            </div>

            <div class="grid grid-cols-3 gap-3 rounded-xl bg-ink-50/70 p-3 ring-1 ring-inset ring-ink-900/5 dark:bg-white/3 dark:ring-white/8">
                <div class="flex flex-col items-center gap-1 text-center">
                    <x-progress-ring :value="$averageScore ?? 0" size="sm" :color="$band->color()" label="Average score">
                        <span class="font-display text-[11px] font-bold text-ink-950 tabular-nums dark:text-white">
                            {{ $averageScore === null ? '—' : rtrim(rtrim(number_format($averageScore, 1), '0'), '.').'%' }}
                        </span>
                    </x-progress-ring>
                    <span class="text-[11px] leading-tight text-ink-500 dark:text-ink-400">
                        Average
                        <span class="block font-medium text-ink-700 dark:text-ink-200">{{ $averageScore === null ? 'No quizzes yet' : $band->shortLabel() }}</span>
                    </span>
                </div>
                <div class="flex flex-col items-center justify-center gap-1 text-center">
                    <span class="font-display text-2xl font-bold text-ink-950 tabular-nums dark:text-white">{{ number_format($stats['quizzesTaken']) }}</span>
                    <span class="text-[11px] leading-tight text-ink-500 dark:text-ink-400">{{ $stats['quizzesTaken'] === 1 ? 'Quiz taken' : 'Quizzes taken' }}</span>
                </div>
                <div class="flex flex-col items-center justify-center gap-1 text-center">
                    <span class="font-display text-2xl font-bold text-ink-950 tabular-nums dark:text-white">{{ number_format($stats['missionsCompleted']) }}</span>
                    <span class="text-[11px] leading-tight text-ink-500 dark:text-ink-400">{{ $stats['missionsCompleted'] === 1 ? 'Mission done' : 'Missions done' }}</span>
                </div>
            </div>

            <p class="flex items-center gap-1.5 text-sm text-ink-600 dark:text-ink-300">
                <x-icon name="gamepad-2" class="size-4 text-ink-400 dark:text-ink-500" />
                @if ($lastActiveAt)
                    Last played
                    <time datetime="{{ $lastActiveAt->toIso8601String() }}" title="{{ $lastActiveAt->format('M j, Y g:i A') }}" class="font-medium text-ink-900 dark:text-ink-100">{{ $lastActiveAt->diffForHumans() }}</time>
                @else
                    <span>Has not played NutriMind yet</span>
                @endif
            </p>
        @endif

        @if ($feedback)
            <div class="rounded-xl bg-grape-50/60 p-3 ring-1 ring-inset ring-grape-600/10 dark:bg-grape-400/10 dark:ring-grape-400/15" data-child-feedback="{{ $feedback->getKey() }}">
                <div class="flex flex-wrap items-center gap-2">
                    <x-status-badge :status="$feedback->type" size="sm" />
                    @if ($feedback->read_at === null)
                        <x-badge color="sun" size="sm" dot>New</x-badge>
                    @endif
                    <span class="text-xs text-ink-500 dark:text-ink-400">
                        {{ $feedback->teacher->name }} ·
                        <time datetime="{{ $feedback->created_at->toIso8601String() }}" title="{{ $feedback->created_at->format('M j, Y g:i A') }}">{{ $feedback->created_at->diffForHumans() }}</time>
                    </span>
                </div>
                <p class="mt-1.5 line-clamp-2 text-sm text-ink-700 dark:text-ink-200">{{ \Illuminate\Support\Str::limit($feedback->message, 160) }}</p>
            </div>
        @endif
    </div>

    @if ($href)
        <x-slot:footer>
            <div class="flex items-center justify-end">
                <x-button variant="ghost" size="sm" icon-trailing="arrow-right" :href="$href">
                    View progress<span class="sr-only"> of {{ $student->name }}</span>
                </x-button>
            </div>
        </x-slot:footer>
    @endif
</x-card>
