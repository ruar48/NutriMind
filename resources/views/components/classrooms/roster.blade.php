@props([
    'classroom',
    'students',
    'levels',
    'canManageStudents' => false,
    'canImport' => false,
])

@php
    $isSearching = filled(request('search'));
@endphp

<div {{ $attributes->class('flex flex-col gap-4') }} data-animate>
    <x-filter-bar
        :action="route('teacher.classrooms.show', $classroom)"
        search-label="Search learners in this class"
        search-placeholder="Search name, username or LRN"
        :reset-url="route('teacher.classrooms.show', [$classroom, 'tab' => 'learners'])"
        :preserve="['tab']"
    />

    <x-section
        title="Learners"
        :description="trans_choice(':count learner enrolled|:count learners enrolled', $classroom->students_count)"
        icon="users"
        padding="none"
    >
        @if ($students->isEmpty())
            @if ($isSearching)
                <x-empty-state illustration="search" title="No learners match your search" description="Try another name, username or LRN.">
                    <x-slot:actions>
                        <x-button variant="secondary" icon="rotate-ccw" :href="route('teacher.classrooms.show', [$classroom, 'tab' => 'learners'])">Clear search</x-button>
                    </x-slot:actions>
                </x-empty-state>
            @else
                <x-empty-state
                    illustration="classroom"
                    title="No learners yet"
                    :description="$canManageStudents || $canImport
                        ? 'Add learners one by one, enroll learners who already have an account, or import a CSV class list.'
                        : 'Learners enrolled in this class appear here.'"
                >
                    @if ($canManageStudents || $canImport)
                        <x-slot:actions>
                            @if ($canManageStudents)
                                <x-button icon="user-plus" :href="route('teacher.classrooms.students.create', $classroom)">Add learner</x-button>
                            @endif
                            @if ($canImport)
                                <x-button variant="secondary" icon="file-up" :href="route('teacher.classrooms.import.create', $classroom)">Import CSV</x-button>
                            @endif
                        </x-slot:actions>
                    @endif
                </x-empty-state>
            @endif
        @else
            <x-table flush :caption="'Learners enrolled in '.$classroom->name">
                <x-slot:head>
                    <x-table.th>Learner</x-table.th>
                    <x-table.th>LRN</x-table.th>
                    <x-table.th>Level</x-table.th>
                    <x-table.th align="right">Average here</x-table.th>
                    <x-table.th align="right">Quizzes done</x-table.th>
                    <x-table.th>Last active</x-table.th>
                    <x-table.th align="right"><span class="sr-only">Actions</span></x-table.th>
                </x-slot:head>

                @foreach ($students as $student)
                    @php
                        $profile = $student->studentProfile;
                        $points = $profile?->total_points ?? 0;
                        $average = $student->classroom_average === null ? null : round((float) $student->classroom_average, 1);
                    @endphp
                    <x-table.row data-roster-row="{{ $student->id }}">
                        <x-table.td primary>
                            <div class="flex items-center gap-3">
                                <x-avatar :name="$student->name" size="sm" />
                                <div class="min-w-0">
                                    <a href="{{ route('teacher.students.show', $student) }}" class="block truncate hover:text-leaf-700 hover:underline dark:hover:text-leaf-300">{{ $student->name }}</a>
                                    <p class="truncate font-mono text-xs font-normal text-ink-500 dark:text-ink-400">{{ $student->username }}</p>
                                </div>
                                @unless ($student->is_active)
                                    <x-badge color="slate" size="sm" icon="ban">Inactive</x-badge>
                                @endunless
                            </div>
                        </x-table.td>
                        <x-table.td numeric class="whitespace-nowrap">
                            @if ($profile?->lrn)
                                {{ $profile->lrn }}
                            @else
                                <span class="text-ink-400 dark:text-ink-500">—</span>
                            @endif
                        </x-table.td>
                        <x-table.td class="whitespace-nowrap">
                            <div class="flex items-center gap-2">
                                <x-level-badge :level="$levels->levelFor($points)" size="sm" />
                                <span class="text-xs text-ink-600 tabular-nums dark:text-ink-300">{{ number_format($points) }} XP</span>
                            </div>
                        </x-table.td>
                        <x-table.td align="right" data-roster-average>
                            <x-score-pill :value="$average" />
                        </x-table.td>
                        <x-table.td align="right" numeric data-roster-quizzes>{{ (int) $student->quizzes_completed }}</x-table.td>
                        <x-table.td class="whitespace-nowrap">
                            @if ($profile?->last_active_at)
                                <time datetime="{{ $profile->last_active_at->toIso8601String() }}" title="{{ $profile->last_active_at->format('M j, Y g:i A') }}">{{ $profile->last_active_at->diffForHumans() }}</time>
                            @else
                                <span class="text-ink-400 dark:text-ink-500">Not played yet</span>
                            @endif
                        </x-table.td>
                        <x-table.td align="right">
                            <x-dropdown variant="icon" icon="ellipsis-vertical" :label="'Actions for '.$student->name">
                                <x-dropdown.item :href="route('teacher.students.show', $student)" icon="chart-line">View performance</x-dropdown.item>
                                @if ($canManageStudents)
                                    <x-confirm-dialog
                                        :action="route('teacher.classrooms.students.password.update', [$classroom, $student])"
                                        method="PUT"
                                        :danger="false"
                                        icon="key-round"
                                        :title="'Reset '.$student->name.'’s password?'"
                                        message="A new password is generated and shown to you once. Their current password stops working right away."
                                        confirm="Reset password"
                                        button="Reset password"
                                        button-variant="menu"
                                        button-icon="key-round"
                                    />
                                    <x-dropdown.divider />
                                    <x-confirm-dialog
                                        :action="route('teacher.classrooms.students.destroy', [$classroom, $student])"
                                        :title="'Remove '.$student->name.' from this class?'"
                                        message="Their account, quiz attempts and game progress stay. You can enroll them again later."
                                        confirm="Remove learner"
                                        button="Remove from class"
                                        button-variant="menu"
                                        button-icon="user-x"
                                    />
                                @endif
                            </x-dropdown>
                        </x-table.td>
                    </x-table.row>
                @endforeach
            </x-table>

            @if ($students->hasPages())
                <div class="border-t border-ink-900/5 px-5 py-3 dark:border-white/8">{{ $students->links() }}</div>
            @endif
        @endif
    </x-section>
</div>
