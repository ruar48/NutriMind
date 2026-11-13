@php
    $alignedTotal = count($aligned['activities']) + count($aligned['quizzes']) + count($aligned['missions']);
    $activityCount = $activityGroups->sum(fn ($group) => $group['activities']->count());
    $scope = $lessonPlan->subject->name.' · '.$lessonPlan->grade_level->label();
    $alignmentErrors = collect(['activities', 'activities.*', 'quizzes', 'quizzes.*', 'missions', 'missions.*'])
        ->map(fn ($key) => $errors->first($key))
        ->filter()
        ->unique()
        ->values();
@endphp

<x-layouts.app :title="$lessonPlan->title">
    <x-page-header
        eyebrow="Lesson plan"
        :title="$lessonPlan->title"
        :description="collect([
            $scope,
            $lessonPlan->academicTerm ? $lessonPlan->academicTerm->name.' (SY '.$lessonPlan->academicTerm->schoolYear->name.')' : null,
            $lessonPlan->week_number ? 'Week '.$lessonPlan->week_number : null,
        ])->filter()->implode(' · ')"
        :breadcrumbs="[['label' => 'Lesson plans', 'url' => route('teacher.lesson-plans.index')], ['label' => $lessonPlan->title]]"
    >
        <x-slot:leading>
            <span class="grid size-14 place-items-center rounded-2xl bg-leaf-600 text-white shadow-card dark:bg-leaf-500">
                <x-icon name="notebook-pen" class="size-7" />
            </span>
        </x-slot:leading>
        <x-slot:meta>
            <x-status-badge :status="$lessonPlan->status" />
            <x-subject-chip :subject="$lessonPlan->subject" />
            @if ($alignedTotal > 0)
                <x-badge color="leaf" icon="link" data-alignment="aligned">Aligned</x-badge>
            @else
                <x-badge color="sun" icon="circle-dashed" data-alignment="unaligned">Not aligned yet</x-badge>
            @endif
            @if ($lessonPlan->competency_code)
                <x-badge color="slate" icon="hash"><span class="font-mono">{{ $lessonPlan->competency_code }}</span></x-badge>
            @endif
        </x-slot:meta>
        <x-slot:actions>
            <x-confirm-dialog
                :action="route('teacher.lesson-plans.destroy', $lessonPlan)"
                :title="'Delete “'.$lessonPlan->title.'”?'"
                message="The lesson plan and its alignments are removed. The game content itself is not changed."
                confirm="Delete lesson plan"
                button="Delete"
                button-variant="ghost"
                button-size="md"
            />
            <x-button variant="secondary" icon="pencil" :href="route('teacher.lesson-plans.edit', $lessonPlan)">Edit plan</x-button>
        </x-slot:actions>
    </x-page-header>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-stat-card label="Activities aligned" :value="number_format(count($aligned['activities']))" icon="puzzle" color="grape" :hint="$activityCount.' available for '.$scope" data-animate style="--stagger: 1" />
        <x-stat-card label="Quizzes aligned" :value="number_format(count($aligned['quizzes']))" icon="clipboard-check" color="sky" :hint="$quizzes->count().' of your quizzes fit'" data-animate style="--stagger: 2" />
        <x-stat-card label="Missions aligned" :value="number_format(count($aligned['missions']))" icon="flag" color="carrot" :hint="$missions->count().' missions fit'" data-animate style="--stagger: 3" />
        @if ($coverage)
            <x-stat-card label="Game coverage" :value="$coverage->status->label()" :icon="$coverage->status->icon()" :color="$coverage->status->color()"
                :hint="$coverage->modules->isEmpty() ? 'No game module teaches '.$coverage->code.' yet' : trans_choice('{1} :count module teaches this competency|[2,*] :count modules teach this competency', $coverage->modules->count())"
                data-coverage-status="{{ $coverage->status->value }}" data-animate style="--stagger: 4" />
        @else
            <x-stat-card label="Game coverage" value="No code" icon="hash" color="slate" hint="Add a competency code to check the game content" data-coverage-status="none" data-animate style="--stagger: 4" />
        @endif
    </div>

    <div class="grid grid-cols-1 gap-6 xl:grid-cols-3">
        <form method="POST" action="{{ route('teacher.lesson-plans.alignment.update', $lessonPlan) }}" class="flex min-w-0 flex-col gap-6 xl:col-span-2" data-submit-once data-content-alignment-editor>
            @csrf
            @method('PUT')

            @if ($alignmentErrors->isNotEmpty())
                <x-alert variant="danger" title="The alignment was not saved">
                    <ul class="list-disc pl-5">
                        @foreach ($alignmentErrors as $message)
                            <li>{{ $message }}</li>
                        @endforeach
                    </ul>
                </x-alert>
            @endif

            <x-section title="Game activities" :description="'Published activities of '.$scope.' modules. Suggested modules match this plan’s module or competency code.'" icon="puzzle" color="grape" data-animate style="--stagger: 5">
                @if ($activityGroups->isEmpty())
                    <x-empty-state compact icon="puzzle" :title="'No game activities for '.$scope.' yet'"
                        description="Your administrator publishes the game modules. Their activities appear here once a module for this subject and grade is published." />
                @else
                    <div class="flex flex-col gap-4">
                        @foreach ($activityGroups as $group)
                            @php
                                $module = $group['module'];
                                $checkGroup = 'activities-module-'.$module->id;
                            @endphp
                            <fieldset @class([
                                'min-w-0 rounded-2xl p-3 ring-1 ring-inset sm:p-4',
                                'bg-sun-50/50 ring-sun-600/20 dark:bg-sun-400/5 dark:ring-sun-400/20' => $group['suggested'],
                                'bg-ink-50/40 ring-ink-900/8 dark:bg-white/2 dark:ring-white/8' => ! $group['suggested'],
                            ]) data-module-group="{{ $module->id }}">
                                <legend class="sr-only">{{ $module->title }}</legend>
                                <div class="flex flex-wrap items-start justify-between gap-3 px-1 pb-2">
                                    <div class="min-w-0">
                                        <p class="flex flex-wrap items-center gap-2 font-display text-sm font-semibold text-ink-950 dark:text-white">
                                            <x-icon name="map" class="size-4 text-grape-600 dark:text-grape-400" />
                                            {{ $module->title }}
                                            @if ($group['suggested'])
                                                <x-badge color="sun" icon="sparkles" size="sm">Suggested</x-badge>
                                            @endif
                                            @unless ($module->is_published)
                                                <x-badge color="slate" icon="eye-off" size="sm">Unpublished</x-badge>
                                            @endunless
                                        </p>
                                        <p class="mt-0.5 text-xs text-ink-500 dark:text-ink-400">
                                            {{ collect([$module->world_name, $module->quarter ? 'Quarter '.$module->quarter : null, $module->competency_code])->filter()->implode(' · ') }}
                                        </p>
                                    </div>
                                    <div class="flex items-center gap-4 text-xs text-ink-500 dark:text-ink-400" data-js-only>
                                        <span><span data-check-count="{{ $checkGroup }}">{{ $group['activities']->whereIn('id', $aligned['activities'])->count() }}</span> of {{ $group['activities']->count() }} selected</span>
                                        <x-form.checkbox name="select_all_activities[]" :value="$module->id" :old="false" :data-check-all="$checkGroup">
                                            <span aria-hidden="true">All</span>
                                            <span class="sr-only">Select every activity of {{ $module->title }}</span>
                                        </x-form.checkbox>
                                    </div>
                                </div>
                                <div class="flex flex-col gap-1">
                                    @foreach ($group['activities'] as $activity)
                                        <x-content.alignment-option
                                            name="activities[]"
                                            :value="$activity->id"
                                            :checked="in_array($activity->id, $aligned['activities'], true)"
                                            :title="$activity->title"
                                            :meta="collect([$activity->type->label(), $activity->estimated_minutes ? $activity->estimated_minutes.' min' : null, number_format($activity->points).' XP'])->filter()->implode(' · ')"
                                            :group="$checkGroup"
                                        >
                                            @unless ($activity->is_published)
                                                <x-slot:badges><x-badge color="slate" icon="eye-off" size="sm">Unpublished</x-badge></x-slot:badges>
                                            @endunless
                                        </x-content.alignment-option>
                                    @endforeach
                                </div>
                            </fieldset>
                        @endforeach
                    </div>
                @endif
            </x-section>

            <x-section title="Your quizzes" :description="'Your quizzes for '.$scope.', newest first.'" icon="clipboard-check" color="sky" data-animate style="--stagger: 6">
                @if ($quizzes->isEmpty())
                    <x-empty-state compact icon="clipboard-check" :title="'No quizzes for '.$scope.' yet'" description="Write a quiz for this subject and grade, then align it here.">
                        <x-slot:actions>
                            <x-button variant="soft" size="sm" icon="plus" :href="route('teacher.quizzes.create')">New quiz</x-button>
                        </x-slot:actions>
                    </x-empty-state>
                @else
                    <fieldset class="flex min-w-0 flex-col gap-1">
                        <legend class="sr-only">Quizzes</legend>
                        @foreach ($quizzes as $quiz)
                            <x-content.alignment-option
                                name="quizzes[]"
                                :value="$quiz->id"
                                :checked="in_array($quiz->id, $aligned['quizzes'], true)"
                                :title="$quiz->title"
                                :meta="trans_choice('{0} No questions yet|{1} :count question|[2,*] :count questions', $quiz->questions_count).($quiz->learning_module_id !== null && (int) $quiz->learning_module_id === (int) $lessonPlan->learning_module_id ? ' · Same module as this plan' : '')"
                            >
                                <x-slot:badges><x-status-badge :status="$quiz->status" size="sm" /></x-slot:badges>
                            </x-content.alignment-option>
                        @endforeach
                    </fieldset>
                @endif
            </x-section>

            <x-section title="Missions" :description="'Active missions of '.$lessonPlan->subject->name.' for '.$lessonPlan->grade_level->label().' or both grades.'" icon="flag" color="carrot" data-animate style="--stagger: 7">
                @if ($missions->isEmpty())
                    <x-empty-state compact icon="flag" :title="'No missions for '.$scope.' yet'" description="Your administrator creates the game’s missions. They appear here once one fits this subject and grade." />
                @else
                    <fieldset class="flex min-w-0 flex-col gap-1">
                        <legend class="sr-only">Missions</legend>
                        @foreach ($missions as $mission)
                            <x-content.alignment-option
                                name="missions[]"
                                :value="$mission->id"
                                :checked="in_array($mission->id, $aligned['missions'], true)"
                                :title="$mission->title"
                                :meta="collect([$mission->type->label(), $mission->difficulty->label(), number_format($mission->points_reward).' XP', $mission->grade_level?->label() ?? 'Both grades', $mission->module?->title])->filter()->implode(' · ')"
                                :suggested="in_array($mission->id, $suggestedMissionIds, true)"
                            >
                                @unless ($mission->is_active)
                                    <x-slot:badges><x-badge color="slate" size="sm">Inactive</x-badge></x-slot:badges>
                                @endunless
                            </x-content.alignment-option>
                        @endforeach
                    </fieldset>
                @endif
            </x-section>

            <x-form.actions submit="Save alignment" submit-icon="link">
                <x-icon name="info" class="size-4" />
                Saving replaces this plan’s aligned activities, quizzes and missions with your selection.
            </x-form.actions>
        </form>

        <aside class="flex min-w-0 flex-col gap-6">
            <x-section title="Lesson details" icon="info" color="slate" data-animate style="--stagger: 5">
                <x-definition-list>
                    <x-detail-row label="Competency code">
                        @if ($lessonPlan->competency_code)
                            <span class="font-mono">{{ $lessonPlan->competency_code }}</span>
                        @endif
                    </x-detail-row>
                    <x-detail-row label="Learning competency" :value="$lessonPlan->learning_competency" />
                    <x-detail-row label="Game module" :value="$lessonPlan->module?->title" />
                    <x-detail-row label="Quarter" :value="$lessonPlan->academicTerm?->name" />
                    <x-detail-row label="Week" :value="$lessonPlan->week_number ? 'Week '.$lessonPlan->week_number : null" />
                    <x-detail-row label="Dates">
                        @if ($lessonPlan->starts_on || $lessonPlan->ends_on)
                            {{ $lessonPlan->starts_on?->format('M j, Y') ?? '…' }} – {{ $lessonPlan->ends_on?->format('M j, Y') ?? '…' }}
                        @endif
                    </x-detail-row>
                    <x-detail-row label="Last updated">
                        <time datetime="{{ $lessonPlan->updated_at?->toIso8601String() }}" title="{{ $lessonPlan->updated_at?->format('M j, Y g:i A') }}">{{ $lessonPlan->updated_at?->diffForHumans() }}</time>
                    </x-detail-row>
                </x-definition-list>
            </x-section>

            <x-section title="Objectives" icon="target" color="leaf" data-animate style="--stagger: 6">
                @if ($lessonPlan->objectives)
                    <p class="text-sm whitespace-pre-line text-ink-700 dark:text-ink-200">{{ $lessonPlan->objectives }}</p>
                @else
                    <p class="text-sm text-ink-500 dark:text-ink-400">No objectives written yet.</p>
                @endif
            </x-section>

            @if ($coverage && $coverage->modules->isNotEmpty())
                <x-section title="Game modules for this competency" icon="map" color="grape" data-animate style="--stagger: 7">
                    <ul class="flex flex-col divide-y divide-ink-900/5 dark:divide-white/8">
                        @foreach ($coverage->modules as $module)
                            <li class="flex items-center justify-between gap-3 py-2.5 first:pt-0 last:pb-0">
                                <span class="min-w-0 truncate text-sm font-medium text-ink-900 dark:text-ink-50">{{ $module->title }}</span>
                                <x-badge :color="$module->is_published ? 'leaf' : 'slate'" size="sm" class="shrink-0">{{ trans_choice('{0} No activities|{1} :count activity|[2,*] :count activities', $module->published_activities_count) }}</x-badge>
                            </li>
                        @endforeach
                    </ul>
                </x-section>
            @endif

            <x-section title="Notes" icon="notebook-pen" color="sky" data-animate style="--stagger: 8">
                @if ($lessonPlan->notes)
                    <p class="text-sm whitespace-pre-line text-ink-700 dark:text-ink-200">{{ $lessonPlan->notes }}</p>
                @else
                    <p class="text-sm text-ink-500 dark:text-ink-400">No notes.</p>
                @endif
            </x-section>
        </aside>
    </div>
</x-layouts.app>
