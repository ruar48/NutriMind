<x-layouts.app title="My children" description="The learners linked to your parent account and their NutriMind progress.">
    <x-page-header
        title="My children"
        description="The learners linked to your parent account. Open a child to follow their quizzes, missions, rewards and teacher feedback."
        :breadcrumbs="[['label' => 'Dashboard', 'url' => route('parent.dashboard')], ['label' => 'My children']]"
    />

    @if ($showFilters)
        <x-filter-bar
            :action="route('parent.children.index')"
            search-label="Search your children"
            search-placeholder="Search name, LRN or username"
            :reset-url="route('parent.children.index')"
        >
            <x-form.select
                name="grade"
                label="Grade level"
                hide-label
                placeholder="All grades"
                :options="\App\Enums\GradeLevel::options()"
                :value="$filters['grade']"
                :old="false"
                class="sm:w-44"
            />
        </x-filter-bar>
    @endif

    @if ($children->isEmpty())
        @if ($isFiltered)
            <x-empty-state variant="card" illustration="search" title="No children match your filters" description="Try another name or grade, or clear the filters.">
                <x-slot:actions>
                    <x-button variant="secondary" icon="rotate-ccw" :href="route('parent.children.index')">Clear filters</x-button>
                </x-slot:actions>
            </x-empty-state>
        @else
            <x-empty-state
                variant="card"
                illustration="classroom"
                title="Ask the school to link your child"
                description="Your parent account is not linked to a learner yet. Ask your child’s teacher or the school administrator to link your child’s NutriMind account. Their progress appears here right after."
            >
                <x-slot:actions>
                    <x-button variant="secondary" icon="layout-dashboard" :href="route('parent.dashboard')">Back to dashboard</x-button>
                </x-slot:actions>
            </x-empty-state>
        @endif
    @else
        <div class="grid grid-cols-1 gap-6 md:grid-cols-2 xl:grid-cols-3">
            @foreach ($cards as $card)
                <x-parent.child-card
                    :student="$card['student']"
                    :relationship="$card['student']->pivot->relationship"
                    :classrooms="$card['student']->enrolledClassrooms"
                    :stats="$card['stats']"
                    :href="route('parent.children.show', $card['student'])"
                    show-classrooms
                    heading-level="h2"
                    data-animate
                    :style="'--stagger: '.min($loop->iteration, 6)"
                />
            @endforeach
        </div>

        @if ($children->hasPages())
            {{ $children->links() }}
        @endif
    @endif
</x-layouts.app>
