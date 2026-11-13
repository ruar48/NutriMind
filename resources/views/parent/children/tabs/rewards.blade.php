@php
    $resetUrl = route('parent.children.show', [$student, 'tab' => 'rewards']);
@endphp

<x-filter-bar
    :action="route('parent.children.show', $student)"
    :search="false"
    :reset-url="$resetUrl"
    :preserve="['tab']"
>
    <x-form.select
        name="reward_type"
        label="Type of reward"
        hide-label
        placeholder="All rewards"
        :options="\App\Enums\RewardType::options()"
        :value="$filters['reward_type']"
        :old="false"
        class="sm:w-48"
    />
</x-filter-bar>

<x-learner.rewards-shelf
    :rewards="$earnedRewards"
    title="Rewards earned"
    :description="$earnedRewards->isEmpty() ? 'Badges, trophies and avatar items '.$firstName.' earns in the game' : trans_choice(':count reward earned|:count rewards earned', $earnedRewards->count())"
    data-child-rewards-earned
/>

<x-section
    title="Still to earn"
    :description="'Rewards '.$firstName.' can still unlock by playing NutriMind'"
    icon="lock"
    color="slate"
    data-child-rewards-locked
>
    @if ($lockedRewards->isEmpty())
        @if ($isFiltered)
            <x-empty-state compact icon="search" title="Nothing left of this type" description="Every reward of this type is already earned, or none is available yet.">
                <x-slot:actions>
                    <x-button variant="secondary" size="sm" icon="rotate-ccw" :href="$resetUrl">Show all rewards</x-button>
                </x-slot:actions>
            </x-empty-state>
        @else
            <x-empty-state compact illustration="trophy" title="Every reward collected" :description="'Amazing! '.$firstName.' has earned every reward the game offers right now.'" />
        @endif
    @else
        <ul class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-3">
            @foreach ($lockedRewards as $reward)
                <li class="flex items-start gap-3 rounded-xl p-3 ring-1 ring-ink-900/8 ring-inset dark:ring-white/10" data-locked-reward="{{ $reward->getKey() }}">
                    <x-reward-badge :reward="$reward" :earned="false" size="sm" />
                    <div class="min-w-0 flex-1 pt-1">
                        <x-status-badge :status="$reward->type" size="sm" />
                        @if (filled($reward->criteria) || filled($reward->description))
                            <p class="mt-1.5 text-sm text-ink-600 dark:text-ink-300">{{ $reward->criteria ?: $reward->description }}</p>
                        @endif
                        @if ($reward->points_required)
                            <p class="mt-1 inline-flex items-center gap-1 text-xs font-semibold text-sun-800 tabular-nums dark:text-sun-300">
                                <x-icon name="sparkles" class="size-3.5" />
                                Unlocks at {{ number_format($reward->points_required) }} XP
                            </p>
                        @endif
                    </div>
                </li>
            @endforeach
        </ul>
    @endif
</x-section>
