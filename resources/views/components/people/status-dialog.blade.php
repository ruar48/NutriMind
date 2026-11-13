@props([
    'user',
    'buttonVariant' => 'menu',
    'buttonSize' => 'sm',
])

@php
    $isDeactivating = $user->is_active;
    $message = match (true) {
        ! $isDeactivating => 'They can sign in again right away.',
        $user->isStudent() => 'The learner account is switched off until you reactivate it. Their game progress is kept.',
        default => 'They are signed out and cannot sign in until you reactivate the account. Their records are kept.',
    };
@endphp

<x-confirm-dialog
    :action="route('admin.users.status.update', $user)"
    method="PUT"
    :danger="$isDeactivating"
    :title="($isDeactivating ? 'Deactivate ' : 'Reactivate ').$user->name.'?'"
    :message="$message"
    :confirm="$isDeactivating ? 'Deactivate' : 'Reactivate'"
    :button="$isDeactivating ? 'Deactivate' : 'Reactivate'"
    :button-variant="$buttonVariant"
    :button-size="$buttonSize"
    :button-icon="$isDeactivating ? 'ban' : 'user-check'"
    :icon="$isDeactivating ? 'ban' : 'user-check'"
    {{ $attributes }}
>
    <x-slot:fields>
        <input type="hidden" name="is_active" value="{{ $isDeactivating ? '0' : '1' }}">
    </x-slot:fields>
</x-confirm-dialog>
