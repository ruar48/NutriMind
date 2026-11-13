@props([
    'seconds' => null,
])

@if ($seconds === null)
    <span {{ $attributes->class('text-ink-400 dark:text-ink-500') }}>—</span>
@else
    @php
        $seconds = max(0, (int) $seconds);
    @endphp
    <time
        datetime="PT{{ intdiv($seconds, 60) }}M{{ $seconds % 60 }}S"
        title="{{ trans_choice(':count second|:count seconds', $seconds) }}"
        {{ $attributes->class('tabular-nums whitespace-nowrap') }}
    >{{ \App\Services\Monitoring\QuizResults::formatDuration($seconds) }}</time>
@endif
