@props([
    'reply',
    'reported' => false,
    'openReports' => 0,
])

@php
    $viewer = auth()->user();
    $isAdmin = $viewer?->isAdmin() ?? false;
    $isHidden = $reply->status !== \App\Enums\ForumContentStatus::Published;
    $canEdit = ! $isHidden && ($viewer?->can('update', $reply) ?? false);
    $canDelete = $viewer?->can('delete', $reply) ?? false;
    $canModerate = $viewer?->can('moderate', \App\Models\ForumReply::class) ?? false;
    $canReport = ! $canModerate && ! $isHidden && ! $reported && ($viewer?->can('report', $reply) ?? false);
@endphp

<article id="reply-{{ $reply->id }}" {{ $attributes->class('scroll-mt-24') }} data-forum-reply="{{ $reply->id }}" data-forum-reply-status="{{ $reply->status->value }}">
    <x-card padding="none">
        <div class="flex flex-col gap-3 p-4 sm:p-5">
            <div class="flex items-start justify-between gap-3">
                <x-forum.author :user="$reply->author" :time="$reply->created_at" />

                <div class="flex shrink-0 flex-wrap items-center justify-end gap-1.5">
                    @if ($isHidden && $isAdmin)
                        <x-status-badge :status="$reply->status" size="sm" />
                    @endif
                    @if ($openReports > 0)
                        <x-badge color="berry" icon="flag" size="sm" data-forum-open-reports="{{ $openReports }}">{{ trans_choice(':count open report|:count open reports', $openReports) }}</x-badge>
                    @endif

                    @if ($canEdit || $canDelete || $canReport || $reported || $canModerate)
                        <x-dropdown variant="icon" icon="ellipsis-vertical" :label="'Actions for the reply by '.$reply->author->name">
                            @if ($canEdit)
                                <x-dropdown.item :href="route('forum.replies.edit', $reply)" icon="pencil">Edit reply</x-dropdown.item>
                            @endif
                            @if ($canReport)
                                <x-forum.report-dialog :thread="$reply->thread" :reply="$reply" />
                            @elseif ($reported)
                                <x-dropdown.item icon="flag" disabled>You reported this reply</x-dropdown.item>
                            @endif
                            @if ($canModerate)
                                @php
                                    $action = $isHidden ? \App\Services\Forum\ModerationAction::Unhide : \App\Services\Forum\ModerationAction::Hide;
                                @endphp
                                <x-confirm-dialog
                                    :action="route('forum.replies.moderation.update', $reply)"
                                    method="PUT"
                                    :danger="false"
                                    :title="$isHidden ? 'Show this reply again?' : 'Hide this reply?'"
                                    :message="$isHidden ? 'Everyone in the forum can read it again.' : 'Readers see “This reply was hidden by a moderator” instead. Open reports about it are resolved.'"
                                    :confirm="$isHidden ? 'Show reply' : 'Hide reply'"
                                    :button="$action->label()"
                                    button-variant="menu"
                                    :button-icon="$action->icon()"
                                >
                                    <x-slot:fields><input type="hidden" name="action" value="{{ $action->value }}"></x-slot:fields>
                                </x-confirm-dialog>
                            @endif
                            @if ($canDelete)
                                <x-dropdown.divider />
                                <x-confirm-dialog
                                    :action="route('forum.replies.destroy', $reply)"
                                    title="Delete this reply?"
                                    message="The reply is removed for everyone. This cannot be undone."
                                    confirm="Delete reply"
                                    button="Delete reply"
                                    button-variant="menu"
                                />
                            @endif
                        </x-dropdown>
                    @endif
                </div>
            </div>

            @if (! $isHidden || $isAdmin)
                <x-forum.post-body :body="$reply->body" @class(['opacity-70' => $isHidden]) />
            @else
                <p class="flex items-center gap-2 rounded-xl bg-ink-50 px-3.5 py-3 text-sm text-ink-500 italic ring-1 ring-ink-900/5 ring-inset dark:bg-white/3 dark:text-ink-400 dark:ring-white/8" data-forum-hidden-notice>
                    <x-icon name="eye-off" class="size-4 shrink-0" />
                    This reply was hidden by a moderator.
                </p>
            @endif
        </div>
    </x-card>
</article>
