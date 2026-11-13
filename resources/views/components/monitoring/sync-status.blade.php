@props([
    'syncedAt' => null,
])

@php
    $moment = $syncedAt instanceof \DateTimeInterface ? \Illuminate\Support\Carbon::instance($syncedAt) : null;
    $state = match (true) {
        $moment === null => 'never',
        \App\Services\Monitoring\SyncHealth::needsAttention($moment) => 'stale',
        default => 'ok',
    };
@endphp

<span {{ $attributes->class([
    'inline-flex items-center gap-1.5 whitespace-nowrap',
    'font-medium text-sun-800 dark:text-sun-300' => $state !== 'ok',
]) }} data-sync-status="{{ $state }}">
    @if ($state === 'never')
        <x-icon name="triangle-alert" class="size-4 shrink-0" />
        Never synced
    @else
        @if ($state === 'stale')
            <x-icon name="triangle-alert" class="size-4 shrink-0" />
            <span class="sr-only">Sync overdue:</span>
        @endif
        <time datetime="{{ $moment->toIso8601String() }}" title="{{ $moment->format('M j, Y g:i A') }}">{{ $moment->diffForHumans() }}</time>
    @endif
</span>
