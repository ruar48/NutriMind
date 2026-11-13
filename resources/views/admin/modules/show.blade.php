@php
    $hasProgress = $activities->contains(fn ($activity) => $activity->progress_count > 0);
    $publishedActivities = $activities->where('is_published', true)->count();
@endphp

<x-layouts.app :title="$module->title">
    <x-page-header
        eyebrow="Learning module"
        :title="$module->title"
        :description="collect([$module->world_name, $module->subject->name, $module->grade_level->label(), $module->quarter ? 'Quarter '.$module->quarter : null])->filter()->implode(' · ')"
        :breadcrumbs="[['label' => 'Learning modules', 'url' => route('admin.modules.index')], ['label' => $module->title]]"
    >
        <x-slot:leading>
            <span class="grid size-14 place-items-center rounded-2xl bg-grape-600 text-white shadow-card dark:bg-grape-500">
                <x-icon name="map" class="size-7" />
            </span>
        </x-slot:leading>
        <x-slot:meta>
            <x-subject-chip :subject="$module->subject" />
            <x-badge :color="$module->grade_level->color()" icon="graduation-cap">{{ $module->grade_level->label() }}</x-badge>
            <x-badge :color="$module->is_published ? 'leaf' : 'slate'" :icon="$module->is_published ? 'eye' : 'eye-off'">{{ $module->is_published ? 'Published' : 'Draft' }}</x-badge>
            @if ($module->competency_code)
                <x-badge color="slate" icon="hash"><span class="font-mono">{{ $module->competency_code }}</span></x-badge>
            @endif
        </x-slot:meta>
        <x-slot:actions>
            @if ($hasProgress)
                <x-button variant="ghost" icon="trash-2" disabled title="Learners have already played activities in this module.">Delete</x-button>
            @else
                <x-confirm-dialog
                    :action="route('admin.modules.destroy', $module)"
                    :title="'Delete “'.$module->title.'”?'"
                    :message="trans_choice('{0} The module is removed. Missions, quizzes and lesson plans that use it are kept but unlinked.|{1} The module and its :count activity are removed. Missions, quizzes and lesson plans that use it are kept but unlinked.|[2,*] The module and its :count activities are removed. Missions, quizzes and lesson plans that use it are kept but unlinked.', $activities->count())"
                    confirm="Delete module"
                    button="Delete"
                    button-variant="ghost"
                    button-size="md"
                />
            @endif
            <x-button variant="secondary" icon="pencil" :href="route('admin.modules.edit', $module)">Edit</x-button>
            <x-button icon="plus" :href="route('admin.modules.activities.create', $module)">Add activity</x-button>
        </x-slot:actions>
    </x-page-header>

    <div class="grid grid-cols-1 gap-6 xl:grid-cols-3">
        <div class="flex min-w-0 flex-col gap-6 xl:col-span-2">
            <x-section
                title="Activities"
                :description="$activities->isEmpty() ? 'Learners play them in this order.' : $publishedActivities.' of '.$activities->count().' published · learners play them in this order'"
                icon="puzzle"
                color="grape"
                padding="none"
                data-animate
                style="--stagger: 1"
            >
                @if ($activities->isNotEmpty())
                    <x-slot:actions>
                        <x-button variant="soft" size="sm" icon="plus" :href="route('admin.modules.activities.create', $module)">Add activity</x-button>
                    </x-slot:actions>
                @endif

                @if ($activities->isEmpty())
                    <x-empty-state illustration="empty-plate" title="No activities yet"
                        description="Add the lessons, videos, readings and mini-games learners play in this module.">
                        <x-slot:actions>
                            <x-button icon="plus" :href="route('admin.modules.activities.create', $module)">Add activity</x-button>
                        </x-slot:actions>
                    </x-empty-state>
                @else
                    <x-table flush caption="Activities of the module">
                        <x-slot:head>
                            <x-table.th align="right">#</x-table.th>
                            <x-table.th>Activity</x-table.th>
                            <x-table.th>Type</x-table.th>
                            <x-table.th align="right">Time</x-table.th>
                            <x-table.th align="right">Points</x-table.th>
                            <x-table.th align="right"><span title="Lesson plans aligned to the activity">Plans</span></x-table.th>
                            <x-table.th align="right"><span title="Learners who started the activity">Learners</span></x-table.th>
                            <x-table.th>Status</x-table.th>
                            <x-table.th align="right"><span class="sr-only">Actions</span></x-table.th>
                        </x-slot:head>
                        @foreach ($activities as $activity)
                            <x-table.row>
                                <x-table.td align="right" numeric>{{ $activity->sort_order }}</x-table.td>
                                <x-table.td primary>
                                    <div class="min-w-48 max-w-80">
                                        <a href="{{ route('admin.activities.edit', $activity) }}" class="block truncate hover:text-leaf-700 hover:underline dark:hover:text-leaf-300">{{ $activity->title }}</a>
                                        @if ($activity->description)
                                            <p class="mt-0.5 truncate text-xs font-normal text-ink-500 dark:text-ink-400">{{ $activity->description }}</p>
                                        @endif
                                    </div>
                                </x-table.td>
                                <x-table.td><x-status-badge :status="$activity->type" size="sm" /></x-table.td>
                                <x-table.td align="right" numeric class="whitespace-nowrap">{{ $activity->estimated_minutes ? $activity->estimated_minutes.' min' : '—' }}</x-table.td>
                                <x-table.td align="right" numeric class="whitespace-nowrap">{{ number_format($activity->points) }} XP</x-table.td>
                                <x-table.td align="right" numeric>{{ $activity->lesson_plans_count }}</x-table.td>
                                <x-table.td align="right" numeric>{{ number_format($activity->progress_count) }}</x-table.td>
                                <x-table.td>
                                    <x-badge :color="$activity->is_published ? 'leaf' : 'slate'" :icon="$activity->is_published ? 'eye' : 'eye-off'" size="sm">{{ $activity->is_published ? 'Published' : 'Draft' }}</x-badge>
                                </x-table.td>
                                <x-table.td align="right">
                                    <x-dropdown variant="icon" icon="ellipsis-vertical" :label="'Actions for '.$activity->title">
                                        <x-dropdown.item :href="route('admin.activities.edit', $activity)" icon="pencil">Edit</x-dropdown.item>
                                        @if ($activity->content_url)
                                            <x-dropdown.item :href="$activity->content_url" icon="external-link" target="_blank" rel="noopener noreferrer">Open content link</x-dropdown.item>
                                        @endif
                                        <x-dropdown.divider />
                                        @if ($activity->progress_count > 0)
                                            <x-dropdown.item icon="trash-2" disabled>Delete (learners played it)</x-dropdown.item>
                                        @else
                                            <x-confirm-dialog
                                                :action="route('admin.activities.destroy', $activity)"
                                                :title="'Delete “'.$activity->title.'”?'"
                                                message="The activity is removed from the module and from any lesson plan aligned to it."
                                                confirm="Delete activity"
                                                button="Delete"
                                                button-variant="menu"
                                            />
                                        @endif
                                    </x-dropdown>
                                </x-table.td>
                            </x-table.row>
                        @endforeach
                    </x-table>
                @endif
            </x-section>

            <x-section
                title="Missions in this module"
                description="Story, daily, weekly and challenge missions that belong to the module."
                icon="flag"
                color="carrot"
                padding="none"
                data-animate
                style="--stagger: 2"
            >
                <x-slot:actions>
                    <x-button variant="soft" size="sm" icon="plus" :href="route('admin.missions.create', ['learning_module_id' => $module->id])">New mission</x-button>
                </x-slot:actions>

                @if ($missions->isEmpty())
                    <x-empty-state compact icon="flag" title="No missions in this module"
                        description="Missions give learners goals and bonus points while they play the module." />
                @else
                    <x-table flush caption="Missions of the module">
                        <x-slot:head>
                            <x-table.th>Mission</x-table.th>
                            <x-table.th>Type</x-table.th>
                            <x-table.th>Difficulty</x-table.th>
                            <x-table.th align="right">Reward</x-table.th>
                            <x-table.th align="right"><span title="Learners who completed the mission, of those who started it">Completed</span></x-table.th>
                            <x-table.th>Status</x-table.th>
                        </x-slot:head>
                        @foreach ($missions as $mission)
                            <x-table.row>
                                <x-table.td primary>
                                    <div class="min-w-48 max-w-80">
                                        <a href="{{ route('admin.missions.edit', $mission) }}" class="block truncate hover:text-leaf-700 hover:underline dark:hover:text-leaf-300">{{ $mission->title }}</a>
                                        <p class="mt-0.5 truncate text-xs font-normal text-ink-500 dark:text-ink-400">{{ $mission->objective }}</p>
                                    </div>
                                </x-table.td>
                                <x-table.td><x-status-badge :status="$mission->type" size="sm" /></x-table.td>
                                <x-table.td><x-status-badge :status="$mission->difficulty" size="sm" /></x-table.td>
                                <x-table.td align="right" numeric class="whitespace-nowrap">{{ number_format($mission->points_reward) }} XP</x-table.td>
                                <x-table.td align="right" numeric class="whitespace-nowrap">{{ number_format($mission->completed_count) }} / {{ number_format($mission->progress_count) }}</x-table.td>
                                <x-table.td>
                                    <x-badge :color="$mission->is_active ? 'leaf' : 'slate'" dot size="sm">{{ $mission->is_active ? 'Active' : 'Inactive' }}</x-badge>
                                </x-table.td>
                            </x-table.row>
                        @endforeach
                    </x-table>
                @endif
            </x-section>
        </div>

        <aside class="flex min-w-0 flex-col gap-6">
            <x-section title="Details" icon="info" color="slate" data-animate style="--stagger: 3">
                <x-definition-list>
                    <x-detail-row label="Subject" :value="$module->subject->name" />
                    <x-detail-row label="Grade level" :value="$module->grade_level->label()" />
                    <x-detail-row label="Quarter" :value="$module->quarter ? 'Quarter '.$module->quarter : null" />
                    <x-detail-row label="Game world" :value="$module->world_name" />
                    <x-detail-row label="Competency code">
                        @if ($module->competency_code)
                            <span class="font-mono">{{ $module->competency_code }}</span>
                        @endif
                    </x-detail-row>
                    <x-detail-row label="Learning competency" :value="$module->learning_competency" />
                    <x-detail-row label="Display order" :value="(string) $module->sort_order" />
                    <x-detail-row label="Last updated">
                        <time datetime="{{ $module->updated_at?->toIso8601String() }}" title="{{ $module->updated_at?->format('M j, Y g:i A') }}">{{ $module->updated_at?->diffForHumans() }}</time>
                    </x-detail-row>
                </x-definition-list>
                @if ($module->description)
                    <p class="mt-4 border-t border-ink-900/5 pt-4 text-sm whitespace-pre-line text-ink-600 dark:border-white/8 dark:text-ink-300">{{ $module->description }}</p>
                @endif
            </x-section>

            <x-section title="Lesson plan alignment" description="How teachers’ lesson plans use this module." icon="link" color="leaf" data-animate style="--stagger: 4">
                <dl class="grid grid-cols-2 gap-3">
                    @foreach ([
                        ['label' => 'Lesson plans using it', 'value' => $usage['lessonPlans'], 'icon' => 'notebook-pen'],
                        ['label' => 'Following the module', 'value' => $usage['followingModule'], 'icon' => 'map'],
                        ['label' => 'Same competency', 'value' => $usage['sameCompetency'], 'icon' => 'hash'],
                        ['label' => 'Quizzes linked', 'value' => $usage['quizzes'], 'icon' => 'clipboard-check'],
                    ] as $stat)
                        <div class="rounded-xl bg-ink-50/70 p-3 ring-1 ring-ink-900/5 ring-inset dark:bg-white/3 dark:ring-white/8">
                            <dt class="flex items-center gap-1.5 text-xs text-ink-500 dark:text-ink-400">
                                <x-icon :name="$stat['icon']" class="size-3.5" />
                                {{ $stat['label'] }}
                            </dt>
                            <dd class="mt-1 font-display text-2xl font-bold text-ink-950 dark:text-white">{{ number_format($stat['value']) }}</dd>
                        </div>
                    @endforeach
                </dl>

                @if ($lessonPlans->isEmpty())
                    <x-empty-state compact icon="notebook-pen" title="Not in any lesson plan yet"
                        description="Teachers align modules and activities from their lesson plans." class="mt-2" />
                @else
                    <ul class="mt-5 flex flex-col divide-y divide-ink-900/5 dark:divide-white/8" aria-label="Recent lesson plans using the module">
                        @foreach ($lessonPlans as $lessonPlan)
                            <li class="flex items-start justify-between gap-3 py-3 first:pt-0 last:pb-0">
                                <div class="min-w-0">
                                    <p class="truncate text-sm font-medium text-ink-900 dark:text-ink-50">{{ $lessonPlan->title }}</p>
                                    <p class="truncate text-xs text-ink-500 dark:text-ink-400">{{ $lessonPlan->teacher->name }} · {{ $lessonPlan->grade_level->label() }}</p>
                                </div>
                                <x-status-badge :status="$lessonPlan->status" size="sm" class="shrink-0" />
                            </li>
                        @endforeach
                    </ul>
                @endif

                <x-slot:footer>
                    <x-button variant="link" size="sm" icon-trailing="arrow-right"
                        :href="route('admin.content-alignment.index', array_filter(['subject' => $module->subject_id, 'grade' => $module->grade_level->value, 'search' => $module->competency_code]))">
                        Open lesson alignment
                    </x-button>
                </x-slot:footer>
            </x-section>
        </aside>
    </div>
</x-layouts.app>
