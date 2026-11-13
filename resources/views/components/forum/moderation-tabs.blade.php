@props([
    'active',
    'openCount',
    'pendingCount',
])

<x-tabs label="Moderation queues" {{ $attributes }}>
    <x-tab :href="route('admin.forum.reports.index')" :active="$active === 'reports'" icon="flag" :count="$openCount">Reported posts</x-tab>
    <x-tab :href="route('admin.forum.pending.index')" :active="$active === 'pending'" icon="clock" :count="$pendingCount">Waiting for approval</x-tab>
</x-tabs>
