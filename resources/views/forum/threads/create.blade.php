<x-layouts.app title="New thread">
    <x-page-header
        eyebrow="Parent Forum"
        title="Start a thread"
        description="Ask a question or share an idea with the school’s families and teachers."
        :breadcrumbs="[
            ['label' => 'Dashboard', 'url' => route('dashboard')],
            ['label' => 'Parent Forum', 'url' => route('forum.index')],
            ['label' => 'New thread'],
        ]"
    />

    @if ($requiresApproval)
        <x-alert variant="info" title="Threads are reviewed first" icon="clock" data-animate>
            A moderator publishes your thread after a quick check. Until then, only you and the moderators can see it.
        </x-alert>
    @endif

    @if ($categoryOptions === [])
        <x-empty-state variant="card" illustration="forum" title="No open categories"
            description="The forum has no open categories right now. Please check back later.">
            <x-slot:actions>
                <x-button variant="secondary" icon="arrow-left" :href="route('forum.index')">Back to the forum</x-button>
            </x-slot:actions>
        </x-empty-state>
    @else
        <x-forum.thread-form
            :thread="$thread"
            :category-options="$categoryOptions"
            :action="route('forum.threads.store')"
            :cancel="route('forum.index')"
            submit="Post thread"
            :requires-approval="$requiresApproval"
        />
    @endif
</x-layouts.app>
