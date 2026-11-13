@php
    $isFiltered = filled(request()->query('search')) || filled(request()->query('status'));
@endphp

<x-layouts.app title="Forum categories">
    <x-page-header
        eyebrow="Community"
        title="Forum categories"
        description="Group parent forum threads by topic. Deactivate a category to hide it without losing its threads."
        :breadcrumbs="[['label' => 'Dashboard', 'url' => route('admin.dashboard')], ['label' => 'Forum categories']]"
    >
        <x-slot:actions>
            <x-button variant="secondary" icon="messages-square" :href="route('forum.index')">Open the forum</x-button>
            <x-button icon="plus" :href="route('admin.forum-categories.create')">New category</x-button>
        </x-slot:actions>
    </x-page-header>

    <x-filter-bar
        :action="route('admin.forum-categories.index')"
        search-label="Search categories"
        search-placeholder="Search names and descriptions"
        :reset-url="route('admin.forum-categories.index')"
        :preserve="[]"
        data-animate
        style="--stagger: 1"
    >
        <x-form.select name="status" label="Status" hide-label placeholder="Any status" :options="$statusOptions" :value="request()->query('status')" :old="false" class="sm:w-44" />
    </x-filter-bar>

    @if ($categories->isEmpty())
        @if ($isFiltered)
            <x-empty-state variant="card" illustration="search" title="No categories match your filters"
                description="Try another name, or clear the filters to see every category.">
                <x-slot:actions>
                    <x-button variant="secondary" icon="rotate-ccw" :href="route('admin.forum-categories.index')">Clear filters</x-button>
                </x-slot:actions>
            </x-empty-state>
        @else
            <x-empty-state variant="dashed" illustration="forum" title="No categories yet"
                description="Create topics like “Healthy Recipes” or “School Events” so parents can start threads.">
                <x-slot:actions>
                    <x-button icon="plus" :href="route('admin.forum-categories.create')">New category</x-button>
                </x-slot:actions>
            </x-empty-state>
        @endif
    @else
        <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
            @foreach ($categories as $category)
                <x-card data-forum-category="{{ $category->slug }}" data-animate :style="'--stagger: '.min($loop->iteration + 1, 8)">
                    <div class="flex items-start justify-between gap-3">
                        <x-subject-chip :name="$category->name" :color="$category->color" :icon="$category->icon" size="lg" />
                        <x-badge :color="$category->is_active ? 'leaf' : 'slate'" dot>{{ $category->is_active ? 'Active' : 'Inactive' }}</x-badge>
                    </div>
                    <p class="mt-3 text-sm text-ink-600 dark:text-ink-300">{{ $category->description ?? 'No description yet.' }}</p>
                    <div class="mt-3 flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-ink-500 dark:text-ink-400">
                        <span class="inline-flex items-center gap-1.5">
                            <x-icon name="messages-square" class="size-3.5" />
                            {{ trans_choice(':count thread|:count threads', $category->threads_count) }}
                        </span>
                        <span class="inline-flex items-center gap-1.5">
                            <x-icon name="list-ordered" class="size-3.5" />
                            Order {{ $category->sort_order }}
                        </span>
                    </div>
                    <x-slot:footer>
                        <div class="flex flex-wrap items-center justify-end gap-2">
                            <x-button variant="ghost" size="sm" icon="external-link" :href="route('forum.categories.show', $category)">View</x-button>
                            <x-button variant="ghost" size="sm" icon="pencil" :href="route('admin.forum-categories.edit', $category)">Edit</x-button>
                            @if ($category->threads_count === 0)
                                <x-confirm-dialog
                                    :action="route('admin.forum-categories.destroy', $category)"
                                    :title="'Delete “'.$category->name.'”?'"
                                    message="The category is removed for good. This cannot be undone."
                                    confirm="Delete category"
                                    button="Delete"
                                    button-variant="ghost"
                                />
                            @else
                                <span class="inline-flex items-center gap-1.5 text-xs text-ink-500 dark:text-ink-400" title="{{ \App\Services\Forum\ForumCategories::HAS_THREADS_MESSAGE }}">
                                    <x-icon name="lock" class="size-3.5" />
                                    Has threads
                                </span>
                            @endif
                        </div>
                    </x-slot:footer>
                </x-card>
            @endforeach
        </div>

        @if ($categories->hasPages())
            <div>{{ $categories->links() }}</div>
        @endif
    @endif
</x-layouts.app>
