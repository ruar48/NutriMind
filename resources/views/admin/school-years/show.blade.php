@php
    $yearStart = $schoolYear->starts_on->copy()->startOfDay();
    $yearEnd = $schoolYear->ends_on->copy()->startOfDay();
    $totalDays = (int) $yearStart->diffInDays($yearEnd) + 1;
    $today = today();
    $calendarHint = match (true) {
        $today->lessThan($yearStart) => 'Starts in '.trans_choice(':count day|:count days', (int) $today->diffInDays($yearStart)),
        $today->greaterThan($yearEnd) => 'Ended '.$schoolYear->ends_on->diffForHumans(),
        default => 'Day '.number_format((int) $yearStart->diffInDays($today) + 1).' of '.number_format($totalDays),
    };
    $currentTerm = $schoolYear->terms->firstWhere('is_active', true);
    $archivedClassrooms = $schoolYear->classrooms_count - $classroomsByGrade->sum('open');
    $isDeletable = ! $schoolYear->is_active && $schoolYear->classrooms_count === 0;
    $termStates = [
        'current' => ['icon' => 'circle-check', 'color' => 'leaf'],
        'today' => ['icon' => 'triangle-alert', 'color' => 'sun'],
        'upcoming' => ['icon' => 'calendar', 'color' => 'sky'],
        'ended' => ['icon' => 'history', 'color' => 'slate'],
    ];
@endphp

<x-layouts.app :title="$schoolYear->name">
    <x-page-header
        eyebrow="School year"
        :title="$schoolYear->name"
        :breadcrumbs="[
            ['label' => 'Dashboard', 'url' => route('admin.dashboard')],
            ['label' => 'School years', 'url' => route('admin.school-years.index')],
            ['label' => $schoolYear->name],
        ]"
    >
        <x-slot:leading>
            <span class="grid size-14 place-items-center rounded-2xl bg-grape-600 text-white shadow-card">
                <x-icon name="calendar-range" class="size-7" />
            </span>
        </x-slot:leading>
        <x-slot:meta>
            <x-setup.year-status :school-year="$schoolYear" />
            <span class="text-sm text-ink-600 dark:text-ink-300"><x-setup.date-range :from="$schoolYear->starts_on" :to="$schoolYear->ends_on" /></span>
        </x-slot:meta>
        <x-slot:actions>
            @unless ($schoolYear->is_active)
                <x-confirm-dialog
                    :action="route('admin.school-years.activation.update', $schoolYear)"
                    method="PUT"
                    :danger="false"
                    :title="'Make '.$schoolYear->name.' the active school year?'"
                    message="The current active year stops being active. Teachers open new classrooms in this year, and the quarter that covers today becomes current."
                    confirm="Make active"
                    button="Make active"
                    button-variant="secondary"
                    button-size="md"
                    button-icon="circle-check"
                />
            @endunless
            <x-button variant="secondary" icon="pencil" :href="route('admin.school-years.edit', $schoolYear)">Edit</x-button>
            @if ($isDeletable)
                <x-confirm-dialog
                    :action="route('admin.school-years.destroy', $schoolYear)"
                    :title="'Delete '.$schoolYear->name.'?'"
                    :message="'The school year and its '.trans_choice(':count quarter|:count quarters', $schoolYear->terms->count()).' are removed. Quizzes and lesson plans linked to those quarters lose the link. This cannot be undone.'"
                    confirm="Delete school year"
                    button="Delete"
                    button-variant="ghost"
                    button-size="md"
                />
            @endif
        </x-slot:actions>
    </x-page-header>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-stat-card label="Quarters" :value="number_format($schoolYear->terms->count())" icon="layers" color="grape" :hint="$currentTerm ? $currentTerm->name.' is current' : 'No current quarter'" data-animate style="--stagger: 1" />
        <x-stat-card label="Classrooms" :value="number_format($schoolYear->classrooms_count)" icon="school" color="leaf" :hint="$archivedClassrooms > 0 ? number_format($archivedClassrooms).' archived' : null" data-animate style="--stagger: 2" />
        <x-stat-card label="Learners enrolled" :value="number_format($learnersCount)" icon="users" color="sky" hint="In this year's classrooms" data-animate style="--stagger: 3" />
        <x-stat-card label="Length" :value="number_format($totalDays).' days'" icon="calendar-days" color="carrot" :hint="$calendarHint" data-animate style="--stagger: 4" />
    </div>

    <div class="grid grid-cols-1 gap-6 xl:grid-cols-3">
        <x-section
            title="Quarters"
            description="In sequence order. Only a quarter of the active school year can be the current quarter."
            icon="layers"
            color="grape"
            class="xl:col-span-2"
        >
            <x-slot:actions>
                @if ($canAddTerm)
                    <x-button size="sm" icon="plus" :href="route('admin.school-years.terms.create', $schoolYear)">Add quarter</x-button>
                @else
                    <x-button size="sm" icon="plus" disabled>Add quarter</x-button>
                @endif
            </x-slot:actions>

            @if ($schoolYear->terms->isEmpty())
                <x-empty-state
                    variant="dashed"
                    compact
                    icon="layers"
                    title="No quarters yet"
                    description="Add quarters one by one, or split the school year into four quarters automatically."
                >
                    <x-slot:actions>
                        <x-button size="sm" icon="plus" :href="route('admin.school-years.terms.create', $schoolYear)">Add quarter</x-button>
                        <x-button size="sm" variant="secondary" icon="layout-grid" :href="route('admin.school-years.edit', [$schoolYear, 'quarters' => 1])">Split into 4 quarters</x-button>
                    </x-slot:actions>
                </x-empty-state>
            @else
                <div class="flex flex-col gap-6">
                    <x-setup.term-timeline :school-year="$schoolYear" />

                    @unless ($schoolYear->is_active)
                        <x-alert variant="info" title="Activate this school year to choose its current quarter">
                            The current quarter is picked automatically when the year becomes active (the one that covers today).
                        </x-alert>
                    @endunless

                    <x-timeline>
                        @foreach ($schoolYear->terms as $term)
                            @php
                                $termState = match (true) {
                                    $term->is_active => 'current',
                                    $term->starts_on->format('Y-m-d') > $today->format('Y-m-d') => 'upcoming',
                                    $term->ends_on->format('Y-m-d') < $today->format('Y-m-d') => 'ended',
                                    default => 'today',
                                };
                                $termWeeks = round(($term->starts_on->diffInDays($term->ends_on) + 1) / 7, 1);
                            @endphp
                            <x-timeline.item
                                :title="$term->name"
                                :icon="$termStates[$termState]['icon']"
                                :color="$termStates[$termState]['color']"
                                data-term-row="{{ $term->id }}"
                            >
                                <div class="flex flex-col gap-3">
                                    <div class="flex flex-wrap items-center gap-x-3 gap-y-2">
                                        <x-setup.term-status :term="$term" size="sm" />
                                        <span class="text-ink-700 dark:text-ink-200"><x-setup.date-range :from="$term->starts_on" :to="$term->ends_on" /></span>
                                        <span class="text-xs text-ink-500 dark:text-ink-400">Quarter {{ $term->sequence }} · {{ rtrim(rtrim(number_format($termWeeks, 1), '0'), '.') }} weeks</span>
                                    </div>
                                    <p class="text-xs text-ink-500 dark:text-ink-400">
                                        {{ trans_choice(':count quiz|:count quizzes', $term->quizzes_count) }} · {{ trans_choice(':count lesson plan|:count lesson plans', $term->lesson_plans_count) }}
                                    </p>
                                    <div class="flex flex-wrap items-center gap-2">
                                        @if ($schoolYear->is_active && ! $term->is_active)
                                            <x-confirm-dialog
                                                :action="route('admin.terms.activation.update', $term)"
                                                method="PUT"
                                                :danger="false"
                                                :title="'Make '.$term->name.' the current quarter?'"
                                                message="Teachers and parents see it as the current quarter, and new quizzes and lesson plans default to it."
                                                confirm="Set as current"
                                                button="Set as current"
                                                button-variant="soft"
                                                button-icon="circle-check"
                                            />
                                        @endif
                                        <x-button variant="ghost" size="sm" icon="pencil" :href="route('admin.terms.edit', $term)">Edit</x-button>
                                        @unless ($term->is_active)
                                            <x-confirm-dialog
                                                :action="route('admin.terms.destroy', $term)"
                                                :title="'Delete '.$term->name.'?'"
                                                :message="($term->quizzes_count + $term->lesson_plans_count) > 0
                                                    ? 'Its '.trans_choice(':count quiz|:count quizzes', $term->quizzes_count).' and '.trans_choice(':count lesson plan|:count lesson plans', $term->lesson_plans_count).' stay, without a quarter. This cannot be undone.'
                                                    : 'The quarter is removed. This cannot be undone.'"
                                                confirm="Delete quarter"
                                                button="Delete"
                                                button-variant="ghost"
                                            />
                                        @endunless
                                    </div>
                                </div>
                            </x-timeline.item>
                        @endforeach
                    </x-timeline>
                </div>
            @endif
        </x-section>

        <aside class="flex min-w-0 flex-col gap-6">
            <x-section title="Classrooms by grade" description="Classrooms teachers opened in this school year." icon="school" color="leaf">
                @if ($schoolYear->classrooms_count === 0)
                    <x-empty-state compact icon="school" title="No classrooms yet" description="Teachers open classrooms once this is the active school year." />
                @else
                    <div class="flex flex-col gap-4">
                        @foreach ($classroomsByGrade as $row)
                            <div data-grade-classrooms="{{ $row['grade']->value }}">
                                <x-progress
                                    :value="$row['total']"
                                    :max="max(1, $schoolYear->classrooms_count)"
                                    :color="$row['grade']->color()"
                                    :label="$row['grade']->label()"
                                    show-value
                                    :value-text="trans_choice(':count classroom|:count classrooms', $row['total'])"
                                />
                                @if ($row['total'] > $row['open'])
                                    <p class="mt-1 text-xs text-ink-500 dark:text-ink-400">{{ number_format($row['total'] - $row['open']) }} archived</p>
                                @endif
                            </div>
                        @endforeach
                    </div>
                @endif
            </x-section>

            <x-section title="Details" icon="info" color="slate">
                <x-definition-list>
                    <x-detail-row label="Name" :value="$schoolYear->name" />
                    <x-detail-row label="Starts" :value="$schoolYear->starts_on->format('M j, Y')" />
                    <x-detail-row label="Ends" :value="$schoolYear->ends_on->format('M j, Y')" />
                    <x-detail-row label="Length">{{ number_format($totalDays) }} days · {{ rtrim(rtrim(number_format($totalDays / 7, 1), '0'), '.') }} weeks</x-detail-row>
                    <x-detail-row label="Created" :value="$schoolYear->created_at?->format('M j, Y')" />
                </x-definition-list>
            </x-section>
        </aside>
    </div>
</x-layouts.app>
