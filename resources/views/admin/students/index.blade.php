@php
    $exportQuery = array_filter(
        request()->only(['search', 'grade', 'classroom', 'status', 'guardian', 'sort', 'direction']),
        fn ($value) => is_string($value) && $value !== '',
    );
@endphp

<x-layouts.app title="Learners">
    <x-page-header
        title="Learners"
        description="Learner accounts that play NutriMind, with their classrooms, guardians and game progress."
        :breadcrumbs="[['label' => 'Dashboard', 'url' => route('admin.dashboard')], ['label' => 'Learners']]"
    >
        <x-slot:actions>
            <x-button variant="secondary" icon="download" :href="route('admin.students.export', $exportQuery)">Export CSV</x-button>
            <x-button variant="secondary" icon="file-up" :href="route('admin.students.import.create')">Import CSV</x-button>
            <x-button icon="user-plus" :href="route('admin.students.create')">Add learner</x-button>
        </x-slot:actions>
    </x-page-header>

    <x-credentials-alert :credentials="session('credentials', [])" />

    <x-filter-bar
        :action="route('admin.students.index')"
        search-label="Search learners"
        search-placeholder="Search name, username or LRN"
        :reset-url="route('admin.students.index')"
    >
        <x-form.select name="grade" label="Grade" hide-label placeholder="Any grade"
            :options="\App\Enums\GradeLevel::options()" :value="request('grade')" :old="false" class="sm:w-36" />
        <x-form.select name="classroom" label="Classroom" hide-label placeholder="Any classroom"
            :options="$classroomOptions" :value="request('classroom')" :old="false" class="sm:w-60" />
        <x-form.select name="guardian" label="Guardian" hide-label placeholder="Any guardian status"
            :options="['yes' => 'Guardian linked', 'no' => 'No guardian linked']" :value="request('guardian')" :old="false" class="sm:w-52" />
        <x-form.select name="status" label="Status" hide-label placeholder="Any status"
            :options="['active' => 'Active', 'inactive' => 'Inactive']" :value="request('status')" :old="false" class="sm:w-40" />
    </x-filter-bar>

    @if ($students->isEmpty())
        @if ($isFiltered)
            <x-empty-state variant="card" illustration="search" title="No learners match your filters"
                description="Try another name, LRN or classroom, or clear the filters.">
                <x-slot:actions>
                    <x-button variant="secondary" icon="rotate-ccw" :href="route('admin.students.index')">Clear filters</x-button>
                </x-slot:actions>
            </x-empty-state>
        @else
            <x-empty-state variant="card" illustration="classroom" title="No learners yet"
                description="Add learners one by one, or import a whole class list from a CSV file.">
                <x-slot:actions>
                    <x-button icon="user-plus" :href="route('admin.students.create')">Add learner</x-button>
                    <x-button variant="secondary" icon="file-up" :href="route('admin.students.import.create')">Import CSV</x-button>
                </x-slot:actions>
            </x-empty-state>
        @endif
    @else
        <x-table caption="Learners">
            <x-slot:head>
                <x-table.th sort="name">Learner</x-table.th>
                <x-table.th>LRN</x-table.th>
                <x-table.th>Grade</x-table.th>
                <x-table.th>Classrooms</x-table.th>
                <x-table.th align="right">Guardians</x-table.th>
                <x-table.th sort="points" default-direction="desc">Level &amp; XP</x-table.th>
                <x-table.th sort="last_active" default-direction="desc">Last active</x-table.th>
                <x-table.th>Status</x-table.th>
                <x-table.th align="right"><span class="sr-only">Actions</span></x-table.th>
            </x-slot:head>

            @foreach ($students as $student)
                <x-table.row data-student-row="{{ $student->id }}">
                    <x-table.td primary>
                        <div class="flex items-center gap-3">
                            <x-avatar :name="$student->name" size="sm" />
                            <div class="min-w-0">
                                <a href="{{ route('admin.students.show', $student) }}" class="block max-w-52 truncate hover:text-leaf-700 hover:underline dark:hover:text-leaf-300">{{ $student->name }}</a>
                                <p class="max-w-52 truncate font-mono text-xs font-normal text-ink-500 dark:text-ink-400">{{ $student->username }}</p>
                            </div>
                        </div>
                    </x-table.td>
                    <x-table.td class="font-mono whitespace-nowrap tabular-nums">{{ $student->studentProfile?->lrn ?? '—' }}</x-table.td>
                    <x-table.td>
                        @if ($student->studentProfile)
                            <x-status-badge :status="$student->studentProfile->grade_level" size="sm" />
                        @else
                            <span class="text-ink-400 dark:text-ink-500">—</span>
                        @endif
                    </x-table.td>
                    <x-table.td><x-people.classroom-chips :classrooms="$student->enrolledClassrooms" /></x-table.td>
                    <x-table.td align="right" numeric data-guardians-count>
                        @if ($student->guardians_count > 0)
                            {{ $student->guardians_count }}
                        @else
                            <span class="text-ink-400 dark:text-ink-500">None</span>
                        @endif
                    </x-table.td>
                    <x-table.td>
                        <div class="flex items-center gap-2.5">
                            <x-level-badge :level="$levels[$student->id]" size="sm" />
                            <span class="text-xs whitespace-nowrap tabular-nums">{{ number_format($student->studentProfile?->total_points ?? 0) }} XP</span>
                        </div>
                    </x-table.td>
                    <x-table.td><x-people.relative-time :at="$student->studentProfile?->last_active_at" empty="Not played yet" /></x-table.td>
                    <x-table.td><x-people.account-status :user="$student" size="sm" /></x-table.td>
                    <x-table.td align="right"><x-people.account-menu :user="$student" /></x-table.td>
                </x-table.row>
            @endforeach

            @if ($students->hasPages())
                <x-slot:footer>{{ $students->links() }}</x-slot:footer>
            @endif
        </x-table>
    @endif
</x-layouts.app>
