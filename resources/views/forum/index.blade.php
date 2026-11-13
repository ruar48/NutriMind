@php
    $canStartThread = auth()->user()->can('create', \App\Models\ForumThread::class);
    $canModerate = auth()->user()->can('moderate', \App\Models\ForumThread::class);
@endphp

<x-layouts.app title="Parent Forum" description="Questions, ideas and news shared by the school’s families and teachers.">
    <x-page-header
        eyebrow="Community"
        title="Parent Forum"
        description="Questions, healthy ideas and school news shared by families and teachers."
        :breadcrumbs="[['label' => 'Dashboard', 'url' => route('dashboard')], ['label' => 'Parent Forum']]"
    >
        <x-slot:leading>
            <span class="grid size-14 place-items-center rounded-2xl bg-carrot-600 text-white shadow-card">
                <x-icon name="messages-square" class="size-7" />
            </span>
        </x-slot:leading>
        <x-slot:actions>
            @if ($canModerate)
                <x-button variant="secondary" icon="shield" :href="route('admin.forum.reports.index')">Moderation</x-button>
            @endif
            @if ($canStartThread)
                <x-button icon="square-pen" :href="route('forum.threads.create')">New thread</x-button>
            @endif
        </x-slot:actions>
    </x-page-header>

    @if ($canModerate && ! $forumEnabled)
        <x-alert variant="warning" title="The forum is turned off for teachers and parents" data-forum-disabled-notice>
            Only administrators can open it right now. <a href="{{ route('admin.settings.edit') }}">Turn it on in Settings</a>.
        </x-alert>
    @endif

    <x-forum.thread-board
        :threads="$threads"
        :categories="$categories"
        :filters="$filters"
        :show-options="$showOptions"
        :action="route('forum.index')"
        :new-thread-url="$canStartThread ? route('forum.threads.create') : null"
    />
</x-layouts.app>
