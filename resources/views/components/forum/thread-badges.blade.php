@props([
    'thread',
    'size' => 'sm',
])

@if ($thread->is_pinned)
    <x-badge color="grape" icon="pin" :size="$size" data-forum-badge="pinned">Pinned</x-badge>
@endif
@if ($thread->is_locked)
    <x-badge color="slate" icon="lock" :size="$size" data-forum-badge="locked">Locked</x-badge>
@endif
@if ($thread->status !== \App\Enums\ForumContentStatus::Published)
    <x-status-badge :status="$thread->status" :size="$size" data-forum-badge="{{ $thread->status->value }}" />
@endif
