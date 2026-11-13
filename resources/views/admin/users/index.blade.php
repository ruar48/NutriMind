@php
    $tabLabels = [
        \App\Enums\UserRole::Admin->value => 'Administrators',
        \App\Enums\UserRole::Teacher->value => 'Teachers',
        \App\Enums\UserRole::Parent->value => 'Parents',
        \App\Enums\UserRole::Student->value => 'Learners',
    ];
    $keptFilters = array_filter(['search' => request()->query('search'), 'status' => request()->query('status')], fn ($value) => is_string($value) && $value !== '');
    $profileUrl = fn (\App\Models\User $account): string => match ($account->role) {
        \App\Enums\UserRole::Student => route('admin.students.show', $account),
        \App\Enums\UserRole::Teacher => route('admin.teachers.show', $account),
        \App\Enums\UserRole::Parent => route('admin.parents.show', $account),
        default => route('admin.users.edit', $account),
    };
@endphp

<x-layouts.app title="All users">
    <x-page-header
        title="All users"
        description="Every account of the school. Learners are added and edited under Learners."
        :breadcrumbs="[['label' => 'Dashboard', 'url' => route('admin.dashboard')], ['label' => 'All users']]"
    >
        <x-slot:actions>
            <x-button variant="secondary" icon="backpack" :href="route('admin.students.create')">Add learner</x-button>
            <x-button icon="user-plus" :href="route('admin.users.create', array_filter(['role' => $role && $role->canAccessWeb() ? $role->value : null]))">New user</x-button>
        </x-slot:actions>
    </x-page-header>

    <x-credentials-alert :credentials="session('credentials', [])" />

    <div class="flex flex-col gap-4">
        <x-tabs label="Filter by role">
            <x-tab :href="route('admin.users.index', $keptFilters)" :active="$role === null" icon="users" :count="$counts['all']">All</x-tab>
            @foreach (\App\Enums\UserRole::cases() as $tabRole)
                <x-tab
                    :href="route('admin.users.index', ['role' => $tabRole->value, ...$keptFilters])"
                    :active="$role === $tabRole"
                    :icon="$tabRole->icon()"
                    :count="$counts[$tabRole->value]"
                >{{ $tabLabels[$tabRole->value] }}</x-tab>
            @endforeach
        </x-tabs>

        <x-filter-bar
            :action="route('admin.users.index')"
            search-label="Search users"
            search-placeholder="Search name, email, username or LRN"
            :reset-url="route('admin.users.index', array_filter(['role' => $role?->value]))"
            :preserve="['role', 'sort', 'direction']"
        >
            <x-form.select name="status" label="Status" hide-label placeholder="Any status"
                :options="['active' => 'Active', 'inactive' => 'Inactive']" :value="request('status')" :old="false" class="sm:w-44" />
        </x-filter-bar>
    </div>

    @if ($users->isEmpty())
        @if ($isFiltered)
            <x-empty-state variant="card" illustration="search" title="No users match your filters"
                description="Try a different name, email or username, or clear the filters.">
                <x-slot:actions>
                    <x-button variant="secondary" icon="rotate-ccw" :href="route('admin.users.index', array_filter(['role' => $role?->value]))">Clear filters</x-button>
                </x-slot:actions>
            </x-empty-state>
        @else
            <x-empty-state variant="card" icon="users"
                :title="$role ? 'No '.strtolower($tabLabels[$role->value]).' yet' : 'No accounts yet'"
                :description="$role === \App\Enums\UserRole::Student ? 'Add learners one by one or import a CSV class list.' : 'Create administrator, teacher and parent accounts to get your school started.'">
                <x-slot:actions>
                    @if ($role === \App\Enums\UserRole::Student)
                        <x-button icon="user-plus" :href="route('admin.students.create')">Add learner</x-button>
                    @else
                        <x-button icon="user-plus" :href="route('admin.users.create', array_filter(['role' => $role?->value]))">New user</x-button>
                    @endif
                </x-slot:actions>
            </x-empty-state>
        @endif
    @else
        <x-table caption="User accounts">
            <x-slot:head>
                <x-table.th sort="name">Name</x-table.th>
                <x-table.th>Email</x-table.th>
                <x-table.th>Role</x-table.th>
                <x-table.th>Status</x-table.th>
                <x-table.th sort="last_login_at" default-direction="desc">Last sign-in</x-table.th>
                <x-table.th sort="created_at" default-direction="desc">Created</x-table.th>
                <x-table.th align="right"><span class="sr-only">Actions</span></x-table.th>
            </x-slot:head>

            @foreach ($users as $account)
                <x-table.row data-user-row="{{ $account->id }}">
                    <x-table.td primary>
                        <div class="flex items-center gap-3">
                            <x-avatar :name="$account->name" size="sm" />
                            <div class="min-w-0">
                                <a href="{{ $profileUrl($account) }}" class="block max-w-56 truncate hover:text-leaf-700 hover:underline dark:hover:text-leaf-300">{{ $account->name }}</a>
                                <p class="max-w-56 truncate font-mono text-xs font-normal text-ink-500 dark:text-ink-400">{{ $account->username ?? '—' }}</p>
                            </div>
                            @if ($account->is(auth()->user()))
                                <x-badge color="grape" size="sm">You</x-badge>
                            @endif
                        </div>
                    </x-table.td>
                    <x-table.td class="max-w-64 truncate">{{ $account->email ?? '—' }}</x-table.td>
                    <x-table.td><x-status-badge :status="$account->role" size="sm" /></x-table.td>
                    <x-table.td><x-people.account-status :user="$account" size="sm" /></x-table.td>
                    <x-table.td>
                        @if ($account->isStudent())
                            <span class="whitespace-nowrap text-ink-400 dark:text-ink-500">Game account</span>
                        @else
                            <x-people.relative-time :at="$account->last_login_at" />
                        @endif
                    </x-table.td>
                    <x-table.td class="whitespace-nowrap tabular-nums">
                        <time datetime="{{ $account->created_at?->toDateString() }}">{{ $account->created_at?->format('M j, Y') }}</time>
                    </x-table.td>
                    <x-table.td align="right">
                        <x-people.account-menu :user="$account" />
                    </x-table.td>
                </x-table.row>
            @endforeach

            @if ($users->hasPages())
                <x-slot:footer>{{ $users->links() }}</x-slot:footer>
            @endif
        </x-table>
    @endif
</x-layouts.app>
