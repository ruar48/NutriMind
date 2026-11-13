@php
    $isArchivedTab = $status === 'archived';
    $filters = array_filter(['subject' => request('subject'), 'search' => request('search')], fn ($value) => is_string($value) && $value !== '');
    $canCreate = auth()->user()->can('create', \App\Models\Classroom::class);
    $resetUrl = route('teacher.classrooms.index', array_filter(['status' => $isArchivedTab ? 'archived' : null]));
@endphp

<x-layouts.app title="Classrooms">
    <x-page-header
        eyebrow="Teaching"
        title="Classrooms"
        description="Your classes, their learners and the class codes learners enter in the NutriMind game."
        :breadcrumbs="[['label' => 'Dashboard', 'url' => route('teacher.dashboard')], ['label' => 'Classrooms']]"
    >
        <x-slot:actions>
            <x-button variant="secondary" icon="book-open" :href="route('teacher.subjects.index')">My subjects</x-button>
            @if ($canCreate)
                <x-button icon="plus" :href="route('teacher.classrooms.create')">New classroom</x-button>
            @endif
        </x-slot:actions>
    </x-page-header>

    @if ($lockedCount > 0)
        <x-alert variant="warning" icon="lock" title="Some classrooms are locked" data-classrooms-locked="{{ $lockedCount }}">
            {{ trans_choice(':count classroom is locked because the administrator removed your access to its subject.|:count classrooms are locked because the administrator removed your access to their subject.', $lockedCount) }}
            Ask your school administrator if you still teach {{ $lockedCount === 1 ? 'it' : 'them' }}.
        </x-alert>
    @endif

    <div class="flex flex-col gap-4">
        <x-tabs label="Filter by status">
            <x-tab :href="route('teacher.classrooms.index', $filters)" :active="! $isArchivedTab" icon="school" :count="$counts['active']">Active</x-tab>
            <x-tab :href="route('teacher.classrooms.index', ['status' => 'archived'] + $filters)" :active="$isArchivedTab" icon="archive" :count="$counts['archived']">Archived</x-tab>
        </x-tabs>

        <x-filter-bar
            :action="route('teacher.classrooms.index')"
            search-label="Search classrooms"
            search-placeholder="Search name, section, room or class code"
            :reset-url="$resetUrl"
            :preserve="['status']"
        >
            <x-form.select
                name="subject"
                label="Subject"
                hide-label
                placeholder="All subjects"
                :options="$subjectOptions"
                :value="request('subject')"
                :old="false"
                class="sm:w-56"
            />
        </x-filter-bar>
    </div>

    @if ($classrooms->isEmpty())
        @if ($isFiltered)
            <x-empty-state variant="card" illustration="search" title="No classrooms match your filters" description="Try another subject or search term, or clear the filters.">
                <x-slot:actions>
                    <x-button variant="secondary" icon="rotate-ccw" :href="$resetUrl">Clear filters</x-button>
                </x-slot:actions>
            </x-empty-state>
        @elseif ($isArchivedTab)
            <x-empty-state variant="card" icon="archive" title="No archived classrooms" description="When a class ends, archive it from its menu. Archived classes keep their learners and results, read-only.">
                <x-slot:actions>
                    <x-button variant="secondary" icon="school" :href="route('teacher.classrooms.index')">View active classrooms</x-button>
                </x-slot:actions>
            </x-empty-state>
        @elseif ($canCreate)
            <x-empty-state variant="card" illustration="classroom" title="No classrooms yet" description="Create a classroom for each section you teach. Learners join it in the NutriMind game with its class code.">
                <x-slot:actions>
                    <x-button icon="plus" :href="route('teacher.classrooms.create')">New classroom</x-button>
                </x-slot:actions>
            </x-empty-state>
        @else
            <x-empty-state variant="card" illustration="classroom" title="No classrooms yet" description="You can create classrooms once your school administrator assigns you an active subject and gives you permission to manage classrooms.">
                <x-slot:actions>
                    <x-button variant="secondary" icon="book-open" :href="route('teacher.subjects.index')">View my subjects</x-button>
                </x-slot:actions>
            </x-empty-state>
        @endif
    @else
        <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
            @foreach ($classrooms as $classroom)
                <x-classrooms.card :classroom="$classroom" data-animate style="--stagger: {{ min($loop->iteration, 6) }}" />
            @endforeach
        </div>

        @if ($classrooms->hasPages())
            <div>{{ $classrooms->links() }}</div>
        @endif
    @endif
</x-layouts.app>
