@php
    $isFiltered = collect($filters)->contains(fn ($value) => $value !== null);
    $filterQuery = array_filter([
        'subject' => $filters['subject'],
        'grade' => $filters['grade']?->value,
        'search' => $filters['search'],
    ], fn ($value) => $value !== null);
    $statusTiles = [
        ['status' => \App\Services\Content\CoverageStatus::Covered, 'hint' => 'Module with playable content or quizzes'],
        ['status' => \App\Services\Content\CoverageStatus::Partial, 'hint' => 'Content started, not complete'],
        ['status' => \App\Services\Content\CoverageStatus::NotCovered, 'hint' => 'Needs a game module'],
    ];
@endphp

<x-layouts.app title="Lesson alignment">
    <x-page-header
        eyebrow="Game content"
        title="Lesson alignment"
        description="The competencies in teachers’ lesson plans, matched by competency code to the game modules, activities, missions and quizzes that cover them."
        :breadcrumbs="[['label' => 'Dashboard', 'url' => route('admin.dashboard')], ['label' => 'Lesson alignment']]"
    >
        <x-slot:actions>
            <x-button variant="secondary" icon="layers" :href="route('admin.modules.index')">Learning modules</x-button>
            <x-button icon="plus" :href="route('admin.modules.create')">New module</x-button>
        </x-slot:actions>
    </x-page-header>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5">
        <x-stat-card label="Planned competencies" :value="number_format($competencyCount)" icon="notebook-pen" color="grape"
            :hint="trans_choice('{0} From no lesson plans|{1} From :count lesson plan|[2,*] From :count lesson plans', $plannedLessonCount)"
            :count-up="$competencyCount" data-animate style="--stagger: 1" />
        @foreach ($statusTiles as $tile)
            <x-stat-card
                :label="$tile['status']->label()"
                :value="number_format($statusCounts[$tile['status']->value])"
                :icon="$tile['status']->icon()"
                :color="$tile['status']->color()"
                :hint="$tile['hint']"
                :href="route('admin.content-alignment.index', [...$filterQuery, 'status' => $tile['status']->value])"
                :count-up="$statusCounts[$tile['status']->value]"
                data-coverage-tile="{{ $tile['status']->value }}"
                data-animate
                style="--stagger: {{ $loop->iteration + 1 }}"
            />
        @endforeach
        <x-stat-card label="Unmapped lesson plans" :value="number_format($unmappedPlans)" icon="hash" color="slate"
            hint="No competency code to match" :count-up="$unmappedPlans" data-coverage-tile="unmapped" data-animate style="--stagger: 5" />
    </div>

    @if ($competencyCount > 0)
        <x-section title="Coverage at a glance" description="Share of planned competencies by coverage status." icon="chart-pie" color="sky" data-animate style="--stagger: 6">
            <x-chart.stacked-bar
                label="Planned competencies by coverage status"
                label-heading="Status"
                value-heading="Competencies"
                :segments="collect(\App\Services\Content\CoverageStatus::cases())->map(fn ($status) => ['label' => $status->label(), 'value' => $statusCounts[$status->value], 'color' => $status->color()])->all()"
            />
        </x-section>
    @endif

    @if ($unmappedPlans > 0)
        <x-alert variant="info" title="Some lesson plans have no competency code">
            {{ trans_choice('{1} :count lesson plan cannot be matched to game content until its teacher adds a competency code.|[2,*] :count lesson plans cannot be matched to game content until their teachers add a competency code.', $unmappedPlans) }}
        </x-alert>
    @endif

    <x-filter-bar
        :action="route('admin.content-alignment.index')"
        search-label="Search competencies"
        search-placeholder="Search competency code or description"
        :reset-url="route('admin.content-alignment.index')"
    >
        <x-form.select name="subject" label="Subject" hide-label placeholder="All subjects" :options="$subjectOptions" :value="$filters['subject']" :old="false" class="sm:w-48" />
        <x-form.select name="grade" label="Grade level" hide-label placeholder="All grades" :options="\App\Enums\GradeLevel::options()" :value="$filters['grade']" :old="false" class="sm:w-36" />
        <x-form.select name="status" label="Coverage status" hide-label placeholder="Any status" :options="\App\Services\Content\CoverageStatus::options()" :value="$filters['status']" :old="false" class="sm:w-40" />
    </x-filter-bar>

    @if ($competencies->isEmpty())
        @if ($isFiltered)
            <x-empty-state variant="card" illustration="search" title="No competencies match your filters"
                description="Try another subject, grade or status, or clear the filters.">
                <x-slot:actions>
                    <x-button variant="secondary" icon="rotate-ccw" :href="route('admin.content-alignment.index')">Clear filters</x-button>
                </x-slot:actions>
            </x-empty-state>
        @else
            <x-empty-state variant="card" illustration="chart" title="No planned competencies yet"
                description="Competencies appear here as soon as teachers add a competency code to their lesson plans. Meanwhile you can build modules for the curriculum.">
                <x-slot:actions>
                    <x-button icon="plus" :href="route('admin.modules.create')">New module</x-button>
                </x-slot:actions>
            </x-empty-state>
        @endif
    @else
        <x-table caption="Planned competencies and the game content that covers them">
            <x-slot:head>
                <x-table.th>Competency</x-table.th>
                <x-table.th>Subject</x-table.th>
                <x-table.th>Grade</x-table.th>
                <x-table.th>Lesson plans</x-table.th>
                <x-table.th>Game modules</x-table.th>
                <x-table.th align="right"><span title="Published activities of published modules">Activities</span></x-table.th>
                <x-table.th align="right">Missions</x-table.th>
                <x-table.th align="right"><span title="Quizzes aligned to the lesson plans">Quizzes</span></x-table.th>
                <x-table.th>Status</x-table.th>
                <x-table.th align="right"><span class="sr-only">Actions</span></x-table.th>
            </x-slot:head>

            @foreach ($competencies as $coverage)
                <x-table.row data-competency="{{ $coverage->code }}" :highlight="$coverage->status === \App\Services\Content\CoverageStatus::NotCovered">
                    <x-table.td primary>
                        <div class="min-w-52 max-w-80">
                            <span class="font-mono text-sm">{{ $coverage->code }}</span>
                            @if ($coverage->learningCompetency)
                                <p class="mt-0.5 line-clamp-2 text-xs font-normal text-ink-500 dark:text-ink-400">{{ $coverage->learningCompetency }}</p>
                            @endif
                        </div>
                    </x-table.td>
                    <x-table.td><x-subject-chip :subject="$coverage->subject" size="sm" /></x-table.td>
                    <x-table.td><x-badge :color="$coverage->grade->color()" size="sm">{{ $coverage->grade->label() }}</x-badge></x-table.td>
                    <x-table.td class="whitespace-nowrap">
                        <span class="font-medium text-ink-900 tabular-nums dark:text-ink-50">{{ trans_choice(':count plan|:count plans', $coverage->plansCount) }}</span>
                        <p class="text-xs text-ink-500 dark:text-ink-400">{{ trans_choice('by :count teacher|by :count teachers', $coverage->teachersCount) }}</p>
                    </x-table.td>
                    <x-table.td>
                        @if ($coverage->modules->isEmpty())
                            <span class="text-ink-400 dark:text-ink-500">No module yet</span>
                        @else
                            <ul class="flex min-w-44 flex-col gap-1">
                                @foreach ($coverage->modules as $module)
                                    <li class="flex items-center gap-1.5">
                                        <a href="{{ route('admin.modules.show', $module) }}" class="max-w-52 truncate font-medium text-ink-800 hover:text-leaf-700 hover:underline dark:text-ink-100 dark:hover:text-leaf-300">{{ $module->title }}</a>
                                        @unless ($module->is_published)
                                            <x-badge color="slate" size="sm">Draft</x-badge>
                                        @endunless
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </x-table.td>
                    <x-table.td align="right" numeric>{{ $coverage->publishedActivitiesCount }}</x-table.td>
                    <x-table.td align="right" numeric>{{ $coverage->missionsCount }}</x-table.td>
                    <x-table.td align="right" numeric>{{ $coverage->quizzesCount }}</x-table.td>
                    <x-table.td>
                        <x-status-badge :status="$coverage->status" size="sm" :title="$coverage->status->description()" data-coverage-status="{{ $coverage->status->value }}" />
                    </x-table.td>
                    <x-table.td align="right">
                        @if ($coverage->modules->isEmpty())
                            <x-button size="sm" icon="plus" :href="route('admin.modules.create', $coverage->modulePrefill())" class="whitespace-nowrap">Create module</x-button>
                        @else
                            <x-button variant="secondary" size="sm" icon="eye" :href="route('admin.modules.show', $coverage->modules->first())" class="whitespace-nowrap">Open module</x-button>
                        @endif
                    </x-table.td>
                </x-table.row>
            @endforeach

            <x-slot:footer>{{ $competencies->links() }}</x-slot:footer>
        </x-table>
    @endif
</x-layouts.app>
