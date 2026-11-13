@php
    $isFiltered = collect($filters)->contains(fn ($value) => $value !== null);
@endphp

<x-layouts.app title="Rewards">
    <x-page-header
        eyebrow="Game content"
        title="Rewards"
        description="Badges, trophies and avatar items learners earn in the game."
        :breadcrumbs="[['label' => 'Dashboard', 'url' => route('admin.dashboard')], ['label' => 'Rewards']]"
    >
        <x-slot:actions>
            <x-button icon="plus" :href="route('admin.rewards.create')">New reward</x-button>
        </x-slot:actions>
    </x-page-header>

    <x-filter-bar
        :action="route('admin.rewards.index')"
        search-label="Search rewards"
        search-placeholder="Search name or how to earn it"
        :reset-url="route('admin.rewards.index')"
        data-animate
        style="--stagger: 1"
    >
        <x-form.select name="type" label="Reward type" hide-label placeholder="All types" :options="\App\Enums\RewardType::options()" :value="$filters['type']" :old="false" class="sm:w-40" />
        <x-form.select name="status" label="Status" hide-label placeholder="Any status" :options="['active' => 'Active', 'inactive' => 'Inactive']" :value="$filters['status']" :old="false" class="sm:w-36" />
    </x-filter-bar>

    @if ($rewards->isEmpty())
        @if ($isFiltered)
            <x-empty-state variant="card" illustration="search" title="No rewards match your filters"
                description="Try another type or name, or clear the filters.">
                <x-slot:actions>
                    <x-button variant="secondary" icon="rotate-ccw" :href="route('admin.rewards.index')">Clear filters</x-button>
                </x-slot:actions>
            </x-empty-state>
        @else
            <x-empty-state variant="dashed" illustration="trophy" title="No rewards yet"
                description="Create badges and trophies that celebrate learners’ progress.">
                <x-slot:actions>
                    <x-button icon="plus" :href="route('admin.rewards.create')">New reward</x-button>
                </x-slot:actions>
            </x-empty-state>
        @endif
    @else
        <ul class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3" aria-label="Rewards">
            @foreach ($rewards as $reward)
                <li class="min-w-0" data-animate style="--stagger: {{ min($loop->iteration + 1, 8) }}">
                    <x-card data-reward-card="{{ $reward->id }}">
                        <div class="flex flex-col items-center text-center">
                            <x-reward-badge :reward="$reward" size="lg" @class(['w-full!', 'opacity-60' => ! $reward->is_active]) />
                            <div class="mt-3 flex flex-wrap items-center justify-center gap-2">
                                <x-status-badge :status="$reward->type" size="sm" />
                                <x-badge :color="$reward->is_active ? 'leaf' : 'slate'" dot size="sm">{{ $reward->is_active ? 'Active' : 'Inactive' }}</x-badge>
                            </div>
                            <p class="mt-3 line-clamp-2 min-h-10 text-sm text-ink-600 dark:text-ink-300">{{ $reward->criteria ?? $reward->description ?? 'No earning rule yet.' }}</p>
                        </div>

                        <dl class="mt-4 grid grid-cols-2 gap-3">
                            <div class="rounded-xl bg-ink-50/70 px-3 py-2 ring-1 ring-ink-900/5 ring-inset dark:bg-white/3 dark:ring-white/8">
                                <dt class="text-xs text-ink-500 dark:text-ink-400">Points required</dt>
                                <dd class="mt-0.5 font-display text-lg font-bold text-ink-950 tabular-nums dark:text-white">{{ $reward->points_required ? number_format($reward->points_required).' XP' : '—' }}</dd>
                            </div>
                            <div class="rounded-xl bg-ink-50/70 px-3 py-2 ring-1 ring-ink-900/5 ring-inset dark:bg-white/3 dark:ring-white/8">
                                <dt class="text-xs text-ink-500 dark:text-ink-400">Times earned</dt>
                                <dd class="mt-0.5 font-display text-lg font-bold text-ink-950 tabular-nums dark:text-white">{{ number_format($reward->students_count) }}</dd>
                            </div>
                        </dl>

                        <x-slot:footer>
                            <div class="flex items-center justify-end gap-2">
                                @if ($reward->students_count > 0)
                                    <x-button variant="ghost" size="sm" icon="trash-2" disabled title="Learners have already earned this reward.">Delete</x-button>
                                @else
                                    <x-confirm-dialog
                                        :action="route('admin.rewards.destroy', $reward)"
                                        :title="'Delete “'.$reward->name.'”?'"
                                        message="The reward is removed from the game. This cannot be undone."
                                        confirm="Delete reward"
                                        button="Delete"
                                        button-variant="ghost"
                                    />
                                @endif
                                <x-button variant="secondary" size="sm" icon="pencil" :href="route('admin.rewards.edit', $reward)">Edit</x-button>
                            </div>
                        </x-slot:footer>
                    </x-card>
                </li>
            @endforeach
        </ul>

        @if ($rewards->hasPages())
            <div>{{ $rewards->links() }}</div>
        @endif
    @endif
</x-layouts.app>
