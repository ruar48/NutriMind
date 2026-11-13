@props([
    'user',
    'deleteBlockedReason' => null,
])

@php
    $isSelf = $user->is(auth()->user());
@endphp

<x-section title="Account" description="Sign-in details and account actions." icon="user-cog" color="slate" {{ $attributes }}>
    <x-definition-list>
        <x-detail-row label="Status" icon="circle-dot"><x-people.account-status :user="$user" /></x-detail-row>
        @if ($user->isStudent())
            <x-detail-row label="Username" icon="id-card">
                @if (filled($user->username))
                    <span class="font-mono">{{ $user->username }}</span>
                @endif
            </x-detail-row>
            <x-detail-row label="Signs in to" icon="gamepad-2" value="The NutriMind game" />
        @else
            <x-detail-row label="Email address" icon="mail" :value="$user->email" />
            <x-detail-row label="Username" icon="id-card" :value="$user->username" />
            <x-detail-row label="Mobile number" icon="phone" :value="$user->phone" />
            <x-detail-row label="Last sign-in" icon="log-in"><x-people.relative-time :at="$user->last_login_at" /></x-detail-row>
        @endif
        <x-detail-row label="Account created" icon="calendar" :value="$user->created_at?->format('M j, Y')" />
    </x-definition-list>

    <x-slot:footer>
        @if ($isSelf)
            <p class="flex items-center gap-2 text-sm text-ink-600 dark:text-ink-300">
                <x-icon name="info" class="size-4 shrink-0" />
                <span>This is your account. Manage it in <a href="{{ route('account.edit') }}" class="font-semibold text-leaf-700 underline underline-offset-2 dark:text-leaf-300">Account settings</a>.</span>
            </p>
        @else
            <div class="flex flex-col gap-3">
                <div class="flex flex-wrap items-center gap-2">
                    <x-people.password-dialog :user="$user" button-variant="secondary" />
                    <x-people.status-dialog :user="$user" button-variant="secondary" />
                    @if ($deleteBlockedReason === null)
                        <x-people.delete-dialog :user="$user" button-variant="ghost" button="Delete account" />
                    @endif
                </div>
                @if ($deleteBlockedReason !== null)
                    <p class="flex items-start gap-2 text-xs text-ink-500 dark:text-ink-400" data-delete-blocked>
                        <x-icon name="lock" class="mt-0.5 size-3.5 shrink-0" />
                        <span>{{ $deleteBlockedReason }}</span>
                    </p>
                @endif
            </div>
        @endif
    </x-slot:footer>
</x-section>
