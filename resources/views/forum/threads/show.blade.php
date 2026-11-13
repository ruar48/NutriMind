@php
    $viewer = auth()->user();
    $isAdmin = $viewer->isAdmin();
    $isAuthor = (int) $thread->author_id === (int) $viewer->id;
    $isPublished = $thread->status === \App\Enums\ForumContentStatus::Published;
    $isPending = $thread->status === \App\Enums\ForumContentStatus::Pending;
    $isHidden = $thread->status === \App\Enums\ForumContentStatus::Hidden;
    $canModerate = $viewer->can('moderate', \App\Models\ForumThread::class);
    $canUpdate = $viewer->can('update', $thread);
    $canDelete = $viewer->can('delete', $thread);
    $canReport = ! $canModerate && ! $threadReported && $isPublished && $viewer->can('report', $thread);
    $categoryUrl = $thread->category->is_active || $isAdmin ? route('forum.categories.show', $thread->category) : null;
    $lastActivity = $thread->last_activity_at ?? $thread->created_at;
    $approve = \App\Services\Forum\ApprovalDecision::Approve;
    $reject = \App\Services\Forum\ApprovalDecision::Reject;
    $hide = \App\Services\Forum\ModerationAction::Hide;
    $unhide = \App\Services\Forum\ModerationAction::Unhide;
    $toggleActions = [
        $thread->is_pinned ? \App\Services\Forum\ModerationAction::Unpin : \App\Services\Forum\ModerationAction::Pin,
        $thread->is_locked ? \App\Services\Forum\ModerationAction::Unlock : \App\Services\Forum\ModerationAction::Lock,
    ];
@endphp

<x-layouts.app :title="\Illuminate\Support\Str::limit($thread->title, 60)" :description="\Illuminate\Support\Str::limit(\Illuminate\Support\Str::squish($thread->body), 150)">
    <x-page-header
        eyebrow="Parent Forum"
        :title="$thread->title"
        :breadcrumbs="[
            ['label' => 'Dashboard', 'url' => route('dashboard')],
            ['label' => 'Parent Forum', 'url' => route('forum.index')],
            ['label' => $thread->category->name, 'url' => $categoryUrl],
            ['label' => \Illuminate\Support\Str::limit($thread->title, 40)],
        ]"
    >
        <x-slot:meta>
            <x-subject-chip :name="$thread->category->name" :color="$thread->category->color" :icon="$thread->category->icon" />
            <x-forum.thread-badges :thread="$thread" size="md" />
            @if ($threadOpenReports > 0)
                <x-badge color="berry" icon="flag" data-forum-open-reports="{{ $threadOpenReports }}">{{ trans_choice(':count open report|:count open reports', $threadOpenReports) }}</x-badge>
            @endif
        </x-slot:meta>
        <x-slot:actions>
            @if ($canReply)
                <x-button icon="reply" href="#reply-form">Reply</x-button>
            @endif
            @if ($canUpdate)
                <x-button variant="secondary" icon="pencil" :href="route('forum.threads.edit', $thread)">Edit</x-button>
            @endif
            @if ($canReport)
                <x-forum.report-dialog :thread="$thread" button-variant="secondary" />
            @elseif ($threadReported)
                <x-badge color="slate" icon="flag" size="lg" data-forum-reported>You reported this thread</x-badge>
            @endif
            @if ($canDelete)
                <x-confirm-dialog
                    :action="route('forum.threads.destroy', $thread)"
                    title="Delete this thread?"
                    :message="$isAuthor ? 'Your thread and all of its replies are removed. This cannot be undone.' : 'The thread, its replies and its reports are removed for everyone. This cannot be undone.'"
                    confirm="Delete thread"
                    button="Delete"
                    button-variant="ghost"
                />
            @endif
        </x-slot:actions>
    </x-page-header>

    @if ($canModerate)
        <x-card padding="sm" data-forum-moderation-tools data-animate style="--stagger: 1">
            <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                <p class="flex items-center gap-2 text-sm font-semibold text-ink-800 dark:text-ink-100">
                    <span class="grid size-8 place-items-center rounded-lg bg-grape-50 text-grape-700 ring-1 ring-grape-600/15 ring-inset dark:bg-grape-400/10 dark:text-grape-300 dark:ring-grape-400/20">
                        <x-icon name="shield" class="size-4" />
                    </span>
                    Moderator tools
                </p>
                <div class="flex flex-wrap items-center gap-2">
                    @if ($isPending)
                        <x-confirm-dialog
                            :action="route('admin.forum.pending.update', $thread)"
                            method="PUT"
                            :danger="false"
                            title="Approve this thread?"
                            message="It is published in the forum and moves to the top of the latest activity."
                            :confirm="$approve->label()"
                            :button="$approve->label()"
                            button-variant="primary"
                            button-icon="circle-check"
                        >
                            <x-slot:fields><input type="hidden" name="decision" value="{{ $approve->value }}"></x-slot:fields>
                        </x-confirm-dialog>
                        <x-confirm-dialog
                            :action="route('admin.forum.pending.update', $thread)"
                            method="PUT"
                            :danger="false"
                            icon="ban"
                            title="Reject this thread?"
                            message="It stays out of the forum. Only its author and administrators can see it."
                            :confirm="$reject->label()"
                            :button="$reject->label()"
                            button-variant="secondary"
                            button-icon="ban"
                        >
                            <x-slot:fields><input type="hidden" name="decision" value="{{ $reject->value }}"></x-slot:fields>
                        </x-confirm-dialog>
                    @endif

                    @foreach ($toggleActions as $toggleAction)
                        <form method="POST" action="{{ route('forum.threads.moderation.update', $thread) }}" data-submit-once>
                            @csrf
                            @method('PUT')
                            <input type="hidden" name="action" value="{{ $toggleAction->value }}">
                            <x-button type="submit" variant="secondary" size="sm" :icon="$toggleAction->icon()">{{ $toggleAction->label() }}</x-button>
                        </form>
                    @endforeach

                    @if ($isPublished)
                        <x-confirm-dialog
                            :action="route('forum.threads.moderation.update', $thread)"
                            method="PUT"
                            :danger="false"
                            icon="eye-off"
                            title="Hide this thread?"
                            message="Teachers and parents no longer see it; its author still can. Open reports about it are resolved."
                            confirm="Hide thread"
                            :button="$hide->label()"
                            button-variant="secondary"
                            :button-icon="$hide->icon()"
                        >
                            <x-slot:fields><input type="hidden" name="action" value="{{ $hide->value }}"></x-slot:fields>
                        </x-confirm-dialog>
                    @elseif ($isHidden)
                        <x-confirm-dialog
                            :action="route('forum.threads.moderation.update', $thread)"
                            method="PUT"
                            :danger="false"
                            icon="eye"
                            title="Show this thread again?"
                            message="Everyone in the forum can read it and reply again."
                            confirm="Show thread"
                            :button="$unhide->label()"
                            button-variant="secondary"
                            :button-icon="$unhide->icon()"
                        >
                            <x-slot:fields><input type="hidden" name="action" value="{{ $unhide->value }}"></x-slot:fields>
                        </x-confirm-dialog>
                    @endif

                    @if ($threadOpenReports > 0 || $replyOpenReports !== [])
                        <x-button variant="ghost" size="sm" icon="flag" :href="route('admin.forum.reports.index')">Reports queue</x-button>
                    @endif
                </div>
            </div>
        </x-card>
    @endif

    @if ($isPending)
        <x-alert variant="warning" title="Waiting for approval" icon="clock" data-forum-status-notice="pending">
            {{ $isAdmin ? 'Teachers and parents cannot see this thread until a moderator approves it.' : 'A moderator reviews new threads before they appear in the forum. Until then, only you and the moderators can see it.' }}
        </x-alert>
    @elseif ($isHidden)
        <x-alert variant="info" title="Hidden by a moderator" icon="eye-off" data-forum-status-notice="hidden">
            {{ $isAdmin ? 'Teachers and parents cannot see this thread. Use “Show again” to publish it.' : 'Only you and the moderators can see this thread.' }}
        </x-alert>
    @endif

    @if ($errors->report->isNotEmpty())
        <x-section title="Finish your report" description="Your report was not sent yet. Check it and send it again." icon="flag" color="berry" data-forum-report-retry>
            <form method="POST" action="{{ route('forum.reports.store') }}" class="flex flex-col gap-4" data-submit-once>
                @csrf
                <input type="hidden" name="forum_thread_id" value="{{ $thread->id }}">
                @if (filled(old('forum_reply_id')))
                    <input type="hidden" name="forum_reply_id" value="{{ old('forum_reply_id') }}">
                @endif
                @foreach (['forum_thread_id', 'forum_reply_id'] as $targetField)
                    @error($targetField, 'report')
                        <x-alert variant="danger">{{ $message }}</x-alert>
                    @enderror
                @endforeach
                <x-forum.report-fields key="retry" submitted />
                <div class="flex justify-end">
                    <x-button type="submit" icon="flag">Send report</x-button>
                </div>
            </form>
        </x-section>
    @endif

    <div class="grid grid-cols-1 gap-6 xl:grid-cols-3">
        <div class="flex min-w-0 flex-col gap-6 xl:col-span-2">
            <x-card padding="none" data-animate style="--stagger: 2">
                <div class="flex flex-col gap-4 p-5 sm:p-6">
                    <x-forum.author :user="$thread->author" size="md" :time="$thread->created_at" time-prefix="Posted" />
                    <x-forum.post-body :body="$thread->body" />
                </div>
            </x-card>

            <section id="replies" class="flex scroll-mt-24 flex-col gap-4" aria-labelledby="replies-title" data-animate style="--stagger: 3">
                <h2 id="replies-title" class="flex items-center gap-2 font-display text-lg font-semibold tracking-tight text-ink-950 dark:text-white">
                    <x-icon name="messages-square" class="size-5 text-leaf-600 dark:text-leaf-400" />
                    {{ trans_choice(':count reply|:count replies', $thread->published_replies_count) }}
                </h2>

                @if ($replies->total() === 0)
                    <x-empty-state variant="dashed" compact icon="message-circle" title="No replies yet"
                        :description="$canReply ? 'Be the first to reply and share what works for your family.' : 'Replies will appear here.'" />
                @elseif ($replies->isEmpty())
                    <x-empty-state variant="dashed" compact icon="message-circle" title="No replies on this page" description="This page is past the last reply.">
                        <x-slot:actions>
                            <x-button variant="secondary" size="sm" icon="arrow-left" :href="route('forum.threads.show', $thread).'#replies'">First page</x-button>
                        </x-slot:actions>
                    </x-empty-state>
                @else
                    <div class="flex flex-col gap-3">
                        @foreach ($replies as $reply)
                            <x-forum.reply-card :reply="$reply" :reported="in_array($reply->id, $reportedReplyIds, true)" :open-reports="$replyOpenReports[$reply->id] ?? 0" />
                        @endforeach
                    </div>
                @endif

                @if ($replies->hasPages())
                    <div>{{ $replies->fragment('replies')->links() }}</div>
                @endif
            </section>

            @if ($canReply)
                <x-section id="reply-form" class="scroll-mt-24" title="Add a reply" icon="reply"
                    :description="$thread->is_locked ? 'This thread is locked. As an administrator you can still reply.' : 'Be kind and helpful. Your reply appears right away.'">
                    <form method="POST" action="{{ route('forum.threads.replies.store', $thread) }}" class="flex flex-col gap-4" data-submit-once>
                        @csrf
                        <x-form.textarea name="body" label="Your reply" :max-length="3000" rows="5" placeholder="Write your reply…" required />
                        <div class="flex justify-end">
                            <x-button type="submit" icon="send">Post reply</x-button>
                        </div>
                    </form>
                </x-section>
            @elseif ($replyNotice)
                <x-alert id="reply-form" variant="info" :icon="$thread->is_locked ? 'lock' : 'info'" data-forum-reply-notice>{{ $replyNotice }}</x-alert>
            @endif
        </div>

        <aside class="flex min-w-0 flex-col gap-6" data-animate style="--stagger: 4">
            <x-section title="About this thread" icon="info" color="slate">
                <x-definition-list>
                    <x-detail-row label="Category">
                        @if ($categoryUrl)
                            <a href="{{ $categoryUrl }}" class="font-semibold text-leaf-700 hover:underline dark:text-leaf-300">{{ $thread->category->name }}</a>
                        @else
                            {{ $thread->category->name }}
                        @endif
                    </x-detail-row>
                    <x-detail-row label="Started by" :value="$thread->author->name" />
                    <x-detail-row label="Posted">
                        <time datetime="{{ $thread->created_at->toIso8601String() }}">{{ $thread->created_at->format('M j, Y g:i A') }}</time>
                    </x-detail-row>
                    <x-detail-row label="Last activity">
                        <time datetime="{{ $lastActivity->toIso8601String() }}" title="{{ $lastActivity->format('M j, Y g:i A') }}">{{ $lastActivity->diffForHumans() }}</time>
                    </x-detail-row>
                    <x-detail-row label="Replies" :value="number_format($thread->published_replies_count)" />
                    <x-detail-row label="Status">
                        <x-status-badge :status="$thread->status" size="sm" />
                    </x-detail-row>
                </x-definition-list>
            </x-section>

            <x-section title="Community guidelines" icon="hand-heart" color="carrot">
                <ul class="list-disc space-y-1.5 pl-5 text-sm text-ink-600 dark:text-ink-300">
                    <li>Be kind and respectful. We are all learning together.</li>
                    <li>Never share addresses, phone numbers or your child’s password.</li>
                    <li>No selling, advertising or chain messages.</li>
                    <li>Use “Report” when a post breaks these rules.</li>
                </ul>
            </x-section>
        </aside>
    </div>
</x-layouts.app>
