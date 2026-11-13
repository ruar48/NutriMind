@php
    $isFiltered = $status !== 'open' || filled(request()->query('search')) || filled(request()->query('reason'));
    $resolutions = [
        \App\Models\ForumReport::RESOLUTION_DISMISSED => ['label' => 'Dismissed', 'color' => 'slate', 'icon' => 'check'],
        \App\Models\ForumReport::RESOLUTION_CONTENT_HIDDEN => ['label' => 'Content hidden', 'color' => 'leaf', 'icon' => 'eye-off'],
    ];
    $dismiss = \App\Services\Forum\ReportDecision::Dismiss;
    $hideContent = \App\Services\Forum\ReportDecision::HideContent;
@endphp

<x-layouts.app title="Reported posts">
    <x-page-header
        eyebrow="Moderation"
        title="Reported posts"
        description="Review what teachers and parents flagged. Dismiss a report to keep the post, or hide the post from the forum."
        :breadcrumbs="[['label' => 'Dashboard', 'url' => route('admin.dashboard')], ['label' => 'Moderation']]"
    >
        <x-slot:actions>
            <x-button variant="secondary" icon="messages-square" :href="route('forum.index')">Open the forum</x-button>
        </x-slot:actions>
    </x-page-header>

    <div class="flex flex-col gap-4" data-animate style="--stagger: 1">
        <x-forum.moderation-tabs active="reports" :open-count="$openCount" :pending-count="$pendingCount" />

        <x-filter-bar
            :action="route('admin.forum.reports.index')"
            search-label="Search reports"
            search-placeholder="Search posts, details or reporters"
            :reset-url="route('admin.forum.reports.index')"
            :preserve="[]"
            :active="$isFiltered"
        >
            <x-form.select name="status" label="Status" hide-label :options="$statusOptions" :value="$status" :old="false" class="sm:w-44" />
            <x-form.select name="reason" label="Reason" hide-label placeholder="Any reason" :options="$reasonOptions" :value="request()->query('reason')" :old="false" class="sm:w-56" />
        </x-filter-bar>
    </div>

    @if ($reports->isEmpty())
        @if ($isFiltered)
            <x-empty-state variant="card" illustration="search" title="No reports match your filters"
                description="Try other words or another reason, or clear the filters to see the open reports.">
                <x-slot:actions>
                    <x-button variant="secondary" icon="rotate-ccw" :href="route('admin.forum.reports.index')">Clear filters</x-button>
                </x-slot:actions>
            </x-empty-state>
        @else
            <x-empty-state variant="card" illustration="forum" title="No open reports"
                description="When a teacher or parent reports a post, it shows up here for you to review.">
                <x-slot:actions>
                    <x-button variant="secondary" icon="history" :href="route('admin.forum.reports.index', ['status' => 'resolved'])">See resolved reports</x-button>
                </x-slot:actions>
            </x-empty-state>
        @endif
    @else
        <x-table caption="Forum reports" data-animate style="--stagger: 2">
            <x-slot:head>
                <x-table.th>Reported post</x-table.th>
                <x-table.th>Reason</x-table.th>
                <x-table.th>Reported by</x-table.th>
                <x-table.th>Status</x-table.th>
                <x-table.th align="right"><span class="sr-only">Actions</span></x-table.th>
            </x-slot:head>

            @foreach ($reports as $report)
                @php
                    $reply = $report->reply;
                    $target = $reply ?? $report->thread;
                    $postUrl = $reply === null
                        ? route('forum.threads.show', $report->thread)
                        : route('forum.threads.show', ['thread' => $report->thread, 'reply' => $reply->id]);
                    $resolution = $resolutions[$report->resolution] ?? null;
                @endphp
                <x-table.row data-forum-report="{{ $report->id }}">
                    <x-table.td class="min-w-72">
                        <div class="flex max-w-md items-start gap-3">
                            <span class="grid size-9 shrink-0 place-items-center rounded-xl bg-ink-900/5 text-ink-600 dark:bg-white/8 dark:text-ink-300">
                                <x-icon :name="$reply === null ? 'messages-square' : 'reply'" class="size-4" />
                            </span>
                            <div class="min-w-0">
                                <p class="text-xs text-ink-500 dark:text-ink-400">{{ $reply === null ? 'Thread' : 'Reply' }} by {{ $target->author->name }}</p>
                                <a href="{{ $postUrl }}" class="font-semibold text-ink-900 hover:text-leaf-700 hover:underline dark:text-white dark:hover:text-leaf-300">{{ \Illuminate\Support\Str::limit($report->thread->title, 80) }}</a>
                                <p class="mt-1 line-clamp-2 text-sm text-ink-600 dark:text-ink-300">{{ \Illuminate\Support\Str::limit(\Illuminate\Support\Str::squish($target->body), 160) }}</p>
                                @if ($target->status !== \App\Enums\ForumContentStatus::Published)
                                    <x-status-badge :status="$target->status" size="sm" class="mt-2" />
                                @endif
                            </div>
                        </div>
                    </x-table.td>
                    <x-table.td class="min-w-48">
                        <x-badge color="slate" icon="flag">{{ $report->reason->label() }}</x-badge>
                        @if (filled($report->details))
                            <p class="mt-1.5 line-clamp-3 max-w-xs text-xs text-ink-600 dark:text-ink-300">{{ $report->details }}</p>
                        @endif
                    </x-table.td>
                    <x-table.td class="whitespace-nowrap">
                        <div class="flex items-center gap-2">
                            <x-avatar :name="$report->reporter->name" size="xs" />
                            <span class="text-sm font-medium text-ink-800 dark:text-ink-100">{{ $report->reporter->name }}</span>
                        </div>
                        <time class="mt-1 block text-xs text-ink-500 dark:text-ink-400" datetime="{{ $report->created_at->toIso8601String() }}" title="{{ $report->created_at->format('M j, Y g:i A') }}">{{ $report->created_at->diffForHumans() }}</time>
                    </x-table.td>
                    <x-table.td class="whitespace-nowrap">
                        @if ($report->resolved_at === null)
                            <x-badge color="sun" icon="clock">Open</x-badge>
                        @else
                            <x-badge :color="$resolution['color'] ?? 'slate'" :icon="$resolution['icon'] ?? 'check'">{{ $resolution['label'] ?? 'Resolved' }}</x-badge>
                            <p class="mt-1 text-xs text-ink-500 dark:text-ink-400">
                                by {{ $report->resolver?->name ?? 'a former administrator' }} ·
                                <time datetime="{{ $report->resolved_at->toIso8601String() }}" title="{{ $report->resolved_at->format('M j, Y g:i A') }}">{{ $report->resolved_at->diffForHumans() }}</time>
                            </p>
                        @endif
                    </x-table.td>
                    <x-table.td align="right">
                        @if ($report->resolved_at === null)
                            <div class="flex justify-end gap-2">
                                <x-confirm-dialog
                                    :action="route('admin.forum.reports.update', $report)"
                                    method="PUT"
                                    :danger="false"
                                    title="Dismiss this report?"
                                    message="The post stays visible and this report is closed."
                                    confirm="Dismiss report"
                                    :button="$dismiss->label()"
                                    button-variant="secondary"
                                    button-icon="check"
                                >
                                    <x-slot:fields><input type="hidden" name="decision" value="{{ $dismiss->value }}"></x-slot:fields>
                                </x-confirm-dialog>
                                <x-confirm-dialog
                                    :action="route('admin.forum.reports.update', $report)"
                                    method="PUT"
                                    :danger="false"
                                    icon="eye-off"
                                    :title="$reply === null ? 'Hide the reported thread?' : 'Hide the reported reply?'"
                                    message="Teachers and parents no longer see it, and every open report about it is resolved."
                                    :confirm="$hideContent->label()"
                                    :button="$hideContent->label()"
                                    button-variant="secondary"
                                    button-icon="eye-off"
                                >
                                    <x-slot:fields><input type="hidden" name="decision" value="{{ $hideContent->value }}"></x-slot:fields>
                                </x-confirm-dialog>
                            </div>
                        @else
                            <x-button variant="ghost" size="sm" icon="external-link" :href="$postUrl">View post</x-button>
                        @endif
                    </x-table.td>
                </x-table.row>
            @endforeach

            <x-slot:footer>{{ $reports->links() }}</x-slot:footer>
        </x-table>
    @endif
</x-layouts.app>
