<x-layouts.app title="Edit thread">
    <x-page-header
        eyebrow="Parent Forum"
        title="Edit thread"
        description="Fix a typo or add details. Replies stay as they are."
        :breadcrumbs="[
            ['label' => 'Dashboard', 'url' => route('dashboard')],
            ['label' => 'Parent Forum', 'url' => route('forum.index')],
            ['label' => \Illuminate\Support\Str::limit($thread->title, 40), 'url' => route('forum.threads.show', $thread)],
            ['label' => 'Edit'],
        ]"
    />

    <x-forum.thread-form
        :thread="$thread"
        :category-options="$categoryOptions"
        :action="route('forum.threads.update', $thread)"
        method="PUT"
        :cancel="route('forum.threads.show', $thread)"
        submit="Save changes"
        submit-icon="check"
        :note="$thread->status === \App\Enums\ForumContentStatus::Published ? 'Readers see your changes right away.' : 'Only you and the moderators can see this thread.'"
    />
</x-layouts.app>
