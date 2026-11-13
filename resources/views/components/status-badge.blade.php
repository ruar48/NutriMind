@props([
    'status' => null,
    'size' => 'md',
])

@if ($status !== null)
    <x-badge
        :color="$status->color()"
        :icon="method_exists($status, 'icon') ? $status->icon() : null"
        :dot="! method_exists($status, 'icon')"
        :size="$size"
        {{ $attributes }}
    >{{ $status->label() }}</x-badge>
@endif
