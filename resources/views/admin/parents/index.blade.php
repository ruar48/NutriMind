<x-layouts.app title="Parents & guardians">
    <x-page-header
        title="Parents & guardians"
        description="Parent and guardian accounts and the learners they follow in the Parent Portal."
        :breadcrumbs="[['label' => 'Dashboard', 'url' => route('admin.dashboard')], ['label' => 'Parents & guardians']]"
    >
        <x-slot:actions>
            <x-button icon="user-plus" :href="route('admin.users.create', ['role' => \App\Enums\UserRole::Parent->value])">Add parent</x-button>
        </x-slot:actions>
    </x-page-header>

    <x-credentials-alert :credentials="session('credentials', [])" />

    <x-filter-bar
        :action="route('admin.parents.index')"
        search-label="Search parents"
        search-placeholder="Search name, email or username"
        :reset-url="route('admin.parents.index')"
    >
        <x-form.select name="linked" label="Linked learners" hide-label placeholder="Any linked learners"
            :options="['yes' => 'Has linked learners', 'no' => 'No linked learners']" :value="request('linked')" :old="false" class="sm:w-56" />
        <x-form.select name="status" label="Status" hide-label placeholder="Any status"
            :options="['active' => 'Active', 'inactive' => 'Inactive']" :value="request('status')" :old="false" class="sm:w-44" />
    </x-filter-bar>

    @if ($parents->isEmpty())
        @if ($isFiltered)
            <x-empty-state variant="card" illustration="search" title="No parents match your filters"
                description="Try a different name or email, or clear the filters.">
                <x-slot:actions>
                    <x-button variant="secondary" icon="rotate-ccw" :href="route('admin.parents.index')">Clear filters</x-button>
                </x-slot:actions>
            </x-empty-state>
        @else
            <x-empty-state variant="card" illustration="forum" title="No parent accounts yet"
                description="Create parent and guardian accounts, then link each one to their children.">
                <x-slot:actions>
                    <x-button icon="user-plus" :href="route('admin.users.create', ['role' => \App\Enums\UserRole::Parent->value])">Add parent</x-button>
                </x-slot:actions>
            </x-empty-state>
        @endif
    @else
        <x-table caption="Parents and guardians">
            <x-slot:head>
                <x-table.th sort="name">Parent or guardian</x-table.th>
                <x-table.th>Mobile</x-table.th>
                <x-table.th>Linked learners</x-table.th>
                <x-table.th sort="last_login_at" default-direction="desc">Last sign-in</x-table.th>
                <x-table.th>Status</x-table.th>
                <x-table.th align="right"><span class="sr-only">Actions</span></x-table.th>
            </x-slot:head>

            @foreach ($parents as $parent)
                <x-table.row data-parent-row="{{ $parent->id }}">
                    <x-table.td primary>
                        <div class="flex items-center gap-3">
                            <x-avatar :name="$parent->name" size="sm" />
                            <div class="min-w-0">
                                <a href="{{ route('admin.parents.show', $parent) }}" class="block max-w-56 truncate hover:text-leaf-700 hover:underline dark:hover:text-leaf-300">{{ $parent->name }}</a>
                                <p class="max-w-56 truncate text-xs font-normal text-ink-500 dark:text-ink-400">{{ $parent->email }}</p>
                            </div>
                        </div>
                    </x-table.td>
                    <x-table.td class="whitespace-nowrap tabular-nums">{{ $parent->phone ?? '—' }}</x-table.td>
                    <x-table.td><x-people.learner-chips :learners="$parent->linkedStudents" /></x-table.td>
                    <x-table.td><x-people.relative-time :at="$parent->last_login_at" /></x-table.td>
                    <x-table.td><x-people.account-status :user="$parent" size="sm" /></x-table.td>
                    <x-table.td align="right"><x-people.account-menu :user="$parent" /></x-table.td>
                </x-table.row>
            @endforeach

            @if ($parents->hasPages())
                <x-slot:footer>{{ $parents->links() }}</x-slot:footer>
            @endif
        </x-table>
    @endif
</x-layouts.app>
