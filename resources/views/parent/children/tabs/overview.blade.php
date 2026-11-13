<x-learner.progress-overview :summary="$summary" />

<div class="grid grid-cols-1 gap-6 xl:grid-cols-3">
    <x-learner.weekly-trend :weeks="$summary->weeklyScores" class="xl:col-span-2" />
    <x-learner.rewards-shelf
        :rewards="$summary->rewards->take($rewardsLimit)"
        :description="$summary->rewardsEarned === 0 ? null : 'Latest of '.trans_choice(':count reward earned|:count rewards earned', $summary->rewardsEarned)"
    >
        @if ($summary->rewardsEarned > 0)
            <x-slot:actions>
                <x-button variant="ghost" size="sm" icon-trailing="arrow-right" :href="route('parent.children.show', [$student, 'tab' => 'rewards'])">All rewards</x-button>
            </x-slot:actions>
        @endif
    </x-learner.rewards-shelf>
</div>

<x-learner.subject-breakdown :subjects="$summary->subjectBreakdown" />

<div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
    <x-learner.recent-attempts :attempts="$summary->recentAttempts" :attempt-url="$attemptUrl" description="The latest quizzes your child submitted">
        @if ($summary->recentAttempts->isNotEmpty())
            <x-slot:actions>
                <x-button variant="ghost" size="sm" icon-trailing="arrow-right" :href="route('parent.children.show', [$student, 'tab' => 'quizzes'])">All attempts</x-button>
            </x-slot:actions>
        @endif
    </x-learner.recent-attempts>
    <x-learner.missions-list :missions="$summary->recentMissions" description="Your child’s latest mission progress in the game">
        @if ($summary->recentMissions->isNotEmpty())
            <x-slot:actions>
                <x-button variant="ghost" size="sm" icon-trailing="arrow-right" :href="route('parent.children.show', [$student, 'tab' => 'missions'])">All missions</x-button>
            </x-slot:actions>
        @endif
    </x-learner.missions-list>
</div>
