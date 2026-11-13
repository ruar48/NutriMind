@props([
    'threads',
    'categories',
    'filters',
    'showOptions',
    'action',
    'category' => null,
    'newThreadUrl' => null,
])

@php
    $isFiltered = $filters['search'] !== null || $filters['show'] !== null;
@endphp

<div class="flex flex-col gap-4" data-animate style="--stagger: 1">
    <x-forum.category-tabs :categories="$categories" :active="$category" />

    <x-filter-bar
        :action="$action"
        search-label="Search threads"
        search-placeholder="Search titles and messages"
        :reset-url="$action"
        :preserve="[]"
    >
        <x-form.select name="show" label="Show" hide-label placeholder="All threads" :options="$showOptions" :value="$filters['show']" :old="false" class="sm:w-56" />
    </x-filter-bar>
</div>

@if ($threads->isEmpty())
    @if ($isFiltered)
        <x-empty-state variant="card" illustration="search" title="No threads match your filters"
            description="Try other words, or clear the filters to see every thread." data-animate style="--stagger: 2">
            <x-slot:actions>
                <x-button variant="secondary" icon="rotate-ccw" :href="$action">Clear filters</x-button>
            </x-slot:actions>
        </x-empty-state>
    @else
        <x-empty-state variant="card" illustration="forum"
            :title="$category === null ? 'No threads yet' : 'No threads in '.$category->name.' yet'"
            description="Start the first conversation: ask a question or share a healthy idea that works for your family."
            data-animate style="--stagger: 2">
            @if ($newThreadUrl)
                <x-slot:actions>
                    <x-button icon="square-pen" :href="$newThreadUrl">Start a thread</x-button>
                </x-slot:actions>
            @endif
        </x-empty-state>
    @endif
@else
    <x-section
        :title="$category === null ? 'Threads' : 'Threads in '.$category->name"
        :description="trans_choice(':count thread|:count threads', $threads->total()).' · pinned first, then the latest activity'"
        icon="messages-square"
        :color="$category?->color ?? 'leaf'"
        padding="none"
        data-animate
        style="--stagger: 2"
    >
        <x-forum.thread-list :threads="$threads" :show-category="$category === null" />

        @if ($threads->hasPages())
            <div class="border-t border-ink-900/5 px-5 py-3 dark:border-white/8">{{ $threads->links() }}</div>
        @endif
    </x-section>
@endif
