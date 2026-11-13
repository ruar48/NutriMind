@props([
    'user',
])

@php
    $role = $user->role instanceof \App\Enums\UserRole ? $user->role : null;
@endphp

<x-dropdown align="right" width="lg" :label="'Account menu for '.$user->name">
    <x-slot:trigger>
        <span class="flex items-center gap-2 rounded-xl p-1 transition hover:bg-ink-900/5 lg:pr-2 dark:hover:bg-white/8">
            <x-avatar :name="$user->name" size="sm" />
            <span class="hidden min-w-0 text-left lg:block">
                <span class="block max-w-40 truncate text-sm leading-tight font-semibold text-ink-900 dark:text-white">{{ $user->name }}</span>
                @if ($role)
                    <span class="block text-[11px] leading-tight text-ink-500 dark:text-ink-400">{{ $role->label() }}</span>
                @endif
            </span>
            <x-icon name="chevron-down" class="hidden size-4 text-ink-400 transition group-open/dropdown:rotate-180 lg:block" />
        </span>
    </x-slot:trigger>

    <div class="flex items-center gap-3 px-3 pt-2.5 pb-3">
        <x-avatar :name="$user->name" size="md" />
        <div class="min-w-0">
            <p class="truncate text-sm font-semibold text-ink-900 dark:text-white">{{ $user->name }}</p>
            @if (filled($user->email))
                <p class="truncate text-xs text-ink-500 dark:text-ink-400">{{ $user->email }}</p>
            @endif
            @if ($role)
                <x-badge :color="$role->color()" :icon="$role->icon()" size="sm" class="mt-1.5">{{ $role->label() }}</x-badge>
            @endif
        </div>
    </div>

    <x-dropdown.divider />

    @if (\Illuminate\Support\Facades\Route::has('account.edit'))
        <x-dropdown.item :href="route('account.edit')" icon="user-cog">Account settings</x-dropdown.item>
    @endif

    @if (\Illuminate\Support\Facades\Route::has('logout'))
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <x-dropdown.item type="submit" icon="log-out" danger>Sign out</x-dropdown.item>
        </form>
    @endif
</x-dropdown>
