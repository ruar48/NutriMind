@php
    $isFiltered = collect($filters)->contains(fn ($value) => $value !== null);
    $gradeOptions = ['5' => 'Grade 5', '6' => 'Grade 6', 'both' => 'Both grades'];
@endphp

<x-layouts.app title="Missions">
    <x-page-header
        eyebrow="Game content"
        title="Missions"
        description="Goals learners complete in the game for bonus points: story missions, daily and weekly quests, and challenges."
        :breadcrumbs="[['label' => 'Dashboard', 'url' => route('admin.dashboard')], ['label' => 'Missions']]"
    >
        <x-slot:actions>
            <x-button icon="plus" :href="route('admin.missions.create')">New mission</x-button>
        </x-slot:actions>
    </x-page-header>

    <x-filter-bar
        :action="route('admin.missions.index')"
        search-label="Search missions"
        search-placeholder="Search title or objective"
        :reset-url="route('admin.missions.index')"
        data-animate
        style="--stagger: 1"
    >
        <x-form.select name="subject" label="Subject" hide-label placeholder="All subjects" :options="$subjectOptions" :value="$filters['subject']" :old="false" class="sm:w-44" />
        <x-form.select name="type" label="Mission type" hide-label placeholder="All types" :options="\App\Enums\MissionType::options()" :value="$filters['type']" :old="false" class="sm:w-40" />
        <x-form.select name="difficulty" label="Difficulty" hide-label placeholder="Any difficulty" :options="\App\Enums\Difficulty::options()" :value="$filters['difficulty']" :old="false" class="sm:w-40" />
        <x-form.select name="grade" label="Grade level" hide-label placeholder="All grades" :options="$gradeOptions" :value="$filters['grade']" :old="false" class="sm:w-36" />
        <x-form.select name="status" label="Status" hide-label placeholder="Any status" :options="['active' => 'Active', 'inactive' => 'Inactive']" :value="$filters['status']" :old="false" class="sm:w-36" />
    </x-filter-bar>

    @if ($missions->isEmpty())
        @if ($isFiltered)
            <x-empty-state variant="card" illustration="search" title="No missions match your filters"
                description="Try another subject, type or grade, or clear the filters.">
                <x-slot:actions>
                    <x-button variant="secondary" icon="rotate-ccw" :href="route('admin.missions.index')">Clear filters</x-button>
                </x-slot:actions>
            </x-empty-state>
        @else
            <x-empty-state variant="card" illustration="trophy" title="No missions yet"
                description="Create story missions for each game world, or daily and weekly quests that keep learners coming back.">
                <x-slot:actions>
                    <x-button icon="plus" :href="route('admin.missions.create')">New mission</x-button>
                </x-slot:actions>
            </x-empty-state>
        @endif
    @else
        <x-table caption="Missions" data-animate style="--stagger: 2">
            <x-slot:head>
                <x-table.th>Mission</x-table.th>
                <x-table.th>Subject</x-table.th>
                <x-table.th>Module</x-table.th>
                <x-table.th>Grade</x-table.th>
                <x-table.th>Type</x-table.th>
                <x-table.th align="right">Reward</x-table.th>
                <x-table.th>Schedule</x-table.th>
                <x-table.th align="right"><span title="Learners who completed the mission, of those who started it">Completed</span></x-table.th>
                <x-table.th>Status</x-table.th>
                <x-table.th align="right"><span class="sr-only">Actions</span></x-table.th>
            </x-slot:head>

            @foreach ($missions as $mission)
                <x-table.row>
                    <x-table.td primary>
                        <div class="min-w-48 max-w-72">
                            <a href="{{ route('admin.missions.edit', $mission) }}" class="block truncate hover:text-leaf-700 hover:underline dark:hover:text-leaf-300">{{ $mission->title }}</a>
                            <p class="mt-0.5 truncate text-xs font-normal text-ink-500 dark:text-ink-400">{{ $mission->objective }}</p>
                        </div>
                    </x-table.td>
                    <x-table.td><x-subject-chip :subject="$mission->subject" size="sm" /></x-table.td>
                    <x-table.td>
                        @if ($mission->module)
                            <a href="{{ route('admin.modules.show', $mission->module) }}" class="block max-w-48 truncate hover:text-leaf-700 hover:underline dark:hover:text-leaf-300">{{ $mission->module->title }}</a>
                        @else
                            <span class="text-ink-400 dark:text-ink-500">No module</span>
                        @endif
                    </x-table.td>
                    <x-table.td>
                        @if ($mission->grade_level)
                            <x-badge :color="$mission->grade_level->color()" size="sm">{{ $mission->grade_level->label() }}</x-badge>
                        @else
                            <x-badge color="slate" size="sm">Both grades</x-badge>
                        @endif
                    </x-table.td>
                    <x-table.td>
                        <div class="flex flex-wrap gap-1.5">
                            <x-status-badge :status="$mission->type" size="sm" />
                            <x-status-badge :status="$mission->difficulty" size="sm" />
                        </div>
                    </x-table.td>
                    <x-table.td align="right" numeric class="whitespace-nowrap">{{ number_format($mission->points_reward) }} XP</x-table.td>
                    <x-table.td class="whitespace-nowrap">
                        @if ($mission->starts_on || $mission->ends_on)
                            {{ $mission->starts_on?->format('M j, Y') ?? 'Any time' }} – {{ $mission->ends_on?->format('M j, Y') ?? 'open' }}
                        @else
                            <span class="text-ink-400 dark:text-ink-500">Always</span>
                        @endif
                    </x-table.td>
                    <x-table.td align="right" numeric class="whitespace-nowrap">{{ number_format($mission->completed_count) }} / {{ number_format($mission->progress_count) }}</x-table.td>
                    <x-table.td>
                        <x-badge :color="$mission->is_active ? 'leaf' : 'slate'" dot size="sm">{{ $mission->is_active ? 'Active' : 'Inactive' }}</x-badge>
                    </x-table.td>
                    <x-table.td align="right">
                        <x-dropdown variant="icon" icon="ellipsis-vertical" :label="'Actions for '.$mission->title">
                            <x-dropdown.item :href="route('admin.missions.edit', $mission)" icon="pencil">Edit</x-dropdown.item>
                            <x-dropdown.divider />
                            @if ($mission->progress_count > 0)
                                <x-dropdown.item icon="trash-2" disabled>Delete (learners started it)</x-dropdown.item>
                            @else
                                <x-confirm-dialog
                                    :action="route('admin.missions.destroy', $mission)"
                                    :title="'Delete “'.$mission->title.'”?'"
                                    message="The mission is removed from the game and from any lesson plan aligned to it."
                                    confirm="Delete mission"
                                    button="Delete"
                                    button-variant="menu"
                                />
                            @endif
                        </x-dropdown>
                    </x-table.td>
                </x-table.row>
            @endforeach

            <x-slot:footer>{{ $missions->links() }}</x-slot:footer>
        </x-table>
    @endif
</x-layouts.app>
