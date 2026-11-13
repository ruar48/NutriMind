@php
    $isFiltered = collect($filters)->contains(fn ($value) => $value !== null);
@endphp

<x-layouts.app title="Lesson plans">
    <x-page-header
        eyebrow="Teaching"
        title="Lesson plans"
        description="Plan each lesson, then align it with the game activities, quizzes and missions your learners play."
        :breadcrumbs="[['label' => 'Dashboard', 'url' => route('teacher.dashboard')], ['label' => 'Lesson plans']]"
    >
        <x-slot:actions>
            <x-button icon="plus" :href="route('teacher.lesson-plans.create')">New lesson plan</x-button>
        </x-slot:actions>
    </x-page-header>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-stat-card label="Lesson plans" :value="number_format($summary['total'])" icon="notebook-pen" color="leaf" :count-up="$summary['total']" data-animate style="--stagger: 1" />
        <x-stat-card label="Aligned to game content" :value="number_format($summary['aligned'])" icon="link" color="sky"
            :hint="$summary['total'] > 0 ? round($summary['aligned'] / $summary['total'] * 100).'% of your plans' : 'No plans yet'"
            :href="route('teacher.lesson-plans.index', ['alignment' => 'aligned'])" :count-up="$summary['aligned']" data-animate style="--stagger: 2" />
        <x-stat-card label="Not aligned yet" :value="number_format($summary['unaligned'])" icon="circle-dashed" color="sun" hint="Add activities, quizzes or missions"
            :href="route('teacher.lesson-plans.index', ['alignment' => 'unaligned'])" :count-up="$summary['unaligned']" data-animate style="--stagger: 3" />
        <x-stat-card label="Ongoing" :value="number_format($summary['ongoing'])" icon="timer" color="grape" hint="Lessons you are teaching now"
            :href="route('teacher.lesson-plans.index', ['status' => \App\Enums\LessonPlanStatus::Ongoing->value])" :count-up="$summary['ongoing']" data-animate style="--stagger: 4" />
    </div>

    <x-filter-bar
        :action="route('teacher.lesson-plans.index')"
        search-label="Search lesson plans"
        search-placeholder="Search title or competency code"
        :reset-url="route('teacher.lesson-plans.index')"
    >
        <x-form.select name="subject" label="Subject" hide-label placeholder="All subjects" :options="$subjectOptions" :value="$filters['subject']" :old="false" class="sm:w-44" />
        <x-form.select name="term" label="Quarter" hide-label placeholder="Any quarter" :options="$termOptions" :value="$filters['term']" :old="false" class="sm:w-44" />
        <x-form.select name="status" label="Status" hide-label placeholder="Any status" :options="\App\Enums\LessonPlanStatus::options()" :value="$filters['status']" :old="false" class="sm:w-36" />
        <x-form.select name="alignment" label="Alignment" hide-label placeholder="Any alignment" :options="['aligned' => 'Aligned', 'unaligned' => 'Not aligned']" :value="$filters['alignment']" :old="false" class="sm:w-40" />
    </x-filter-bar>

    @if ($lessonPlans->isEmpty())
        @if ($isFiltered)
            <x-empty-state variant="card" illustration="search" title="No lesson plans match your filters"
                description="Try another subject, quarter or status, or clear the filters.">
                <x-slot:actions>
                    <x-button variant="secondary" icon="rotate-ccw" :href="route('teacher.lesson-plans.index')">Clear filters</x-button>
                </x-slot:actions>
            </x-empty-state>
        @else
            <x-empty-state variant="card" illustration="classroom" title="No lesson plans yet"
                description="Write your first lesson plan, then link the game activities, quizzes and missions that support it.">
                <x-slot:actions>
                    <x-button icon="plus" :href="route('teacher.lesson-plans.create')">New lesson plan</x-button>
                </x-slot:actions>
            </x-empty-state>
        @endif
    @else
        <x-table caption="Your lesson plans">
            <x-slot:head>
                <x-table.th>Lesson</x-table.th>
                <x-table.th>Subject</x-table.th>
                <x-table.th>Grade</x-table.th>
                <x-table.th>Quarter</x-table.th>
                <x-table.th>Dates</x-table.th>
                <x-table.th>Status</x-table.th>
                <x-table.th>Game content</x-table.th>
                <x-table.th align="right"><span class="sr-only">Actions</span></x-table.th>
            </x-slot:head>

            @foreach ($lessonPlans as $lessonPlan)
                @php
                    $alignedCount = $lessonPlan->activities_count + $lessonPlan->quizzes_count + $lessonPlan->missions_count;
                @endphp
                <x-table.row data-lesson-plan="{{ $lessonPlan->id }}">
                    <x-table.td primary>
                        <div class="min-w-52 max-w-80">
                            <a href="{{ route('teacher.lesson-plans.show', $lessonPlan) }}" class="block truncate hover:text-leaf-700 hover:underline dark:hover:text-leaf-300">{{ $lessonPlan->title }}</a>
                            <p class="mt-0.5 truncate font-mono text-xs font-normal text-ink-500 dark:text-ink-400">{{ $lessonPlan->competency_code ?? 'No competency code' }}</p>
                        </div>
                    </x-table.td>
                    <x-table.td><x-subject-chip :subject="$lessonPlan->subject" size="sm" /></x-table.td>
                    <x-table.td><x-badge :color="$lessonPlan->grade_level->color()" size="sm">{{ $lessonPlan->grade_level->label() }}</x-badge></x-table.td>
                    <x-table.td class="whitespace-nowrap">
                        {{ $lessonPlan->academicTerm?->name ?? '—' }}
                        @if ($lessonPlan->week_number)
                            <p class="text-xs text-ink-500 dark:text-ink-400">Week {{ $lessonPlan->week_number }}</p>
                        @endif
                    </x-table.td>
                    <x-table.td class="whitespace-nowrap">
                        @if ($lessonPlan->starts_on || $lessonPlan->ends_on)
                            {{ $lessonPlan->starts_on?->format('M j, Y') ?? '…' }}
                            @if ($lessonPlan->ends_on && $lessonPlan->ends_on->toDateString() !== $lessonPlan->starts_on?->toDateString())
                                <p class="text-xs text-ink-500 dark:text-ink-400">to {{ $lessonPlan->ends_on->format('M j, Y') }}</p>
                            @endif
                        @else
                            <span class="text-ink-400 dark:text-ink-500">Not scheduled</span>
                        @endif
                    </x-table.td>
                    <x-table.td><x-status-badge :status="$lessonPlan->status" size="sm" /></x-table.td>
                    <x-table.td>
                        <div class="flex min-w-44 flex-col gap-1.5">
                            @if ($alignedCount > 0)
                                <x-badge color="leaf" icon="link" size="sm" data-alignment="aligned">Aligned</x-badge>
                            @else
                                <x-badge color="sun" icon="circle-dashed" size="sm" data-alignment="unaligned">Not aligned</x-badge>
                            @endif
                            <p class="flex items-center gap-3 text-xs text-ink-500 tabular-nums dark:text-ink-400">
                                <span class="inline-flex items-center gap-1" title="Activities">
                                    <x-icon name="puzzle" class="size-3.5" />{{ $lessonPlan->activities_count }}<span class="sr-only">{{ \Illuminate\Support\Str::plural('activity', $lessonPlan->activities_count) }}</span>
                                </span>
                                <span class="inline-flex items-center gap-1" title="Quizzes">
                                    <x-icon name="clipboard-check" class="size-3.5" />{{ $lessonPlan->quizzes_count }}<span class="sr-only">{{ \Illuminate\Support\Str::plural('quiz', $lessonPlan->quizzes_count) }}</span>
                                </span>
                                <span class="inline-flex items-center gap-1" title="Missions">
                                    <x-icon name="flag" class="size-3.5" />{{ $lessonPlan->missions_count }}<span class="sr-only">{{ \Illuminate\Support\Str::plural('mission', $lessonPlan->missions_count) }}</span>
                                </span>
                            </p>
                        </div>
                    </x-table.td>
                    <x-table.td align="right">
                        <x-dropdown variant="icon" icon="ellipsis-vertical" :label="'Actions for '.$lessonPlan->title">
                            <x-dropdown.item :href="route('teacher.lesson-plans.show', $lessonPlan)" icon="link">Open and align</x-dropdown.item>
                            <x-dropdown.item :href="route('teacher.lesson-plans.edit', $lessonPlan)" icon="pencil">Edit</x-dropdown.item>
                            <x-dropdown.divider />
                            <x-confirm-dialog
                                :action="route('teacher.lesson-plans.destroy', $lessonPlan)"
                                :title="'Delete “'.$lessonPlan->title.'”?'"
                                message="The lesson plan and its alignments are removed. The game content itself is not changed."
                                confirm="Delete lesson plan"
                                button="Delete"
                                button-variant="menu"
                            />
                        </x-dropdown>
                    </x-table.td>
                </x-table.row>
            @endforeach

            <x-slot:footer>{{ $lessonPlans->links() }}</x-slot:footer>
        </x-table>
    @endif
</x-layouts.app>
