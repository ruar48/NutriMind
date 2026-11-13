@props([
    'classroom',
])

@php
    $bands = [
        'leaf' => 'bg-leaf-500',
        'carrot' => 'bg-carrot-500',
        'sun' => 'bg-sun-400',
        'berry' => 'bg-berry-500',
        'grape' => 'bg-grape-500',
        'sky' => 'bg-sky-500',
        'teal' => 'bg-teal-500',
        'lime' => 'bg-lime-500',
        'pink' => 'bg-pink-500',
        'indigo' => 'bg-indigo-500',
        'slate' => 'bg-slate-400',
    ];

    $band = $bands[$classroom->subject->color] ?? $bands['leaf'];
    $isArchived = $classroom->isArchived();
    $canUpdate = auth()->user()?->can('update', $classroom) ?? false;
    $averageScore = $classroom->average_score === null ? null : round((float) $classroom->average_score, 1);
    $details = collect([
        $classroom->grade_level->label(),
        'Section '.$classroom->section,
        $classroom->room,
    ])->filter()->implode(' · ');
@endphp

<x-card padding="none" {{ $attributes }} data-classroom-card="{{ $classroom->id }}">
    <span aria-hidden="true" @class(['absolute inset-x-0 top-0 h-1.5 rounded-t-2xl', $band, 'opacity-50' => $isArchived])></span>

    <div class="flex flex-col gap-4 px-5 pt-6 pb-5">
        <div class="flex items-start gap-3">
            <x-classrooms.subject-tile :subject="$classroom->subject" @class(['grayscale' => $isArchived]) />
            <div class="min-w-0 flex-1">
                <h3 class="font-display text-base leading-snug font-semibold tracking-tight text-ink-950 dark:text-white">
                    <a href="{{ route('teacher.classrooms.show', $classroom) }}" class="rounded-md hover:text-leaf-700 hover:underline focus-visible:outline-2 focus-visible:outline-offset-2 dark:hover:text-leaf-300">{{ $classroom->name }}</a>
                </h3>
                <p class="mt-0.5 truncate text-sm text-ink-500 dark:text-ink-400">{{ $details }}</p>
            </div>
            <x-dropdown variant="icon" icon="ellipsis-vertical" :label="'Actions for '.$classroom->name" class="-mt-1.5 -mr-2">
                <x-dropdown.item :href="route('teacher.classrooms.show', $classroom)" icon="eye">Open classroom</x-dropdown.item>
                @if ($canUpdate)
                    <x-dropdown.item :href="route('teacher.classrooms.edit', $classroom)" icon="pencil">Edit details</x-dropdown.item>
                    <x-dropdown.divider />
                    @if ($isArchived)
                        <x-confirm-dialog
                            :action="route('teacher.classrooms.archive.destroy', $classroom)"
                            :danger="false"
                            :title="'Restore '.$classroom->name.'?'"
                            message="The classroom moves back to your active classes and you can manage its learners again."
                            confirm="Restore classroom"
                            button="Restore"
                            button-variant="menu"
                            button-icon="archive-restore"
                        />
                    @else
                        <x-confirm-dialog
                            :action="route('teacher.classrooms.archive.store', $classroom)"
                            method="POST"
                            :title="'Archive '.$classroom->name.'?'"
                            message="Learners and quiz results stay available, but the class becomes read-only until you restore it."
                            confirm="Archive classroom"
                            button="Archive"
                            button-variant="menu"
                            button-icon="archive"
                        />
                    @endif
                @endif
            </x-dropdown>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            <x-subject-chip :subject="$classroom->subject" size="sm" />
            <x-status-badge :status="$classroom->learning_mode" size="sm" />
            @if ($isArchived)
                <x-badge color="slate" icon="archive" size="sm">Archived</x-badge>
            @endif
            @unless ($classroom->schoolYear->is_active)
                <x-badge color="slate" icon="calendar" size="sm">SY {{ $classroom->schoolYear->name }}</x-badge>
            @endunless
        </div>

        <dl class="grid grid-cols-3 gap-2 rounded-xl bg-ink-50 p-3 ring-1 ring-ink-900/5 ring-inset dark:bg-white/4 dark:ring-white/8">
            <div class="min-w-0">
                <dt class="truncate text-xs text-ink-600 dark:text-ink-300">Learners</dt>
                <dd class="mt-0.5 font-display text-lg font-bold text-ink-950 tabular-nums dark:text-white" data-classroom-stat="learners">{{ number_format($classroom->students_count) }}</dd>
            </div>
            <div class="min-w-0">
                <dt class="truncate text-xs text-ink-600 dark:text-ink-300">Published quizzes</dt>
                <dd class="mt-0.5 font-display text-lg font-bold text-ink-950 tabular-nums dark:text-white" data-classroom-stat="published-quizzes">{{ number_format($classroom->published_quizzes_count) }}</dd>
            </div>
            <div class="min-w-0">
                <dt class="truncate text-xs text-ink-600 dark:text-ink-300">Average</dt>
                <dd class="mt-1" data-classroom-stat="average">
                    <x-score-pill :value="$averageScore" />
                </dd>
            </div>
        </dl>
    </div>

    <x-slot:footer>
        <div class="flex items-center justify-between gap-3">
            <x-classrooms.class-code :code="$classroom->class_code" />
            <x-button variant="ghost" size="sm" icon-trailing="arrow-right" :href="route('teacher.classrooms.show', $classroom)" :aria-label="'Open '.$classroom->name">Open</x-button>
        </div>
    </x-slot:footer>
</x-card>
