@props([
    'user',
    'size' => 'sm',
    'time' => null,
    'timePrefix' => null,
])

<div {{ $attributes->class('flex min-w-0 items-center gap-3') }}>
    <x-avatar :name="$user->name" :size="$size" />
    <div class="min-w-0">
        <p class="flex flex-wrap items-center gap-x-2 gap-y-1">
            <span class="truncate text-sm font-semibold text-ink-900 dark:text-white">{{ $user->name }}</span>
            <x-forum.role-badge :user="$user" />
        </p>
        @if ($time instanceof \DateTimeInterface)
            <p class="mt-0.5 text-xs text-ink-500 dark:text-ink-400">
                @if ($timePrefix)
                    {{ $timePrefix }}
                @endif
                <time datetime="{{ $time->toIso8601String() }}" title="{{ $time->format('M j, Y g:i A') }}">{{ $time->diffForHumans() }}</time>
            </p>
        @endif
    </div>
</div>
