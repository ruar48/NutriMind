@php
    $isFiltered = filled(request()->query('search')) || filled(request()->query('category'));
    $approve = \App\Services\Forum\ApprovalDecision::Approve;
    $reject = \App\Services\Forum\ApprovalDecision::Reject;
@endphp

<x-layouts.app title="Threads waiting for approval">
    <x-page-header
        eyebrow="Moderation"
        title="Waiting for approval"
        description="New threads by teachers and parents wait here while approval is required. Approve them to publish, or reject them to keep them out of the forum."
        :breadcrumbs="[['label' => 'Dashboard', 'url' => route('admin.dashboard')], ['label' => 'Moderation', 'url' => route('admin.forum.reports.index')], ['label' => 'Waiting for approval']]"
    >
        <x-slot:actions>
            <x-button variant="secondary" icon="messages-square" :href="route('forum.index')">Open the forum</x-button>
        </x-slot:actions>
    </x-page-header>

    <div class="flex flex-col gap-4" data-animate style="--stagger: 1">
        <x-forum.moderation-tabs active="pending" :open-count="$openCount" :pending-count="$pendingCount" />

        @unless ($requiresApproval)
            <x-alert variant="info" title="Approval is turned off" icon="info" data-forum-approval-off>
                New threads are published right away. <a href="{{ route('admin.settings.edit') }}">Require approval in Settings</a>.
            </x-alert>
        @endunless

        <x-filter-bar
            :action="route('admin.forum.pending.index')"
            search-label="Search pending threads"
            search-placeholder="Search titles and messages"
            :reset-url="route('admin.forum.pending.index')"
            :preserve="[]"
        >
            <x-form.select name="category" label="Category" hide-label placeholder="Any category" :options="$categoryOptions" :value="request()->query('category')" :old="false" class="sm:w-60" />
        </x-filter-bar>
    </div>

    @if ($threads->isEmpty())
        @if ($isFiltered)
            <x-empty-state variant="card" illustration="search" title="No pending threads match your filters"
                description="Try other words or another category, or clear the filters.">
                <x-slot:actions>
                    <x-button variant="secondary" icon="rotate-ccw" :href="route('admin.forum.pending.index')">Clear filters</x-button>
                </x-slot:actions>
            </x-empty-state>
        @else
            <x-empty-state variant="card" illustration="hourglass" title="Nothing is waiting for approval"
                description="Threads that need a moderator’s approval appear here, oldest first.">
                <x-slot:actions>
                    <x-button variant="secondary" icon="flag" :href="route('admin.forum.reports.index')">Review reported posts</x-button>
                </x-slot:actions>
            </x-empty-state>
        @endif
    @else
        <div class="flex flex-col gap-4">
            @foreach ($threads as $thread)
                <x-card data-forum-pending-thread="{{ $thread->id }}" data-animate :style="'--stagger: '.min($loop->iteration + 1, 8)">
                    <div class="flex flex-col gap-4">
                        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                            <x-forum.author :user="$thread->author" size="md" :time="$thread->created_at" time-prefix="Submitted" />
                            <x-subject-chip :name="$thread->category->name" :color="$thread->category->color" :icon="$thread->category->icon" size="sm" />
                        </div>
                        <div class="min-w-0">
                            <h2 class="font-display text-lg leading-snug font-semibold tracking-tight text-ink-950 dark:text-white">
                                <a href="{{ route('forum.threads.show', $thread) }}" class="hover:text-leaf-700 hover:underline dark:hover:text-leaf-300">{{ $thread->title }}</a>
                            </h2>
                            <x-forum.post-body :body="\Illuminate\Support\Str::limit($thread->body, 600)" class="mt-2" />
                        </div>
                    </div>
                    <x-slot:footer>
                        <div class="flex flex-col-reverse gap-2 sm:flex-row sm:items-center sm:justify-end">
                            <x-button variant="ghost" size="sm" icon="eye" :href="route('forum.threads.show', $thread)">Read the whole thread</x-button>
                            <x-confirm-dialog
                                :action="route('admin.forum.pending.update', $thread)"
                                method="PUT"
                                :danger="false"
                                icon="ban"
                                :title="'Reject “'.\Illuminate\Support\Str::limit($thread->title, 60).'”?'"
                                message="It stays out of the forum. Only its author and administrators can see it."
                                :confirm="$reject->label()"
                                :button="$reject->label()"
                                button-variant="secondary"
                                button-icon="ban"
                            >
                                <x-slot:fields><input type="hidden" name="decision" value="{{ $reject->value }}"></x-slot:fields>
                            </x-confirm-dialog>
                            <x-confirm-dialog
                                :action="route('admin.forum.pending.update', $thread)"
                                method="PUT"
                                :danger="false"
                                :title="'Approve “'.\Illuminate\Support\Str::limit($thread->title, 60).'”?'"
                                message="It is published in the forum and moves to the top of the latest activity."
                                :confirm="$approve->label()"
                                :button="$approve->label()"
                                button-variant="primary"
                                button-icon="circle-check"
                            >
                                <x-slot:fields><input type="hidden" name="decision" value="{{ $approve->value }}"></x-slot:fields>
                            </x-confirm-dialog>
                        </div>
                    </x-slot:footer>
                </x-card>
            @endforeach
        </div>

        @if ($threads->hasPages())
            <div>{{ $threads->links() }}</div>
        @endif
    @endif
</x-layouts.app>
