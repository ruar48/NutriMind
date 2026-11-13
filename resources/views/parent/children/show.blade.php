@php
    $firstName = \Illuminate\Support\Str::before(trim($student->name).' ', ' ');
    $missionCount = $summary->missionsCompleted + $summary->missionsInProgress;
    $tabUrl = fn (string $tabName): string => route('parent.children.show', $tabName === 'overview' ? $student : [$student, 'tab' => $tabName]);
@endphp

<x-layouts.app :title="$student->name" description="Your child’s learning progress in NutriMind.">
    <x-page-header
        :title="$student->name"
        description="Your child’s learning progress in the NutriMind game"
        :breadcrumbs="[
            ['label' => 'Dashboard', 'url' => route('parent.dashboard')],
            ['label' => 'My children', 'url' => route('parent.children.index')],
            ['label' => $student->name],
        ]"
    >
        <x-slot:meta>
            @if ($relationship)
                <x-badge color="carrot" icon="house-heart"><span class="sr-only">Your relationship:</span> {{ $relationship->label() }}</x-badge>
            @endif
            @unless ($student->is_active)
                <x-badge color="slate" icon="ban">Game account inactive</x-badge>
            @endunless
        </x-slot:meta>

        @if ($canViewFeedback && $unreadFeedbackCount > 0 && $tab !== 'feedback')
            <x-slot:actions>
                <x-button icon="message-circle" :href="$tabUrl('feedback')">
                    {{ trans_choice('Read :count new message|Read :count new messages', $unreadFeedbackCount) }}
                </x-button>
            </x-slot:actions>
        @endif
    </x-page-header>

    <x-learner.profile-header :summary="$summary" data-animate style="--stagger: 1" />

    <x-tabs label="Progress sections">
        <x-tab :href="$tabUrl('overview')" :active="$tab === 'overview'" icon="layout-dashboard">Overview</x-tab>
        <x-tab :href="$tabUrl('quizzes')" :active="$tab === 'quizzes'" icon="clipboard-check" :count="$attemptCount">Quizzes</x-tab>
        <x-tab :href="$tabUrl('missions')" :active="$tab === 'missions'" icon="flag" :count="$missionCount">Missions</x-tab>
        @if ($canViewFeedback)
            <x-tab :href="$tabUrl('feedback')" :active="$tab === 'feedback'" icon="message-circle" :count="$unreadFeedbackCount > 0 ? $unreadFeedbackCount : null">
                Feedback
                @if ($unreadFeedbackCount > 0)
                    <span class="sr-only">({{ $unreadFeedbackCount }} unread)</span>
                @endif
            </x-tab>
        @endif
        <x-tab :href="$tabUrl('rewards')" :active="$tab === 'rewards'" icon="trophy" :count="$summary->rewardsEarned">Rewards</x-tab>
    </x-tabs>

    @if ($tab === 'quizzes')
        @include('parent.children.tabs.quizzes', [
            'student' => $student,
            'firstName' => $firstName,
            'summary' => $summary,
            'attempts' => $attempts,
            'attemptUrl' => $attemptUrl,
            'subjectOptions' => $subjectOptions,
            'filters' => $filters,
            'isFiltered' => $isFiltered,
        ])
    @elseif ($tab === 'missions')
        @include('parent.children.tabs.missions', [
            'student' => $student,
            'firstName' => $firstName,
            'summary' => $summary,
            'missions' => $missions,
            'missionPoints' => $missionPoints,
            'subjectOptions' => $subjectOptions,
            'statusOptions' => $statusOptions,
            'filters' => $filters,
            'isFiltered' => $isFiltered,
        ])
    @elseif ($tab === 'feedback')
        @include('parent.children.tabs.feedback', [
            'student' => $student,
            'firstName' => $firstName,
            'feedback' => $feedback,
            'newFeedbackIds' => $newFeedbackIds,
            'feedbackAttemptUrl' => $feedbackAttemptUrl,
            'filters' => $filters,
            'isFiltered' => $isFiltered,
        ])
    @elseif ($tab === 'rewards')
        @include('parent.children.tabs.rewards', [
            'student' => $student,
            'firstName' => $firstName,
            'earnedRewards' => $earnedRewards,
            'lockedRewards' => $lockedRewards,
            'filters' => $filters,
            'isFiltered' => $isFiltered,
        ])
    @else
        @include('parent.children.tabs.overview', [
            'student' => $student,
            'summary' => $summary,
            'attemptUrl' => $attemptUrl,
            'rewardsLimit' => \App\Http\Controllers\Guardian\ChildController::OVERVIEW_REWARDS,
        ])
    @endif
</x-layouts.app>
