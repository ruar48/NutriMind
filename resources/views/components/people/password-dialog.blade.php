@props([
    'user',
    'buttonVariant' => 'menu',
    'buttonSize' => 'sm',
    'button' => 'Reset password',
])

<x-confirm-dialog
    :action="route('admin.users.password.update', $user)"
    method="PUT"
    :danger="false"
    :title="'Generate a new password for '.$user->name.'?'"
    :message="$user->isStudent()
        ? 'A new game password is created and shown to you once. The old password stops working.'
        : 'A new password is created and shown to you once. The old password stops working, and remembered sign-ins end.'"
    confirm="Generate password"
    :button="$button"
    :button-variant="$buttonVariant"
    :button-size="$buttonSize"
    button-icon="key-round"
    icon="key-round"
    {{ $attributes }}
>
    <x-slot:fields>
        <input type="hidden" name="generate" value="1">
    </x-slot:fields>
</x-confirm-dialog>
