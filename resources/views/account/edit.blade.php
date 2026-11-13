@php
    $role = $user->role instanceof \App\Enums\UserRole ? $user->role : null;
@endphp

<x-layouts.app title="Account settings">
    <x-page-header
        title="Account settings"
        description="Update your profile details and keep your password secure."
        :breadcrumbs="[['label' => 'Dashboard', 'url' => route('dashboard')], ['label' => 'Account settings']]"
    >
        <x-slot:leading>
            <x-avatar :name="$user->name" size="xl" class="ring-4 ring-white shadow-card dark:ring-ink-900" />
        </x-slot:leading>
        <x-slot:meta>
            @if ($role)
                <x-badge :color="$role->color()" :icon="$role->icon()">{{ $role->label() }}</x-badge>
            @endif
            @if ($user->last_login_at)
                <span class="inline-flex items-center gap-1.5 text-xs text-ink-500 dark:text-ink-400" title="{{ $user->last_login_at->format('M j, Y g:i A') }}">
                    <x-icon name="clock" class="size-3.5" />
                    Last signed in {{ $user->last_login_at->diffForHumans() }}
                </span>
            @endif
        </x-slot:meta>
    </x-page-header>

    <form method="POST" action="{{ route('account.update') }}" class="flex flex-col gap-4" data-submit-once data-animate style="--stagger: 1">
        @csrf
        @method('PUT')

        <x-form.section title="Profile" description="Your name and contact details appear to other staff, in reports and on the parent forum." columns="2">
            <x-form.input name="name" label="Full name" :value="$user->name" autocomplete="name" required class="col-span-full" />
            <x-form.input name="email" type="email" label="Email address" :value="$user->email" autocomplete="email" prefix-icon="mail" hint="You sign in with this address." required />
            <x-form.input name="phone" type="tel" label="Mobile number" :value="$user->phone" autocomplete="tel" inputmode="tel" prefix-icon="phone" placeholder="09XX XXX XXXX" optional />
        </x-form.section>

        <x-form.actions :sticky="false" submit="Save profile" />
    </form>

    <x-divider />

    <form method="POST" action="{{ route('account.password.update') }}" class="flex flex-col gap-4" data-submit-once data-animate style="--stagger: 2">
        @csrf
        @method('PUT')

        <x-form.section title="Password" description="Use at least 8 characters. A short phrase of a few words is easy to remember and hard to guess.">
            <x-form.password name="current_password" label="Current password" bag="updatePassword" autocomplete="current-password" required />
            <x-form.password name="password" label="New password" bag="updatePassword" autocomplete="new-password" required />
            <x-form.password name="password_confirmation" label="Confirm new password" bag="updatePassword" autocomplete="new-password" required />
        </x-form.section>

        <x-form.actions :sticky="false" submit="Update password" submit-icon="key-round" />
    </form>
</x-layouts.app>
