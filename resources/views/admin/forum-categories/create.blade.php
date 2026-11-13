<x-layouts.app title="New forum category">
    <x-page-header
        eyebrow="Community"
        title="New forum category"
        description="Group parent forum threads by topic, like “Healthy Recipes” or “School Events”."
        :breadcrumbs="[
            ['label' => 'Dashboard', 'url' => route('admin.dashboard')],
            ['label' => 'Forum categories', 'url' => route('admin.forum-categories.index')],
            ['label' => 'New category'],
        ]"
    />

    <x-forum.category-form
        :category="$category"
        :action="route('admin.forum-categories.store')"
        :cancel="route('admin.forum-categories.index')"
        submit="Create category"
        submit-icon="plus"
    />
</x-layouts.app>
