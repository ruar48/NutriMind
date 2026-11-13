<x-layouts.app :title="$student->name">
    <x-page-header
        :title="$student->name"
        description="Learner profile and game progress across every subject."
        :breadcrumbs="[['label' => 'Learners', 'url' => route('admin.students.index')], ['label' => $student->name]]"
    >
        <x-slot:meta>
            <x-people.account-status :user="$student" />
        </x-slot:meta>
        <x-slot:actions>
            <x-button icon="pencil" :href="route('admin.students.edit', $student)">Edit learner</x-button>
        </x-slot:actions>
    </x-page-header>

    <x-credentials-alert :credentials="session('credentials', [])" title="Save the learner’s sign-in details" />

    <x-learner.profile-header :summary="$summary" />
    <x-learner.progress-overview :summary="$summary" />

    <div class="grid grid-cols-1 gap-6 xl:grid-cols-3">
        <x-learner.weekly-trend :weeks="$summary->weeklyScores" class="xl:col-span-2" />
        <x-learner.rewards-shelf :rewards="$summary->rewards" />
    </div>

    <x-learner.subject-breakdown :subjects="$summary->subjectBreakdown" />

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
        <x-learner.recent-attempts :attempts="$summary->recentAttempts" />
        <x-learner.missions-list :missions="$summary->recentMissions" />
    </div>

    <div class="grid grid-cols-1 gap-6 xl:grid-cols-3">
        <div class="flex min-w-0 flex-col gap-6 xl:col-span-2">
            <x-section title="Classrooms" description="Every enrollment, including archived and past classrooms." icon="school" padding="none">
                <x-slot:actions>
                    <x-button variant="ghost" size="sm" icon="pencil" :href="route('admin.students.edit', $student)">Change classrooms</x-button>
                </x-slot:actions>
                @if ($student->enrolledClassrooms->isEmpty())
                    <x-empty-state illustration="classroom" title="Not enrolled yet" description="Enroll the learner in a classroom of the current school year.">
                        <x-slot:actions>
                            <x-button variant="soft" icon="school" :href="route('admin.students.edit', $student)">Choose classrooms</x-button>
                        </x-slot:actions>
                    </x-empty-state>
                @else
                    <x-table flush :caption="'Classrooms of '.$student->name">
                        <x-slot:head>
                            <x-table.th>Classroom</x-table.th>
                            <x-table.th>Teacher</x-table.th>
                            <x-table.th>School year</x-table.th>
                            <x-table.th>Enrolled</x-table.th>
                            <x-table.th>Status</x-table.th>
                        </x-slot:head>
                        @foreach ($student->enrolledClassrooms as $classroom)
                            <x-table.row data-enrollment-row="{{ $classroom->id }}">
                                <x-table.td primary>
                                    <div class="flex flex-col gap-1">
                                        <span class="max-w-56 truncate">{{ $classroom->name }}</span>
                                        <x-subject-chip :subject="$classroom->subject" size="sm" />
                                    </div>
                                </x-table.td>
                                <x-table.td>
                                    <a href="{{ route('admin.teachers.show', $classroom->teacher) }}" class="whitespace-nowrap hover:text-leaf-700 hover:underline dark:hover:text-leaf-300">{{ $classroom->teacher->name }}</a>
                                </x-table.td>
                                <x-table.td class="whitespace-nowrap">{{ $classroom->schoolYear->name }}</x-table.td>
                                <x-table.td class="whitespace-nowrap tabular-nums">{{ $classroom->pivot->enrolled_at?->format('M j, Y') ?? '—' }}</x-table.td>
                                <x-table.td>
                                    @if ($classroom->isArchived())
                                        <x-badge color="slate" icon="archive" size="sm">Archived</x-badge>
                                    @else
                                        <x-badge color="leaf" dot size="sm">Active</x-badge>
                                    @endif
                                </x-table.td>
                            </x-table.row>
                        @endforeach
                    </x-table>
                @endif
            </x-section>

            <x-section title="Parents & guardians" description="They follow this learner in the Parent Portal." icon="house-heart" color="carrot">
                <x-slot:actions>
                    <x-button variant="ghost" size="sm" icon="link" :href="route('admin.parents.index')">Link from a parent</x-button>
                </x-slot:actions>
                @if ($student->guardians->isEmpty())
                    <x-empty-state compact icon="house-heart" title="No parent or guardian linked"
                        description="Open the parent’s profile and link this learner, or create the parent account first.">
                        <x-slot:actions>
                            <x-button size="sm" variant="soft" icon="user-plus" :href="route('admin.users.create', ['role' => \App\Enums\UserRole::Parent->value])">Add parent</x-button>
                            <x-button size="sm" variant="secondary" icon="search" :href="route('admin.parents.index')">Find a parent</x-button>
                        </x-slot:actions>
                    </x-empty-state>
                @else
                    <ul class="flex flex-col divide-y divide-ink-900/5 dark:divide-white/8" role="list">
                        @foreach ($student->guardians as $guardian)
                            <li class="flex flex-col gap-3 py-3 first:pt-0 last:pb-0 sm:flex-row sm:items-center sm:justify-between" data-guardian-row="{{ $guardian->id }}">
                                <div class="flex min-w-0 items-center gap-3">
                                    <x-avatar :name="$guardian->name" size="md" />
                                    <div class="min-w-0">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <a href="{{ route('admin.parents.show', $guardian) }}" class="truncate font-semibold text-ink-900 hover:text-leaf-700 hover:underline dark:text-ink-50 dark:hover:text-leaf-300">{{ $guardian->name }}</a>
                                            <x-badge color="carrot" size="sm">{{ $guardian->pivot->relationship->label() }}</x-badge>
                                            @unless ($guardian->is_active)
                                                <x-people.account-status :user="$guardian" size="sm" />
                                            @endunless
                                        </div>
                                        <p class="truncate text-xs text-ink-500 dark:text-ink-400">{{ collect([$guardian->email, $guardian->phone])->filter()->join(' · ') }}</p>
                                    </div>
                                </div>
                                <x-confirm-dialog
                                    :action="route('admin.parents.children.destroy', [$guardian, $student])"
                                    :title="'Unlink '.$guardian->name.'?'"
                                    :message="$guardian->name.' will no longer see '.$student->name.' in the Parent Portal. Both accounts stay.'"
                                    confirm="Unlink"
                                    button="Unlink"
                                    button-variant="ghost"
                                    button-icon="user-x"
                                    icon="user-x"
                                >
                                    <x-slot:fields>
                                        <input type="hidden" name="return_to" value="student">
                                    </x-slot:fields>
                                </x-confirm-dialog>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-section>
        </div>

        <aside class="flex min-w-0 flex-col gap-6">
            <x-people.account-panel :user="$student" />
        </aside>
    </div>
</x-layouts.app>
