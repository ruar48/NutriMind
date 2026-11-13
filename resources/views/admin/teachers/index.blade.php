<x-layouts.app title="Teachers">
    <x-page-header
        title="Teachers"
        description="Teacher accounts with their subjects, classrooms, learners and quizzes."
        :breadcrumbs="[['label' => 'Dashboard', 'url' => route('admin.dashboard')], ['label' => 'Teachers']]"
    >
        <x-slot:actions>
            <x-button variant="secondary" icon="user-check" :href="route('admin.subject-assignments.index')">Manage assignments</x-button>
            <x-button icon="user-plus" :href="route('admin.users.create', ['role' => \App\Enums\UserRole::Teacher->value])">Add teacher</x-button>
        </x-slot:actions>
    </x-page-header>

    <x-credentials-alert :credentials="session('credentials', [])" />

    @if ($teachersWithoutSubjects > 0)
        <x-alert variant="warning" :title="trans_choice(':count active teacher has no subject yet|:count active teachers have no subject yet', $teachersWithoutSubjects)" data-teachers-without-subjects="{{ $teachersWithoutSubjects }}">
            <p>Teachers without an assigned subject cannot create classrooms or quizzes.</p>
            <div class="mt-3">
                <x-button size="sm" variant="secondary" icon="user-check" :href="route('admin.subject-assignments.index')">Assign subjects</x-button>
            </div>
        </x-alert>
    @endif

    <x-filter-bar
        :action="route('admin.teachers.index')"
        search-label="Search teachers"
        search-placeholder="Search name, email or username"
        :reset-url="route('admin.teachers.index')"
    >
        <x-form.select name="subject" label="Subject" hide-label placeholder="Any subject"
            :options="$subjectOptions" :value="request('subject')" :old="false" class="sm:w-52" />
        <x-form.select name="status" label="Status" hide-label placeholder="Any status"
            :options="['active' => 'Active', 'inactive' => 'Inactive']" :value="request('status')" :old="false" class="sm:w-44" />
    </x-filter-bar>

    @if ($teachers->isEmpty())
        @if ($isFiltered)
            <x-empty-state variant="card" illustration="search" title="No teachers match your filters"
                description="Try a different name or subject, or clear the filters.">
                <x-slot:actions>
                    <x-button variant="secondary" icon="rotate-ccw" :href="route('admin.teachers.index')">Clear filters</x-button>
                </x-slot:actions>
            </x-empty-state>
        @else
            <x-empty-state variant="card" illustration="classroom" title="No teachers yet"
                description="Add teacher accounts, then assign each one the subjects they teach.">
                <x-slot:actions>
                    <x-button icon="user-plus" :href="route('admin.users.create', ['role' => \App\Enums\UserRole::Teacher->value])">Add teacher</x-button>
                </x-slot:actions>
            </x-empty-state>
        @endif
    @else
        <x-table caption="Teachers">
            <x-slot:head>
                <x-table.th sort="name">Teacher</x-table.th>
                <x-table.th>Subjects</x-table.th>
                <x-table.th align="right">Active classrooms</x-table.th>
                <x-table.th align="right">Learners</x-table.th>
                <x-table.th align="right">Published quizzes</x-table.th>
                <x-table.th sort="last_login_at" default-direction="desc">Last sign-in</x-table.th>
                <x-table.th>Status</x-table.th>
                <x-table.th align="right"><span class="sr-only">Actions</span></x-table.th>
            </x-slot:head>

            @foreach ($teachers as $teacher)
                <x-table.row data-teacher-row="{{ $teacher->id }}">
                    <x-table.td primary>
                        <div class="flex items-center gap-3">
                            <x-avatar :name="$teacher->name" size="sm" />
                            <div class="min-w-0">
                                <a href="{{ route('admin.teachers.show', $teacher) }}" class="block max-w-56 truncate hover:text-leaf-700 hover:underline dark:hover:text-leaf-300">{{ $teacher->name }}</a>
                                <p class="max-w-56 truncate text-xs font-normal text-ink-500 dark:text-ink-400">{{ $teacher->email }}</p>
                            </div>
                        </div>
                    </x-table.td>
                    <x-table.td>
                        @if ($teacher->assignedSubjects->isEmpty())
                            <x-badge color="sun" icon="triangle-alert" size="sm">No subjects</x-badge>
                        @else
                            <div class="flex max-w-xs flex-wrap gap-1.5">
                                @foreach ($teacher->assignedSubjects as $subject)
                                    <x-subject-chip :subject="$subject" size="sm" />
                                @endforeach
                            </div>
                        @endif
                    </x-table.td>
                    <x-table.td align="right" numeric data-active-classrooms>{{ number_format($teacher->active_classrooms_count) }}</x-table.td>
                    <x-table.td align="right" numeric data-learners>{{ number_format((int) $teacher->learners_count) }}</x-table.td>
                    <x-table.td align="right" numeric data-published-quizzes>{{ number_format($teacher->published_quizzes_count) }}</x-table.td>
                    <x-table.td><x-people.relative-time :at="$teacher->last_login_at" /></x-table.td>
                    <x-table.td><x-people.account-status :user="$teacher" size="sm" /></x-table.td>
                    <x-table.td align="right"><x-people.account-menu :user="$teacher" /></x-table.td>
                </x-table.row>
            @endforeach

            @if ($teachers->hasPages())
                <x-slot:footer>{{ $teachers->links() }}</x-slot:footer>
            @endif
        </x-table>
    @endif
</x-layouts.app>
