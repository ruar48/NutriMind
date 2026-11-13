@php
    $isFiltered = request()->filled('search') || $type !== null || $classroom !== null || $student !== null;
    $resetUrl = route('teacher.feedback.index');
@endphp

<x-layouts.app title="Feedback">
    <x-page-header
        eyebrow="Monitoring"
        title="Feedback"
        description="Praise, encouragement, guidance and concerns you sent to learners and their families."
        :breadcrumbs="[['label' => 'Dashboard', 'url' => route('teacher.dashboard')], ['label' => 'Feedback']]"
    >
        <x-slot:actions>
            <x-button icon="plus" :href="route('teacher.feedback.create', array_filter(['student' => $student?->getKey()]))">New feedback</x-button>
        </x-slot:actions>
    </x-page-header>

    <div class="flex flex-col gap-4">
        @if ($student)
            <x-alert variant="info" :title="'Feedback for '.$student->name">
                <span class="flex flex-wrap items-center gap-3">
                    Showing only the messages you sent to this learner.
                    <x-button variant="link" size="sm" :href="route('teacher.feedback.index')">Show all feedback</x-button>
                </span>
            </x-alert>
        @endif

        <x-filter-bar
            :action="route('teacher.feedback.index')"
            search-label="Search learners"
            search-placeholder="Search learner name or username"
            :reset-url="$resetUrl"
            :preserve="$student ? ['student'] : []"
        >
            <x-form.select
                name="classroom"
                label="Classroom"
                hide-label
                placeholder="All classrooms"
                :options="$classrooms->mapWithKeys(fn ($option) => [$option->getKey() => $option->name.($option->isArchived() ? ' (archived)' : '')])->all()"
                :value="$classroom?->getKey()"
                :old="false"
                class="sm:w-56"
            />
            <x-form.select
                name="type"
                label="Feedback type"
                hide-label
                placeholder="Any type"
                :options="\App\Enums\FeedbackType::options()"
                :value="$type?->value"
                :old="false"
                class="sm:w-44"
            />
        </x-filter-bar>
    </div>

    @if ($feedback->isEmpty())
        @if ($isFiltered)
            <x-empty-state variant="card" illustration="search" title="No feedback matches your filters" description="Try another learner, classroom or type, or clear the filters.">
                <x-slot:actions>
                    <x-button variant="secondary" icon="rotate-ccw" :href="$resetUrl">Clear filters</x-button>
                </x-slot:actions>
            </x-empty-state>
        @else
            <x-empty-state variant="card" icon="message-circle" title="No feedback yet" description="Celebrate progress and guide your learners. Families read visible feedback in the Parent Portal.">
                <x-slot:actions>
                    <x-button icon="plus" :href="route('teacher.feedback.create')">Write your first feedback</x-button>
                </x-slot:actions>
            </x-empty-state>
        @endif
    @else
        <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
            @foreach ($feedback as $item)
                <x-monitoring.feedback-card
                    :feedback="$item"
                    :learner-url="in_array((int) $item->student_id, $learnerIds, true) ? route('teacher.students.show', $item->student) : null"
                    :attempt-url="$item->quizAttempt ? $attemptUrl($item->quizAttempt) : null"
                />
            @endforeach
        </div>

        {{ $feedback->links() }}
    @endif
</x-layouts.app>
