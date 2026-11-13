@props([
    'credentials' => [],
    'downloadUrl' => null,
    'title' => 'Save these sign-in details',
])

@php
    $accounts = collect($credentials ?? [])
        ->filter(fn (mixed $credential): bool => is_array($credential))
        ->map(fn (array $credential): array => [
            'name' => (string) ($credential['name'] ?? ''),
            'username' => (string) ($credential['username'] ?? ''),
            'password' => (string) ($credential['password'] ?? ''),
        ])
        ->values();

    $allAccountsText = $accounts
        ->map(fn (array $account): string => $account['name'].' — '.$account['username'].' / '.$account['password'])
        ->implode("\n");
@endphp

@if ($accounts->isNotEmpty())
    <x-alert variant="warning" :title="$title" dismissible {{ $attributes->merge(['data-credentials-alert' => $accounts->count()]) }}>
        <p>Passwords are shown only once — copy or download them now.</p>

        <x-table :caption="trans_choice('Sign-in details for :count account|Sign-in details for :count accounts', $accounts->count())" class="mt-3">
            <x-slot:head>
                <x-table.th>Name</x-table.th>
                <x-table.th>Username</x-table.th>
                <x-table.th>Password</x-table.th>
                <x-table.th align="right" data-js-only><span class="sr-only">Copy</span></x-table.th>
            </x-slot:head>

            @foreach ($accounts as $account)
                <x-table.row data-credential-row>
                    <x-table.td primary class="min-w-32">{{ $account['name'] }}</x-table.td>
                    <x-table.td class="whitespace-nowrap">
                        <span class="font-mono text-ink-900 dark:text-ink-50">{{ $account['username'] }}</span>
                    </x-table.td>
                    <x-table.td class="whitespace-nowrap">
                        <span class="font-mono font-semibold tracking-wide text-ink-900 select-all dark:text-ink-50">{{ $account['password'] }}</span>
                    </x-table.td>
                    <x-table.td align="right" data-js-only>
                        <x-copy-button
                            :text="$account['username'].' / '.$account['password']"
                            size="xs"
                            :aria-label="'Copy sign-in details for '.$account['name']"
                        />
                    </x-table.td>
                </x-table.row>
            @endforeach
        </x-table>

        <div class="mt-3 flex flex-wrap items-center gap-2">
            <x-copy-button :text="$allAccountsText" label="Copy all" aria-label="Copy all sign-in details" data-copy-all />
            @if (filled($downloadUrl))
                <x-button variant="secondary" size="sm" icon="download" :href="$downloadUrl" class="no-underline!">Download CSV</x-button>
            @endif
        </div>
    </x-alert>
@endif
