@props([
    'user',
])

@php
    $role = $user->role instanceof \App\Enums\UserRole ? $user->role : null;
@endphp

@if (in_array($role, [\App\Enums\UserRole::Admin, \App\Enums\UserRole::Teacher], true))
    <x-badge :color="$role->color()" :icon="$role->icon()" size="sm" data-forum-author-role="{{ $role->value }}" {{ $attributes }}>{{ $role->label() }}</x-badge>
@endif
