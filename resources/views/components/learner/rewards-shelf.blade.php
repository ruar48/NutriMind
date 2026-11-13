@props([
    'rewards' => [],
    'title' => 'Rewards',
    'description' => null,
])

@php
    $rewardCount = count($rewards);
    $description ??= $rewardCount === 0
        ? 'Badges, trophies and avatar items earned in the game'
        : trans_choice(':count reward earned|:count rewards earned', $rewardCount);
@endphp

<x-section :title="$title" :description="$description" icon="trophy" color="sun" {{ $attributes }}>
    @isset($actions)
        <x-slot:actions>{{ $actions }}</x-slot:actions>
    @endisset

    @if ($rewardCount === 0)
        <x-empty-state compact illustration="trophy" title="No rewards yet" description="Badges and trophies appear here when the learner earns them in the game." />
    @else
        <ul class="flex flex-wrap justify-center gap-x-4 gap-y-6 sm:justify-start">
            @foreach ($rewards as $reward)
                <li>
                    <x-reward-badge :reward="$reward" />
                </li>
            @endforeach
        </ul>
    @endif
</x-section>
