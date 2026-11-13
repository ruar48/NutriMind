@php
    $resetUrl = route('parent.children.show', [$student, 'tab' => 'missions']);
@endphp

<div class="grid grid-cols-1 gap-4 sm:grid-cols-3" data-mission-totals>
    <x-stat-card
        label="Missions completed"
        :value="number_format($summary->missionsCompleted)"
        icon="flag"
        color="carrot"
        hint="Finished in the game"
        :count-up="$summary->missionsCompleted"
        data-animate
        style="--stagger: 1"
    />
    <x-stat-card
        label="In progress"
        :value="number_format($summary->missionsInProgress)"
        icon="timer"
        color="sky"
        hint="Started, not finished yet"
        :count-up="$summary->missionsInProgress"
        data-animate
        style="--stagger: 2"
    />
    <x-stat-card
        label="Mission XP"
        :value="number_format($missionPoints).' XP'"
        icon="sparkles"
        color="sun"
        hint="Points earned from missions"
        :count-up="$missionPoints"
        count-suffix=" XP"
        data-animate
        style="--stagger: 3"
    />
</div>

<x-filter-bar
    :action="route('parent.children.show', $student)"
    :search="false"
    :reset-url="$resetUrl"
    :preserve="['tab']"
>
    <x-form.select
        name="status"
        label="Status"
        hide-label
        placeholder="Any status"
        :options="$statusOptions"
        :value="$filters['status']"
        :old="false"
        class="sm:w-44"
    />
    <x-form.select
        name="subject"
        label="Subject"
        hide-label
        placeholder="All subjects"
        :options="$subjectOptions"
        :value="$filters['subject']"
        :old="false"
        class="sm:w-56"
    />
</x-filter-bar>

@if ($missions->isEmpty())
    @if ($isFiltered)
        <x-empty-state variant="card" illustration="search" title="No missions match your filters" description="Try another status or subject, or clear the filters.">
            <x-slot:actions>
                <x-button variant="secondary" icon="rotate-ccw" :href="$resetUrl">Clear filters</x-button>
            </x-slot:actions>
        </x-empty-state>
    @else
        <x-empty-state
            variant="card"
            icon="flag"
            title="No missions started yet"
            :description="'Missions appear here once '.$firstName.' starts them in the NutriMind game.'"
        />
    @endif
@else
    <x-learner.missions-list
        :missions="$missions"
        title="Missions"
        :description="trans_choice(':count mission|:count missions', $missions->total()).($isFiltered ? ' match your filters' : ' started or completed')"
        data-child-missions
    />

    @if ($missions->hasPages())
        {{ $missions->links() }}
    @endif
@endif
