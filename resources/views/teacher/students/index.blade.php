@php
    $rows = $roster->rows;
    $isFiltered = request()->filled('search') || request()->filled('band') || $classroom !== null;
    $averageText = $roster->averageScore === null ? '—' : rtrim(rtrim(number_format($roster->averageScore, 1), '0'), '.').'%';
    $averageBand = \App\Enums\PerformanceBand::fromScore($roster->averageScore);
    $resetUrl = route('teacher.students.index');
@endphp

<x-layouts.app title="Learners">
    <x-page-header
        eyebrow="Monitoring"
        title="Learners"
        description="Learners in your active classrooms, with their quiz scores and game activity in your subjects."
        :breadcrumbs="[['label' => 'Dashboard', 'url' => route('teacher.dashboard')], ['label' => 'Learners']]"
    >
        <x-slot:actions>
            <x-button variant="secondary" icon="flag" :href="route('teacher.missions.index')">Mission progress</x-button>
            @if ($canGiveFeedback)
                <x-button icon="message-circle" :href="route('teacher.feedback.create')">Give feedback</x-button>
            @endif
        </x-slot:actions>
    </x-page-header>

    @if ($classrooms->isEmpty())
        <x-empty-state
            variant="card"
            illustration="classroom"
            title="No active classrooms yet"
            description="Your learners appear here once you have an active classroom with enrolled learners in one of your subjects."
        >
            <x-slot:actions>
                <x-button icon="school" :href="route('teacher.classrooms.index')">Go to classrooms</x-button>
            </x-slot:actions>
        </x-empty-state>
    @else
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <x-stat-card
                label="Learners"
                :value="number_format($roster->learnerCount)"
                icon="users"
                color="sky"
                :hint="$classroom ? $classroom->name : trans_choice('In :count active classroom|In :count active classrooms', $classrooms->count())"
                :count-up="$roster->learnerCount"
                data-animate
                style="--stagger: 1"
            />
            <x-stat-card
                label="Class average"
                :value="$averageText"
                icon="target"
                :color="$averageBand->color()"
                :hint="$roster->averageScore === null ? 'No quiz attempts yet' : $averageBand->label()"
                data-animate
                style="--stagger: 2"
            />
            <x-stat-card
                label="Needs attention"
                :value="number_format($roster->needsAttention)"
                icon="triangle-alert"
                color="berry"
                hint="Average below 75%"
                :href="route('teacher.students.index', array_filter(['classroom' => $classroom?->getKey(), 'band' => \App\Enums\PerformanceBand::DidNotMeetExpectations->value]))"
                data-animate
                style="--stagger: 3"
            />
            <x-stat-card
                label="Sync overdue"
                :value="number_format($roster->syncNeedsAttention)"
                icon="wifi-off"
                color="sun"
                hint="Never synced or not for 7+ days"
                :href="route('teacher.students.index', array_filter(['classroom' => $classroom?->getKey(), 'sort' => 'last_synced', 'direction' => 'asc']))"
                data-animate
                style="--stagger: 4"
            />
        </div>

        <x-section title="Performance bands" description="DepEd descriptors from each learner’s average of best quiz scores" icon="gauge" color="sky">
            <x-chart.stacked-bar
                label="Learners by performance band"
                label-heading="Band"
                value-heading="Learners"
                empty-text="Bands appear once your learners are enrolled."
                :segments="collect(\App\Enums\PerformanceBand::cases())
                    ->map(fn ($performanceBand) => ['label' => $performanceBand->label(), 'value' => $roster->bandCounts[$performanceBand->value] ?? 0, 'color' => $performanceBand->color()])
                    ->all()"
            />
        </x-section>

        <div class="flex flex-col gap-4">
            <x-filter-bar
                :action="route('teacher.students.index')"
                search-label="Search learners"
                search-placeholder="Search name, username or LRN"
                :reset-url="$resetUrl"
            >
                <x-form.select
                    name="classroom"
                    label="Classroom"
                    hide-label
                    placeholder="All my classrooms"
                    :options="$classrooms->mapWithKeys(fn ($option) => [$option->getKey() => $option->name])->all()"
                    :value="$classroom?->getKey()"
                    :old="false"
                    class="sm:w-56"
                />
                <x-form.select
                    name="band"
                    label="Performance band"
                    hide-label
                    placeholder="Any band"
                    :options="\App\Enums\PerformanceBand::options()"
                    :value="$band?->value"
                    :old="false"
                    class="sm:w-60"
                />
            </x-filter-bar>
        </div>

        @if ($rows->isEmpty())
            @if ($isFiltered)
                <x-empty-state variant="card" illustration="search" title="No learners match your filters" description="Try another name, classroom or band, or clear the filters.">
                    <x-slot:actions>
                        <x-button variant="secondary" icon="rotate-ccw" :href="$resetUrl">Clear filters</x-button>
                    </x-slot:actions>
                </x-empty-state>
            @else
                <x-empty-state variant="card" illustration="classroom" title="No learners yet" description="Add learners to your classrooms or import a class list to start monitoring their progress.">
                    <x-slot:actions>
                        <x-button icon="school" :href="route('teacher.classrooms.index')">Go to classrooms</x-button>
                    </x-slot:actions>
                </x-empty-state>
            @endif
        @else
            <x-table caption="Learners in your classrooms">
                <x-slot:head>
                    <x-table.th sort="name">Learner</x-table.th>
                    <x-table.th sort="points" default-direction="desc">Level</x-table.th>
                    <x-table.th align="right">Average</x-table.th>
                    <x-table.th>Band</x-table.th>
                    <x-table.th align="right">Quizzes</x-table.th>
                    <x-table.th align="right">Missions</x-table.th>
                    <x-table.th sort="last_active" default-direction="desc">Last active</x-table.th>
                    <x-table.th sort="last_synced" default-direction="desc">Last synced</x-table.th>
                    <x-table.th align="right"><span class="sr-only">Actions</span></x-table.th>
                </x-slot:head>

                @foreach ($rows as $row)
                    <x-table.row :highlight="$row->band->needsAttention()" data-learner-row="{{ $row->student->getKey() }}">
                        <x-table.td primary>
                            <div class="flex min-w-56 items-center gap-3">
                                <x-avatar :name="$row->student->name" size="sm" />
                                <div class="min-w-0">
                                    <a href="{{ route('teacher.students.show', $row->student) }}" class="block truncate rounded hover:text-leaf-700 hover:underline dark:hover:text-leaf-300">{{ $row->student->name }}</a>
                                    <div class="mt-1 flex flex-wrap gap-1">
                                        @foreach ($row->classrooms as $learnerClassroom)
                                            <x-subject-chip :name="$learnerClassroom->name" :color="$learnerClassroom->subject->color" :icon="$learnerClassroom->subject->icon" size="sm" />
                                        @endforeach
                                    </div>
                                </div>
                            </div>
                        </x-table.td>
                        <x-table.td>
                            <div class="flex items-center gap-2 whitespace-nowrap">
                                <x-level-badge :level="$row->level" size="sm" />
                                <span class="text-xs tabular-nums">{{ number_format($row->points) }} XP</span>
                            </div>
                        </x-table.td>
                        <x-table.td align="right"><x-score-pill :value="$row->averageScore" /></x-table.td>
                        <x-table.td><x-status-badge :status="$row->band" size="sm" /></x-table.td>
                        <x-table.td align="right" numeric>{{ $row->quizzesTaken }}</x-table.td>
                        <x-table.td align="right" numeric>{{ $row->missionsCompleted }}</x-table.td>
                        <x-table.td><x-monitoring.time :value="$row->lastActiveAt" empty="Not played yet" /></x-table.td>
                        <x-table.td><x-monitoring.sync-status :synced-at="$row->lastSyncedAt" /></x-table.td>
                        <x-table.td align="right">
                            <x-dropdown variant="icon" icon="ellipsis-vertical" :label="'Actions for '.$row->student->name">
                                <x-dropdown.item :href="route('teacher.students.show', $row->student)" icon="eye">View progress</x-dropdown.item>
                                @if ($canGiveFeedback)
                                    <x-dropdown.item :href="route('teacher.feedback.create', ['student' => $row->student->getKey()])" icon="message-circle">Give feedback</x-dropdown.item>
                                @endif
                            </x-dropdown>
                        </x-table.td>
                    </x-table.row>
                @endforeach

                @if ($rows->hasPages())
                    <x-slot:footer>{{ $rows->links() }}</x-slot:footer>
                @endif
            </x-table>
            <p class="-mt-2 text-xs text-ink-600 dark:text-ink-300">
                Averages use each learner’s best attempt per quiz in {{ $classroom ? $classroom->name : 'your active classrooms' }}. Missions count completed missions in those classrooms’ subjects.
            </p>
        @endif
    @endif
</x-layouts.app>
