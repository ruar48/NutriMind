@props([
    'user',
    'size' => 'md',
])

<x-badge :color="$user->is_active ? 'leaf' : 'slate'" :size="$size" dot {{ $attributes->merge(['data-account-status' => $user->is_active ? 'active' : 'inactive']) }}>
    {{ $user->is_active ? 'Active' : 'Inactive' }}
</x-badge>
