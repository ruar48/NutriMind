@props([
    'user',
])

@php
    $isSelf = $user->is(auth()->user());
@endphp

<x-section
    title="Password"
    :description="$user->isStudent() ? 'The password the learner types in the NutriMind game.' : 'Reset the password when the account holder forgot it.'"
    icon="key-round"
    color="sun"
    {{ $attributes->merge(['id' => 'password']) }}
>
    @if ($isSelf)
        <x-alert variant="info" title="This is your account">
            Change your own password in <a href="{{ route('account.edit') }}" class="font-semibold underline underline-offset-2">Account settings</a> — it asks for your current password first.
        </x-alert>
    @else
        <div class="flex flex-col gap-6">
            <div class="flex flex-col gap-3 rounded-xl bg-ink-50 p-4 ring-1 ring-ink-900/5 ring-inset sm:flex-row sm:items-center sm:justify-between dark:bg-white/3 dark:ring-white/8">
                <div class="min-w-0">
                    <p class="text-sm font-semibold text-ink-900 dark:text-ink-50">Generate a new password</p>
                    <p class="mt-0.5 text-sm text-ink-500 dark:text-ink-400">
                        {{ $user->isStudent() ? 'Easy to type, like “mango-4821”.' : 'Twelve letters and digits.' }} It is shown to you once, ready to hand over.
                    </p>
                </div>
                <x-people.password-dialog :user="$user" button-variant="secondary" button="Generate password" class="shrink-0" />
            </div>

            <x-divider label="or set one yourself" />

            <form method="POST" action="{{ route('admin.users.password.update', $user) }}" class="flex flex-col gap-4" data-submit-once>
                @csrf
                @method('PUT')

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <x-form.password name="password" label="New password" bag="resetPassword" autocomplete="new-password" hint="At least 8 characters with letters and numbers." required />
                    <x-form.password name="password_confirmation" label="Confirm new password" bag="resetPassword" autocomplete="new-password" required />
                </div>

                <div class="flex justify-end">
                    <x-button type="submit" variant="secondary" icon="check">Set password</x-button>
                </div>
            </form>
        </div>
    @endif
</x-section>
