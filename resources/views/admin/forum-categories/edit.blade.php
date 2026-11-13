<x-layouts.app :title="'Edit '.$category->name">
    <x-page-header
        eyebrow="Community"
        :title="'Edit '.$category->name"
        :description="trans_choice('This category has :count thread.|This category has :count threads.', $category->threads_count)"
        :breadcrumbs="[
            ['label' => 'Dashboard', 'url' => route('admin.dashboard')],
            ['label' => 'Forum categories', 'url' => route('admin.forum-categories.index')],
            ['label' => $category->name],
        ]"
    >
        <x-slot:meta>
            <x-subject-chip :name="$category->name" :color="$category->color" :icon="$category->icon" />
            <x-badge :color="$category->is_active ? 'leaf' : 'slate'" dot>{{ $category->is_active ? 'Active' : 'Inactive' }}</x-badge>
        </x-slot:meta>
        <x-slot:actions>
            <x-button variant="secondary" icon="external-link" :href="route('forum.categories.show', $category)">View in forum</x-button>
            @if ($category->threads_count === 0)
                <x-confirm-dialog
                    :action="route('admin.forum-categories.destroy', $category)"
                    :title="'Delete “'.$category->name.'”?'"
                    message="The category is removed for good. This cannot be undone."
                    confirm="Delete category"
                    button="Delete"
                    button-variant="ghost"
                />
            @endif
        </x-slot:actions>
    </x-page-header>

    <x-forum.category-form
        :category="$category"
        :action="route('admin.forum-categories.update', $category)"
        method="PUT"
        :cancel="route('admin.forum-categories.index')"
        submit="Save category"
    />
</x-layouts.app>
