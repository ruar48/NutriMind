<x-layouts.app title="Subjects" description="Subjects teachers are assigned to and game content belongs to.">
    <x-page-header
        eyebrow="Academic setup"
        title="Subjects"
        description="Each subject has a color and icon used on classrooms, quizzes and game content. Assign subjects to teachers under Subject Assignment."
        :breadcrumbs="[['label' => 'Dashboard', 'url' => route('admin.dashboard')], ['label' => 'Subjects']]"
    >
        <x-slot:meta>
            <x-badge color="slate" icon="book-open">{{ trans_choice(':count subject|:count subjects', $totalCount) }}</x-badge>
            <x-badge color="leaf" icon="circle-check">{{ number_format($activeCount) }} active</x-badge>
        </x-slot:meta>
        <x-slot:actions>
            <x-button variant="secondary" icon="user-check" :href="route('admin.subject-assignments.index')">Assign teachers</x-button>
            <x-button icon="plus" :href="route('admin.subjects.create')">New subject</x-button>
        </x-slot:actions>
    </x-page-header>

    <div class="flex flex-col gap-4">
        <x-filter-bar
            :action="route('admin.subjects.index')"
            search-label="Search subjects"
            search-placeholder="Search by name or code"
            :reset-url="route('admin.subjects.index')"
        >
            <x-form.select
                name="status"
                label="Status"
                hide-label
                placeholder="Any status"
                :options="$statusOptions"
                :value="$status"
                :old="false"
                class="sm:w-44"
            />
        </x-filter-bar>

        @if ($subjects->isEmpty())
            @if ($isFiltered)
                <x-empty-state variant="card" illustration="search" title="No subjects match your filters" description="Try another name or code, or clear the filters.">
                    <x-slot:actions>
                        <x-button variant="secondary" icon="rotate-ccw" :href="route('admin.subjects.index')">Clear filters</x-button>
                    </x-slot:actions>
                </x-empty-state>
            @else
                <x-empty-state variant="card" illustration="empty-plate" title="No subjects yet" description="Add subjects such as Health, Science or EPP – Home Economics, then assign them to teachers.">
                    <x-slot:actions>
                        <x-button icon="plus" :href="route('admin.subjects.create')">New subject</x-button>
                    </x-slot:actions>
                </x-empty-state>
            @endif
        @else
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
                @foreach ($subjects as $subject)
                    <x-setup.subject-card :subject="$subject" data-animate style="--stagger: {{ min($loop->iteration, 6) }}" />
                @endforeach
            </div>

            @if ($subjects->hasPages())
                <div>{{ $subjects->links() }}</div>
            @endif
        @endif
    </div>
</x-layouts.app>
