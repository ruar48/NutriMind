@php
    $isFiltered = request()->filled('search') || request()->filled('type') || $classroom !== null;
    $resetUrl = route('teacher.missions.index');
    $classroomBars = collect($classroomCompletion)
        ->filter(fn (array $row) => $row['completion']->eligible > 0)
        ->map(fn (array $row) => [
            'label' => $row['classroom']->name,
            'value' => $row['completion']->rate() ?? 0,
            'sublabel' => number_format($row['completion']->completed).' of '.number_format($row['completion']->eligible).' learner missions completed',
            'url' => route('teacher.missions.index', ['classroom' => $row['classroom']->getKey()]),
        ])
        ->values()
        ->all();
@endphp

<x-layouts.app title="Mission progress">
    <x-page-header
        eyebrow="Monitoring"
        title="Mission progress"
        description="How your learners are doing on the game’s active missions in your subjects."
        :breadcrumbs="[['label' => 'Dashboard', 'url' => route('teacher.dashboard')], ['label' => 'Mission progress']]"
    >
        <x-slot:actions>
            <x-button variant="secondary" icon="users" :href="route('teacher.students.index')">Learners</x-button>
        </x-slot:actions>
    </x-page-header>

    @if ($classrooms->isNotEmpty())
        <x-section title="Completion by classroom" description="Completed missions out of every active mission each classroom’s learners can play" icon="school" color="carrot">
            <x-chart.hbar
                label="Mission completion by classroom"
                format="percent"
                label-heading="Classroom"
                value-heading="Completed"
                empty-text="None of your classrooms has an active mission to play yet."
                :items="$classroomBars"
            />
        </x-section>
    @endif

    <x-filter-bar :action="route('teacher.missions.index')" search-label="Search missions" search-placeholder="Search mission titles" :reset-url="$resetUrl">
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
            name="type"
            label="Mission type"
            hide-label
            placeholder="Any type"
            :options="\App\Enums\MissionType::options()"
            :value="request('type')"
            :old="false"
            class="sm:w-44"
        />
    </x-filter-bar>

    @if ($missions->isEmpty())
        @if ($isFiltered)
            <x-empty-state variant="card" illustration="search" title="No missions match your filters" description="Try another title, type or classroom, or clear the filters.">
                <x-slot:actions>
                    <x-button variant="secondary" icon="rotate-ccw" :href="$resetUrl">Clear filters</x-button>
                </x-slot:actions>
            </x-empty-state>
        @else
            <x-empty-state variant="card" illustration="lost-map" title="No active missions yet" description="Missions appear here when the school administrator publishes game missions for your subjects." />
        @endif
    @else
        <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
            @foreach ($missions as $mission)
                @php
                    $stats = $completion[$mission->getKey()];
                    $rate = $stats->rate();
                @endphp
                <x-card :href="route('teacher.missions.show', array_filter(['mission' => $mission, 'classroom' => $classroom?->getKey()]))" class="flex flex-col" data-mission-card="{{ $mission->getKey() }}">
                    <div class="flex flex-wrap items-center gap-2">
                        <x-status-badge :status="$mission->type" size="sm" />
                        <x-status-badge :status="$mission->difficulty" size="sm" />
                        <x-monitoring.mission-schedule :mission="$mission" />
                    </div>

                    <h2 class="mt-3 font-display text-base font-semibold tracking-tight break-words text-ink-950 dark:text-white">{{ $mission->title }}</h2>
                    <p class="mt-1 line-clamp-2 text-sm text-ink-600 dark:text-ink-300">{{ $mission->objective }}</p>

                    <div class="mt-3 flex flex-wrap items-center gap-2 text-xs text-ink-500 dark:text-ink-400">
                        <x-subject-chip :subject="$mission->subject" size="sm" />
                        <span>{{ $mission->grade_level?->label() ?? 'Grades 5 and 6' }}</span>
                        <span aria-hidden="true">·</span>
                        <span class="font-semibold text-sun-800 tabular-nums dark:text-sun-300">+{{ number_format($mission->points_reward) }} XP</span>
                    </div>

                    <div class="mt-5">
                        @if ($stats->eligible > 0)
                            <x-progress
                                :value="$stats->completed"
                                :max="$stats->eligible"
                                color="leaf"
                                label="Completed"
                                show-value
                                :value-text="rtrim(rtrim(number_format($rate, 1), '0'), '.').'%'"
                            />
                        @else
                            <p class="text-sm text-ink-500 dark:text-ink-400">None of {{ $classroom ? 'this classroom’s' : 'your' }} learners can play this mission.</p>
                        @endif
                    </div>

                    <dl class="mt-4 grid grid-cols-3 gap-3 border-t border-ink-900/5 pt-4 text-center dark:border-white/8">
                        <div>
                            <dt class="text-xs text-ink-500 dark:text-ink-400">Eligible</dt>
                            <dd class="mt-0.5 font-display text-lg font-bold text-ink-950 tabular-nums dark:text-white" data-stat="eligible">{{ number_format($stats->eligible) }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs text-ink-500 dark:text-ink-400">Completed</dt>
                            <dd class="mt-0.5 font-display text-lg font-bold text-ink-950 tabular-nums dark:text-white" data-stat="completed">{{ number_format($stats->completed) }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs text-ink-500 dark:text-ink-400">In progress</dt>
                            <dd class="mt-0.5 font-display text-lg font-bold text-ink-950 tabular-nums dark:text-white" data-stat="in-progress">{{ number_format($stats->inProgress) }}</dd>
                        </div>
                    </dl>
                </x-card>
            @endforeach
        </div>

        {{ $missions->links() }}
    @endif
</x-layouts.app>
