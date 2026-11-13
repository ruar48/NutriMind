@php
    $breadcrumbs = [['label' => 'Dashboard', 'url' => route('admin.dashboard')], ['label' => 'School years']];
@endphp

<x-layouts.app title="School years" description="School years and quarters of the school calendar.">
    <x-page-header
        eyebrow="Academic setup"
        title="School years & quarters"
        description="The active school year and its current quarter appear in every portal. Teachers open classrooms in the active year."
        :breadcrumbs="$breadcrumbs"
    >
        <x-slot:actions>
            <x-button :href="route('admin.school-years.create')" icon="plus">New school year</x-button>
        </x-slot:actions>
    </x-page-header>

    @if ($activeSchoolYear)
        @php
            $currentTerm = $activeSchoolYear->terms->firstWhere('is_active', true);
            $yearStart = $activeSchoolYear->starts_on->copy()->startOfDay();
            $yearEnd = $activeSchoolYear->ends_on->copy()->startOfDay();
            $totalDays = (int) $yearStart->diffInDays($yearEnd) + 1;
            $elapsedDays = (int) min($totalDays, max(0, $yearStart->diffInDays(today(), false) + 1));
        @endphp

        <x-section
            title="Active school year"
            description="Shown to teachers and parents as the current school year."
            icon="calendar-range"
            color="grape"
            data-active-school-year="{{ $activeSchoolYear->id }}"
            data-animate
        >
            <x-slot:actions>
                <x-button variant="secondary" size="sm" icon="eye" :href="route('admin.school-years.show', $activeSchoolYear)">Manage quarters</x-button>
            </x-slot:actions>

            <div class="grid grid-cols-1 gap-6 lg:grid-cols-5">
                <div class="flex min-w-0 flex-col gap-4 lg:col-span-2">
                    <div>
                        <p class="font-display text-3xl font-bold tracking-tight text-ink-950 dark:text-white">{{ $activeSchoolYear->name }}</p>
                        <p class="mt-1 text-sm text-ink-600 dark:text-ink-300">
                            <x-setup.date-range :from="$activeSchoolYear->starts_on" :to="$activeSchoolYear->ends_on" />
                        </p>
                    </div>

                    <div class="flex flex-wrap gap-2">
                        @if ($currentTerm)
                            <x-badge color="leaf" icon="circle-check">{{ $currentTerm->name }} is current</x-badge>
                        @else
                            <x-badge color="sun" icon="triangle-alert">No current quarter</x-badge>
                        @endif
                        <x-badge color="slate" icon="school">{{ trans_choice(':count classroom|:count classrooms', $activeSchoolYear->classrooms_count) }}</x-badge>
                    </div>

                    <x-progress
                        :value="$elapsedDays"
                        :max="$totalDays"
                        color="grape"
                        label="School days elapsed"
                        show-value
                        :value-text="number_format($elapsedDays).' of '.number_format($totalDays).' days'"
                    />
                </div>

                <div class="min-w-0 lg:col-span-3">
                    <x-setup.term-timeline :school-year="$activeSchoolYear" />
                </div>
            </div>
        </x-section>
    @else
        <x-alert variant="warning" title="No active school year" data-no-active-school-year>
            Teachers can’t open classrooms and the portals show no school year until one is active.
            Activate a school year from the list below, or create a new one.
        </x-alert>
    @endif

    <div class="flex flex-col gap-4">
        <x-filter-bar
            :action="route('admin.school-years.index')"
            search-label="Search school years"
            search-placeholder="Search by name, e.g. 2026"
            :reset-url="route('admin.school-years.index')"
        >
            <x-form.select
                name="status"
                label="Status"
                hide-label
                placeholder="Any status"
                :options="$statusOptions"
                :value="$status"
                :old="false"
                class="sm:w-48"
            />
        </x-filter-bar>

        @if ($schoolYears->isEmpty())
            @if ($isFiltered)
                <x-empty-state variant="card" illustration="search" title="No school years match your filters" description="Try another name or status, or clear the filters.">
                    <x-slot:actions>
                        <x-button variant="secondary" icon="rotate-ccw" :href="route('admin.school-years.index')">Clear filters</x-button>
                    </x-slot:actions>
                </x-empty-state>
            @else
                <x-empty-state variant="card" illustration="hourglass" title="No school years yet" description="Create the current school year first. NutriMind can split it into four quarters for you.">
                    <x-slot:actions>
                        <x-button :href="route('admin.school-years.create')" icon="plus">New school year</x-button>
                    </x-slot:actions>
                </x-empty-state>
            @endif
        @else
            <x-table caption="School years">
                <x-slot:head>
                    <x-table.th>School year</x-table.th>
                    <x-table.th>Status</x-table.th>
                    <x-table.th align="right">Quarters</x-table.th>
                    <x-table.th align="right">Classrooms</x-table.th>
                    <x-table.th align="right"><span class="sr-only">Actions</span></x-table.th>
                </x-slot:head>

                @foreach ($schoolYears as $schoolYear)
                    <x-table.row :highlight="$schoolYear->is_active" data-school-year-row="{{ $schoolYear->id }}">
                        <x-table.td primary>
                            <div class="flex items-center gap-3">
                                <span @class([
                                    'grid size-10 shrink-0 place-items-center rounded-xl ring-1 ring-inset',
                                    'bg-grape-50 text-grape-700 ring-grape-600/15 dark:bg-grape-400/10 dark:text-grape-300 dark:ring-grape-400/20' => $schoolYear->is_active,
                                    'bg-ink-50 text-ink-500 ring-ink-900/5 dark:bg-white/5 dark:text-ink-400 dark:ring-white/8' => ! $schoolYear->is_active,
                                ])>
                                    <x-icon name="calendar-range" class="size-5" />
                                </span>
                                <div class="min-w-0">
                                    <a href="{{ route('admin.school-years.show', $schoolYear) }}" class="block truncate rounded hover:text-leaf-700 hover:underline focus-visible:outline-2 focus-visible:outline-offset-2 dark:hover:text-leaf-300">{{ $schoolYear->name }}</a>
                                    <p class="text-xs font-normal text-ink-500 dark:text-ink-400">
                                        <x-setup.date-range :from="$schoolYear->starts_on" :to="$schoolYear->ends_on" />
                                    </p>
                                </div>
                            </div>
                        </x-table.td>
                        <x-table.td><x-setup.year-status :school-year="$schoolYear" /></x-table.td>
                        <x-table.td align="right" numeric>{{ number_format($schoolYear->terms_count) }}</x-table.td>
                        <x-table.td align="right" numeric>{{ number_format($schoolYear->classrooms_count) }}</x-table.td>
                        <x-table.td align="right">
                            <x-dropdown variant="icon" icon="ellipsis-vertical" :label="'Actions for '.$schoolYear->name">
                                <x-dropdown.item :href="route('admin.school-years.show', $schoolYear)" icon="eye">View quarters</x-dropdown.item>
                                <x-dropdown.item :href="route('admin.school-years.edit', $schoolYear)" icon="pencil">Edit</x-dropdown.item>
                                @unless ($schoolYear->is_active)
                                    <x-confirm-dialog
                                        :action="route('admin.school-years.activation.update', $schoolYear)"
                                        method="PUT"
                                        :danger="false"
                                        :title="'Make '.$schoolYear->name.' the active school year?'"
                                        :message="$activeSchoolYear ? $activeSchoolYear->name.' stops being active. Teachers open new classrooms in '.$schoolYear->name.', and the quarter that covers today becomes current.' : 'Teachers open new classrooms in '.$schoolYear->name.', and the quarter that covers today becomes current.'"
                                        confirm="Make active"
                                        button="Make active"
                                        button-variant="menu"
                                        button-icon="circle-check"
                                    />
                                @endunless
                                <x-dropdown.divider />
                                @if ($schoolYear->is_active)
                                    <x-dropdown.item icon="trash-2" disabled>Active year · can’t delete</x-dropdown.item>
                                @elseif ($schoolYear->classrooms_count > 0)
                                    <x-dropdown.item icon="trash-2" disabled>Has classrooms · can’t delete</x-dropdown.item>
                                @else
                                    <x-confirm-dialog
                                        :action="route('admin.school-years.destroy', $schoolYear)"
                                        :title="'Delete '.$schoolYear->name.'?'"
                                        :message="'The school year and its '.trans_choice(':count quarter|:count quarters', $schoolYear->terms_count).' are removed. Quizzes and lesson plans linked to those quarters lose the link. This cannot be undone.'"
                                        confirm="Delete school year"
                                        button="Delete"
                                        button-variant="menu"
                                    />
                                @endif
                            </x-dropdown>
                        </x-table.td>
                    </x-table.row>
                @endforeach

                <x-slot:footer>{{ $schoolYears->links() }}</x-slot:footer>
            </x-table>
        @endif
    </div>
</x-layouts.app>
