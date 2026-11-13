@php
    $isFiltered = collect($filters)->contains(fn ($value) => $value !== null);
    $quarterOptions = ['1' => '1st Quarter', '2' => '2nd Quarter', '3' => '3rd Quarter', '4' => '4th Quarter', 'none' => 'No quarter'];
@endphp

<x-layouts.app title="Learning modules">
    <x-page-header
        eyebrow="Game content"
        title="Learning modules"
        description="The lessons of each game world. Learners play a module’s published activities in order."
        :breadcrumbs="[['label' => 'Dashboard', 'url' => route('admin.dashboard')], ['label' => 'Learning modules']]"
    >
        <x-slot:meta>
            <x-badge color="slate" icon="layers">{{ trans_choice(':count module|:count modules', $totalModules) }}</x-badge>
        </x-slot:meta>
        <x-slot:actions>
            <x-button variant="secondary" icon="link" :href="route('admin.content-alignment.index')">Lesson alignment</x-button>
            <x-button icon="plus" :href="route('admin.modules.create')">New module</x-button>
        </x-slot:actions>
    </x-page-header>

    <x-filter-bar
        :action="route('admin.modules.index')"
        search-label="Search modules"
        search-placeholder="Search title, world or competency code"
        :reset-url="route('admin.modules.index')"
        data-animate
        style="--stagger: 1"
    >
        <x-form.select name="subject" label="Subject" hide-label placeholder="All subjects" :options="$subjectOptions" :value="$filters['subject']" :old="false" class="sm:w-48" />
        <x-form.select name="grade" label="Grade level" hide-label placeholder="All grades" :options="\App\Enums\GradeLevel::options()" :value="$filters['grade']" :old="false" class="sm:w-36" />
        <x-form.select name="quarter" label="Quarter" hide-label placeholder="Any quarter" :options="$quarterOptions" :value="$filters['quarter']" :old="false" class="sm:w-40" />
        <x-form.select name="status" label="Status" hide-label placeholder="Any status" :options="['published' => 'Published', 'draft' => 'Draft']" :value="$filters['status']" :old="false" class="sm:w-36" />
    </x-filter-bar>

    @if ($modules->isEmpty())
        @if ($isFiltered)
            <x-empty-state variant="card" illustration="search" title="No modules match your filters"
                description="Try another subject, grade or quarter, or clear the filters.">
                <x-slot:actions>
                    <x-button variant="secondary" icon="rotate-ccw" :href="route('admin.modules.index')">Clear filters</x-button>
                </x-slot:actions>
            </x-empty-state>
        @else
            <x-empty-state variant="card" illustration="sprout" title="No learning modules yet"
                description="Create the first module of a game world, then add its lessons, videos and mini-games.">
                <x-slot:actions>
                    <x-button icon="plus" :href="route('admin.modules.create')">New module</x-button>
                </x-slot:actions>
            </x-empty-state>
        @endif
    @else
        <x-table caption="Learning modules" data-animate style="--stagger: 2">
            <x-slot:head>
                <x-table.th>Module</x-table.th>
                <x-table.th>Subject</x-table.th>
                <x-table.th>Grade</x-table.th>
                <x-table.th>Quarter</x-table.th>
                <x-table.th>Competency</x-table.th>
                <x-table.th align="right">Activities</x-table.th>
                <x-table.th align="right">Missions</x-table.th>
                <x-table.th align="right"><span title="Lesson plans that follow the module or use its activities">Lesson plans</span></x-table.th>
                <x-table.th>Status</x-table.th>
                <x-table.th align="right"><span class="sr-only">Actions</span></x-table.th>
            </x-slot:head>

            @foreach ($modules as $module)
                <x-table.row>
                    <x-table.td primary>
                        <div class="min-w-48 max-w-72">
                            <a href="{{ route('admin.modules.show', $module) }}" class="block truncate hover:text-leaf-700 hover:underline dark:hover:text-leaf-300">{{ $module->title }}</a>
                            <p class="mt-0.5 flex items-center gap-1.5 truncate text-xs font-normal text-ink-500 dark:text-ink-400">
                                <x-icon name="map" class="size-3.5 shrink-0" />
                                <span class="truncate">{{ $module->world_name ?? 'No game world' }}</span>
                            </p>
                        </div>
                    </x-table.td>
                    <x-table.td><x-subject-chip :subject="$module->subject" size="sm" /></x-table.td>
                    <x-table.td><x-badge :color="$module->grade_level->color()" size="sm">{{ $module->grade_level->label() }}</x-badge></x-table.td>
                    <x-table.td class="whitespace-nowrap">{{ $module->quarter ? 'Q'.$module->quarter : '—' }}</x-table.td>
                    <x-table.td>
                        @if ($module->competency_code)
                            <span class="font-mono text-xs whitespace-nowrap text-ink-700 dark:text-ink-200" @if ($module->learning_competency) title="{{ $module->learning_competency }}" @endif>{{ $module->competency_code }}</span>
                        @else
                            <span class="text-ink-400 dark:text-ink-500">—</span>
                        @endif
                    </x-table.td>
                    <x-table.td align="right" numeric>{{ $module->activities_count }}</x-table.td>
                    <x-table.td align="right" numeric>{{ $module->missions_count }}</x-table.td>
                    <x-table.td align="right" numeric>{{ $module->lesson_plans_count }}</x-table.td>
                    <x-table.td>
                        <x-badge :color="$module->is_published ? 'leaf' : 'slate'" :icon="$module->is_published ? 'eye' : 'eye-off'" size="sm">{{ $module->is_published ? 'Published' : 'Draft' }}</x-badge>
                    </x-table.td>
                    <x-table.td align="right">
                        <x-dropdown variant="icon" icon="ellipsis-vertical" :label="'Actions for '.$module->title">
                            <x-dropdown.item :href="route('admin.modules.show', $module)" icon="eye">View module</x-dropdown.item>
                            <x-dropdown.item :href="route('admin.modules.edit', $module)" icon="pencil">Edit</x-dropdown.item>
                            <x-dropdown.item :href="route('admin.modules.activities.create', $module)" icon="plus">Add activity</x-dropdown.item>
                            <x-dropdown.divider />
                            @if ($module->has_progress)
                                <x-dropdown.item icon="trash-2" disabled>Delete (learners played it)</x-dropdown.item>
                            @else
                                <x-confirm-dialog
                                    :action="route('admin.modules.destroy', $module)"
                                    :title="'Delete “'.$module->title.'”?'"
                                    :message="trans_choice('{0} The module is removed. Missions, quizzes and lesson plans that use it are kept but unlinked.|{1} The module and its :count activity are removed. Missions, quizzes and lesson plans that use it are kept but unlinked.|[2,*] The module and its :count activities are removed. Missions, quizzes and lesson plans that use it are kept but unlinked.', $module->activities_count)"
                                    confirm="Delete module"
                                    button="Delete"
                                    button-variant="menu"
                                />
                            @endif
                        </x-dropdown>
                    </x-table.td>
                </x-table.row>
            @endforeach

            <x-slot:footer>{{ $modules->links() }}</x-slot:footer>
        </x-table>
    @endif
</x-layouts.app>
