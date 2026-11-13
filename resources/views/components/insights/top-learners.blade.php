{{--
    Leaderboard podium of the learners in scope (LearningAnalytics::leaderboard()).
    Props: entries (rows), url (?Closure(User): ?string — the learner page).
--}}
@props([
    'entries' => [],
    'url' => null,
    'title' => 'Leaderboard',
    'description' => 'Most game XP earned so far',
])

@php
    $board = array_map(fn (array $entry): array => [
        'rank' => $entry['rank'],
        'name' => $entry['student']->name,
        'points' => $entry['points'],
        'level' => $entry['level'],
        'url' => $url instanceof \Closure ? $url($entry['student']) : null,
    ], $entries);
@endphp

<x-section :title="$title" :description="$description" icon="trophy" color="sun" {{ $attributes }}>
    <x-leaderboard :entries="$board" empty-text="Points appear here once learners play the game." />
</x-section>
