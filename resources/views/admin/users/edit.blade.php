@php
    [$listLabel, $listUrl, $profileUrl] = match ($user->role) {
        \App\Enums\UserRole::Teacher => ['Teachers', route('admin.teachers.index'), route('admin.teachers.show', $user)],
        \App\Enums\UserRole::Parent => ['Parents & guardians', route('admin.parents.index'), route('admin.parents.show', $user)],
        default => ['All users', route('admin.users.index'), null],
    };
    $isSelf = $user->is(auth()->user());
@endphp

<x-layouts.app :title="'Edit '.$user->name">
    <x-page-header
        :title="'Edit '.$user->name"
        description="Update the profile and sign-in details of this account."
        :breadcrumbs="array_values(array_filter([
            ['label' => $listLabel, 'url' => $listUrl],
            $profileUrl ? ['label' => $user->name, 'url' => $profileUrl] : null,
            ['label' => 'Edit'],
        ]))"
    >
        <x-slot:leading>
            <x-avatar :name="$user->name" size="xl" class="shadow-card ring-4 ring-white dark:ring-ink-900" />
        </x-slot:leading>
        <x-slot:meta>
            <x-status-badge :status="$user->role" />
            <x-people.account-status :user="$user" />
            @if ($isSelf)
                <x-badge color="grape">You</x-badge>
            @endif
        </x-slot:meta>
        @if ($profileUrl)
            <x-slot:actions>
                <x-button variant="secondary" icon="eye" :href="$profileUrl">View profile</x-button>
            </x-slot:actions>
        @endif
    </x-page-header>

    <x-credentials-alert :credentials="session('credentials', [])" />

    <form method="POST" action="{{ route('admin.users.update', $user) }}" class="flex flex-col gap-4" data-submit-once>
        @csrf
        @method('PUT')

        <x-form.section title="Profile" description="Shown to other staff, in reports and on the parent forum." columns="2">
            <x-form.input name="name" label="Full name" :value="$user->name" autocomplete="name" maxlength="255" required class="col-span-full" />
            <x-form.input name="email" type="email" label="Email address" :value="$user->email" autocomplete="email" prefix-icon="mail" maxlength="255" hint="They sign in with this address." required />
            <x-form.input name="phone" type="tel" label="Mobile number" :value="$user->phone" autocomplete="tel" inputmode="tel" prefix-icon="phone" placeholder="09XX XXX XXXX" maxlength="30" optional />
            <x-form.input name="username" label="Username" :value="$user->username" autocomplete="off" maxlength="30" hint="Letters, numbers, dashes and underscores." optional />
            <div class="flex flex-col gap-1.5">
                <span class="text-sm font-medium text-ink-800 dark:text-ink-100">Account type</span>
                <div class="flex h-11 items-center gap-2">
                    <x-status-badge :status="$user->role" />
                </div>
                <x-form.hint>The account type cannot be changed.</x-form.hint>
            </div>
        </x-form.section>

        <x-form.actions :sticky="false" :cancel="$profileUrl ?? route('admin.users.index')" submit="Save changes" />
    </form>

    <x-divider />

    <x-people.password-panel :user="$user" />

    @unless ($isSelf)
        <x-section title="Account status" description="Deactivate an account to stop sign-ins without losing its records." icon="shield" color="slate">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <p class="text-sm text-ink-600 dark:text-ink-300">
                    @if ($user->is_active)
                        This account is <strong class="font-semibold text-ink-900 dark:text-white">active</strong>@if ($user->last_login_at) and last signed in <x-people.relative-time :at="$user->last_login_at" />@endif.
                    @else
                        This account is <strong class="font-semibold text-ink-900 dark:text-white">deactivated</strong> and cannot sign in.
                    @endif
                </p>
                <div class="flex flex-wrap items-center gap-2">
                    <x-people.status-dialog :user="$user" button-variant="secondary" />
                    <x-people.delete-dialog :user="$user" button-variant="danger" button="Delete account" />
                </div>
            </div>
        </x-section>
    @endunless
</x-layouts.app>
