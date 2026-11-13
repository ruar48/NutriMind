@php
    $linkErrors = $errors->getBag('linkLearner');
    $failedStudentId = $linkErrors->isNotEmpty() ? (int) old('student_id') : null;
@endphp

<x-layouts.app :title="$guardian->name">
    <x-page-header
        :title="$guardian->name"
        :description="collect([$guardian->email, $guardian->phone])->filter()->join(' · ')"
        :breadcrumbs="[['label' => 'Parents & guardians', 'url' => route('admin.parents.index')], ['label' => $guardian->name]]"
    >
        <x-slot:leading>
            <x-avatar :name="$guardian->name" size="xl" class="shadow-card ring-4 ring-white dark:ring-ink-900" />
        </x-slot:leading>
        <x-slot:meta>
            <x-status-badge :status="$guardian->role" />
            <x-people.account-status :user="$guardian" />
            <span class="inline-flex items-center gap-1.5 text-xs text-ink-600 dark:text-ink-300">
                <x-icon name="clock" class="size-3.5" />
                Last sign-in: <x-people.relative-time :at="$guardian->last_login_at" />
            </span>
        </x-slot:meta>
        <x-slot:actions>
            <x-button icon="pencil" :href="route('admin.users.edit', $guardian)">Edit parent</x-button>
        </x-slot:actions>
    </x-page-header>

    <x-credentials-alert :credentials="session('credentials', [])" />

    <div class="grid grid-cols-1 gap-6 xl:grid-cols-3">
        <div class="flex min-w-0 flex-col gap-6 xl:col-span-2">
            <x-section
                title="Linked learners"
                :description="trans_choice(':count learner this parent follows in the Parent Portal|:count learners this parent follows in the Parent Portal', $guardian->linkedStudents->count())"
                icon="users-round"
                color="carrot"
            >
                @if ($guardian->linkedStudents->isEmpty())
                    <x-empty-state compact illustration="sprout" title="No learners linked yet"
                        description="Search for the parent’s child below and link them with the right relationship." />
                @else
                    <ul class="flex flex-col divide-y divide-ink-900/5 dark:divide-white/8" role="list">
                        @foreach ($guardian->linkedStudents as $learner)
                            <li class="flex flex-col gap-3 py-3 first:pt-0 last:pb-0 sm:flex-row sm:items-center sm:justify-between" data-linked-learner="{{ $learner->id }}">
                                <div class="flex min-w-0 items-start gap-3">
                                    <x-avatar :name="$learner->name" size="md" />
                                    <div class="flex min-w-0 flex-col gap-1.5">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <a href="{{ route('admin.students.show', $learner) }}" class="truncate font-semibold text-ink-900 hover:text-leaf-700 hover:underline dark:text-ink-50 dark:hover:text-leaf-300">{{ $learner->name }}</a>
                                            <x-badge color="carrot" size="sm">{{ $learner->pivot->relationship->label() }}</x-badge>
                                            @if ($learner->studentProfile)
                                                <x-status-badge :status="$learner->studentProfile->grade_level" size="sm" />
                                            @endif
                                        </div>
                                        <p class="text-xs text-ink-500 tabular-nums dark:text-ink-400">
                                            LRN {{ $learner->studentProfile?->lrn ?? '—' }} · <span class="font-mono">{{ $learner->username }}</span>
                                        </p>
                                        <x-people.classroom-chips :classrooms="$learner->enrolledClassrooms" :max="3" empty="Not enrolled in a classroom" class="text-xs" />
                                    </div>
                                </div>
                                <x-confirm-dialog
                                    :action="route('admin.parents.children.destroy', [$guardian, $learner])"
                                    :title="'Unlink '.$learner->name.'?'"
                                    :message="$guardian->name.' will no longer see '.$learner->name.' in the Parent Portal. Both accounts stay.'"
                                    confirm="Unlink"
                                    button="Unlink"
                                    button-variant="ghost"
                                    button-icon="user-x"
                                    icon="user-x"
                                />
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-section>

            <x-section title="Link a learner" description="Search by name, username or LRN, choose the relationship, then link." icon="link" id="link-learner">
                <div class="flex flex-col gap-5">
                    @if ($linkErrors->isNotEmpty())
                        <x-alert variant="danger" title="The learner was not linked">
                            <ul class="list-inside list-disc">
                                @foreach ($linkErrors->all() as $message)
                                    <li>{{ $message }}</li>
                                @endforeach
                            </ul>
                        </x-alert>
                    @endif

                    <x-filter-bar
                        :action="route('admin.parents.show', $guardian).'#link-learner'"
                        search-name="learner_search"
                        search-label="Search learners to link"
                        search-placeholder="Name, username or LRN"
                        :reset-url="route('admin.parents.show', $guardian).'#link-learner'"
                        :preserve="[]"
                    />

                    @if ($learnerSearch === null)
                        <p class="text-sm text-ink-500 dark:text-ink-400">Start typing to find the learner.</p>
                    @elseif ($results->isEmpty())
                        <x-empty-state compact illustration="search" :title="'No learners match “'.$learnerSearch.'”'"
                            description="Check the spelling, or search by LRN or username." />
                    @else
                        <ul class="flex flex-col divide-y divide-ink-900/5 rounded-xl ring-1 ring-ink-900/5 ring-inset dark:divide-white/8 dark:ring-white/8" role="list" data-learner-results="{{ $results->count() }}">
                            @foreach ($results as $learner)
                                @php $isLinked = in_array($learner->id, $linkedIds, true); @endphp
                                <li @class([
                                    'flex flex-col gap-3 p-4 lg:flex-row lg:items-center lg:justify-between',
                                    'bg-berry-50/60 dark:bg-berry-400/5' => $failedStudentId === $learner->id,
                                ]) data-learner-result="{{ $learner->id }}">
                                    <div class="flex min-w-0 items-center gap-3">
                                        <x-avatar :name="$learner->name" size="sm" />
                                        <div class="min-w-0">
                                            <p class="truncate text-sm font-semibold text-ink-900 dark:text-ink-50">{{ $learner->name }}</p>
                                            <p class="truncate text-xs text-ink-500 tabular-nums dark:text-ink-400">
                                                {{ $learner->studentProfile?->grade_level?->label() ?? 'No grade' }} · LRN {{ $learner->studentProfile?->lrn ?? '—' }} · <span class="font-mono">{{ $learner->username }}</span>
                                            </p>
                                            @if ($learner->enrolledClassrooms->isNotEmpty())
                                                <p class="truncate text-xs text-ink-500 dark:text-ink-400">{{ $learner->enrolledClassrooms->pluck('name')->join(', ') }}</p>
                                            @endif
                                        </div>
                                    </div>
                                    @if ($isLinked)
                                        <x-badge color="leaf" icon="check" class="self-start lg:self-center">Already linked</x-badge>
                                    @else
                                        <form method="POST" action="{{ route('admin.parents.children.store', $guardian) }}" class="flex flex-col gap-2 sm:flex-row sm:items-center" data-submit-once>
                                            @csrf
                                            <input type="hidden" name="student_id" value="{{ $learner->id }}">
                                            <x-form.select
                                                name="relationship"
                                                :id="'relationship-'.$learner->id"
                                                :label="'Relationship to '.$learner->name"
                                                hide-label
                                                :options="$relationshipOptions"
                                                placeholder="Relationship…"
                                                :value="$failedStudentId === $learner->id ? old('relationship') : null"
                                                :old="false"
                                                required
                                                class="sm:w-44"
                                            />
                                            <x-button type="submit" icon="link">Link learner</x-button>
                                        </form>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                        @if ($hasMoreResults)
                            <p class="text-xs text-ink-500 dark:text-ink-400">Showing the first {{ $results->count() }} matches. Type more of the name, or the LRN, to narrow the list.</p>
                        @endif
                    @endif
                </div>
            </x-section>
        </div>

        <aside class="flex min-w-0 flex-col gap-6">
            <x-people.account-panel :user="$guardian" />
        </aside>
    </div>
</x-layouts.app>
