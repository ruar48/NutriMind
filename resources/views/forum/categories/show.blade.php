@php
    $canStartThread = $category->is_active && auth()->user()->can('create', \App\Models\ForumThread::class);
@endphp

<x-layouts.app :title="$category->name" :description="$category->description">
    <x-page-header
        eyebrow="Parent Forum"
        :title="$category->name"
        :description="$category->description"
        :breadcrumbs="[
            ['label' => 'Dashboard', 'url' => route('dashboard')],
            ['label' => 'Parent Forum', 'url' => route('forum.index')],
            ['label' => $category->name],
        ]"
    >
        <x-slot:meta>
            <x-subject-chip :name="$category->name" :color="$category->color" :icon="$category->icon" />
            <x-badge color="slate" icon="messages-square">{{ trans_choice(':count thread|:count threads', $threads->total()) }}</x-badge>
            @unless ($category->is_active)
                <x-badge color="slate" icon="eye-off">Inactive</x-badge>
            @endunless
        </x-slot:meta>
        <x-slot:actions>
            <x-button variant="secondary" icon="arrow-left" :href="route('forum.index')">All topics</x-button>
            @if ($canStartThread)
                <x-button icon="square-pen" :href="route('forum.threads.create', ['category' => $category->slug])">New thread</x-button>
            @endif
        </x-slot:actions>
    </x-page-header>

    @unless ($category->is_active)
        <x-alert variant="info" title="This category is inactive" icon="eye-off">
            Teachers and parents cannot see it or post in it. <a href="{{ route('admin.forum-categories.edit', $category) }}">Edit the category</a> to make it active again.
        </x-alert>
    @endunless

    <x-forum.thread-board
        :threads="$threads"
        :categories="$categories"
        :category="$category"
        :filters="$filters"
        :show-options="$showOptions"
        :action="route('forum.categories.show', $category)"
        :new-thread-url="$canStartThread ? route('forum.threads.create', ['category' => $category->slug]) : null"
    />
</x-layouts.app>
