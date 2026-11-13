@props([
    'thread',
    'reply' => null,
    'buttonVariant' => 'menu',
])

@php
    $key = $reply === null ? 'thread' : 'reply-'.$reply->id;
    $oldTarget = $errors->report->isNotEmpty() ? (filled(old('forum_reply_id')) ? 'reply-'.old('forum_reply_id') : 'thread') : null;
@endphp

<x-confirm-dialog
    :action="route('forum.reports.store')"
    method="POST"
    :danger="false"
    :id="'report-'.$key"
    icon="flag"
    :title="$reply === null ? 'Report this thread?' : 'Report this reply?'"
    message="The moderators review every report. The author is not told who reported the post."
    confirm="Send report"
    button="Report"
    :button-variant="$buttonVariant"
    button-icon="flag"
    data-forum-report-form="{{ $key }}"
>
    <x-slot:fields>
        <input type="hidden" name="forum_thread_id" value="{{ $thread->id }}">
        @if ($reply !== null)
            <input type="hidden" name="forum_reply_id" value="{{ $reply->id }}">
        @endif
    </x-slot:fields>

    <x-forum.report-fields :key="$key" :submitted="$oldTarget === $key" />
</x-confirm-dialog>
