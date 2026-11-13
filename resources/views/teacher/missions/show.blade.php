@php
    $isFiltered = $status !== null || $classroom !== null;
    $resetUrl = route('teacher.missions.show', $mission);
    $rate = $completion->rate();
    $statuses = [
        \App\Enums\ProgressStatus::Completed->value => $completion->completed,
        \App\Enums\ProgressStatus::InProgress->value => $completion->inProgress,
        \App\Enums\ProgressStatus::NotStarted->value => $completion->notStarted(),
    ];
@endphp

<x-layouts.app :title="$mission->title">
    <x-page-header
        eyebrow="Mission progress"
        :title="$mission->title"
        :description="$mission->objective"
        :breadcrumbs="[['label' => 'Mission progress', 'url' => route('teacher.missions.index')], ['label' => $mission->title]]"
    >
        <x-slot:meta>
            <x-subject-chip :subject="$mission->subject" />
            <x-status-badge :status="$mission->type" />
            <x-status-badge :status="$mission->difficulty" />
            <x-monitoring.mission-schedule :mission="$mission" size="md" />
            @unless ($mission->is_active)
                <x-badge color="slate" icon="eye-off">Not active in the game</x-badge>
            @endunless
        </x-slot:meta>
        <x-slot:actions>
            <x-button variant="secondary" icon="arrow-left" :href="route('teacher.missions.index')">All missions</x-button>
        </x-slot:actions>
    </x-page-header>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-stat-card label="Eligible learners" :value="number_format($completion->eligible)" icon="users" color="sky"
            :hint="$classroom ? $classroom->name : trans_choice('In :count classroom|In :count classrooms', $classrooms->count())" data-animate style="--stagger: 1" />
        <x-stat-card label="Completed" :value="number_format($completion->completed)" icon="circle-check" color="leaf"
            :hint="$rate === null ? 'No eligible learners' : rtrim(rtrim(number_format($rate, 1), '0'), '.').'% completion'" data-animate style="--stagger: 2" />
        <x-stat-card label="In progress" :value="number_format($completion->inProgress)" icon="timer" color="sky" hint="Started in the game" data-animate style="--stagger: 3" />
        <x-stat-card label="Not started" :value="number_format($completion->notStarted())" icon="clock" color="slate" hint="No progress synced yet" data-animate style="--stagger: 4" />
    </div>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <x-section title="Learner status" description="Where every eligible learner is on this mission" icon="chart-pie" color="carrot" class="lg:col-span-2">
            <x-chart.stacked-bar
                label="Learners by mission status"
                label-heading="Status"
                value-heading="Learners"
                empty-text="No eligible learners yet."
                :segments="collect([\App\Enums\ProgressStatus::Completed, \App\Enums\ProgressStatus::InProgress, \App\Enums\ProgressStatus::NotStarted])
                    ->map(fn ($progressStatus) => ['label' => $progressStatus->label(), 'value' => $statuses[$progressStatus->value], 'color' => $progressStatus->color()])
                    ->all()"
            />
        </x-section>

        <x-section title="About this mission" icon="flag" color="carrot">
            <x-definition-list>
                <x-detail-row label="Grade" :value="$mission->grade_level?->label() ?? 'Grades 5 and 6'" />
                <x-detail-row label="Reward" :value="'+'.number_format($mission->points_reward).' XP'" />
                <x-detail-row label="Learning module" :value="$mission->module?->title" />
                <x-detail-row label="Dates">
                    @if ($mission->starts_on || $mission->ends_on)
                        {{ $mission->starts_on?->format('M j, Y') ?? 'Open' }} – {{ $mission->ends_on?->format('M j, Y') ?? 'Open' }}
                    @else
                        Always available
                    @endif
                </x-detail-row>
            </x-definition-list>
            <p class="mt-4 text-sm leading-relaxed text-ink-600 dark:text-ink-300">{{ $mission->description }}</p>
        </x-section>
    </div>

    @if ($classrooms->isEmpty())
        <x-empty-state
            variant="card"
            illustration="classroom"
            title="None of your classrooms can play this mission"
            :description="'This mission is for '.($mission->grade_level?->label() ?? 'Grades 5 and 6').' learners in '.$mission->subject->name.'. Learners appear here once they are enrolled in one of your active classrooms of that subject and grade.'"
        />
    @else
        <x-filter-bar :action="route('teacher.missions.show', $mission)" :search="false" :reset-url="$resetUrl">
            <x-form.select
                name="classroom"
                label="Classroom"
                hide-label
                placeholder="All eligible classrooms"
                :options="$classrooms->mapWithKeys(fn ($option) => [$option->getKey() => $option->name])->all()"
                :value="$classroom?->getKey()"
                :old="false"
                class="sm:w-60"
            />
            <x-form.select
                name="status"
                label="Status"
                hide-label
                placeholder="Any status"
                :options="\App\Enums\ProgressStatus::options()"
                :value="$status?->value"
                :old="false"
                class="sm:w-48"
            />
        </x-filter-bar>

        @if ($learners->isEmpty())
            <x-empty-state variant="card" illustration="search" :title="$isFiltered ? 'No learners match your filters' : 'No learners yet'"
                :description="$isFiltered ? 'Try another classroom or status, or clear the filters.' : 'Enrolled learners appear here with their progress on this mission.'">
                @if ($isFiltered)
                    <x-slot:actions>
                        <x-button variant="secondary" icon="rotate-ccw" :href="$resetUrl">Clear filters</x-button>
                    </x-slot:actions>
                @endif
            </x-empty-state>
        @else
            <x-table caption="Learner progress on this mission">
                <x-slot:head>
                    <x-table.th>Learner</x-table.th>
                    <x-table.th>Status</x-table.th>
                    <x-table.th>Progress</x-table.th>
                    <x-table.th align="right">Points</x-table.th>
                    <x-table.th>Started</x-table.th>
                    <x-table.th>Completed</x-table.th>
                </x-slot:head>

                @foreach ($learners as $learner)
                    @php
                        $progress = $learner->missionProgress->first();
                        $learnerStatus = $progress?->status ?? \App\Enums\ProgressStatus::NotStarted;
                    @endphp
                    <x-table.row data-learner-row="{{ $learner->getKey() }}" data-status="{{ $learnerStatus->value }}">
                        <x-table.td primary>
                            <div class="flex min-w-52 items-center gap-3">
                                <x-avatar :name="$learner->name" size="sm" />
                                <div class="min-w-0">
                                    <a href="{{ route('teacher.students.show', $learner) }}" class="block truncate rounded hover:text-leaf-700 hover:underline dark:hover:text-leaf-300">{{ $learner->name }}</a>
                                    <p class="truncate text-xs font-normal text-ink-500 dark:text-ink-400">{{ $learner->enrolledClassrooms->pluck('name')->implode(', ') }}</p>
                                </div>
                            </div>
                        </x-table.td>
                        <x-table.td><x-status-badge :status="$learnerStatus" size="sm" /></x-table.td>
                        <x-table.td>
                            <x-progress
                                :value="$learnerStatus === \App\Enums\ProgressStatus::NotStarted ? 0 : ($progress?->progress_percent ?? 0)"
                                :color="$learnerStatus->color()"
                                size="sm"
                                show-value
                                class="w-36"
                            />
                        </x-table.td>
                        <x-table.td align="right" numeric>{{ number_format($progress?->points_earned ?? 0) }}</x-table.td>
                        <x-table.td><x-monitoring.time :value="$progress?->started_at" /></x-table.td>
                        <x-table.td><x-monitoring.time :value="$progress?->completed_at" /></x-table.td>
                    </x-table.row>
                @endforeach

                @if ($learners->hasPages())
                    <x-slot:footer>{{ $learners->links() }}</x-slot:footer>
                @endif
            </x-table>
        @endif
    @endif
</x-layouts.app>
