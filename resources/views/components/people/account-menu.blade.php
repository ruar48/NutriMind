@props([
    'user',
])

@php
    $profileUrl = match ($user->role) {
        \App\Enums\UserRole::Student => route('admin.students.show', $user),
        \App\Enums\UserRole::Teacher => route('admin.teachers.show', $user),
        \App\Enums\UserRole::Parent => route('admin.parents.show', $user),
        default => null,
    };
    $editUrl = $user->isStudent() ? route('admin.students.edit', $user) : route('admin.users.edit', $user);
    $isSelf = $user->is(auth()->user());
@endphp

<x-dropdown variant="icon" icon="ellipsis-vertical" :label="'Actions for '.$user->name" {{ $attributes->merge(['data-account-menu' => $user->id]) }}>
    @if ($profileUrl)
        <x-dropdown.item :href="$profileUrl" icon="eye">View profile</x-dropdown.item>
    @endif
    <x-dropdown.item :href="$editUrl" icon="pencil">Edit</x-dropdown.item>

    @if ($isSelf)
        <x-dropdown.divider />
        <x-dropdown.label>This is your account</x-dropdown.label>
        <x-dropdown.item :href="route('account.edit')" icon="user-cog">Account settings</x-dropdown.item>
    @else
        <x-people.password-dialog :user="$user" />
        <x-people.status-dialog :user="$user" />
        <x-dropdown.divider />
        <x-people.delete-dialog :user="$user" />
    @endif
</x-dropdown>
