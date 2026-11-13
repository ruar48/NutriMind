<x-layouts.app :title="$student ? 'Feedback for '.$student->name : 'New feedback'">
    <x-page-header
        eyebrow="Learner guidance"
        :title="$student ? 'Feedback for '.$student->name : 'New feedback'"
        description="Send praise, encouragement, guidance or a concern. Families read visible feedback in the Parent Portal."
        :breadcrumbs="array_values(array_filter([
            ['label' => 'Feedback', 'url' => route('teacher.feedback.index')],
            $student ? ['label' => $student->name, 'url' => route('teacher.students.show', $student)] : null,
            ['label' => 'New feedback'],
        ]))"
    >
        @if ($student)
            <x-slot:actions>
                <x-button variant="secondary" icon="users" :href="route('teacher.feedback.create')">Choose another learner</x-button>
            </x-slot:actions>
        @endif
    </x-page-header>

    @if ($student === null)
        @if ($learnerOptions->isEmpty())
            <x-empty-state
                variant="card"
                illustration="classroom"
                title="No learners to write to yet"
                description="Learners appear here once they are enrolled in one of your active classrooms."
            >
                <x-slot:actions>
                    <x-button icon="school" :href="route('teacher.classrooms.index')">Go to classrooms</x-button>
                </x-slot:actions>
            </x-empty-state>
        @else
            <x-section title="Choose a learner" description="Pick the learner first. Suggested messages are based on their results in your subjects." icon="users" color="sky" class="max-w-3xl">
                <form method="GET" action="{{ route('teacher.feedback.create') }}" class="flex flex-col gap-4 sm:flex-row sm:items-end">
                    <x-form.select
                        name="student"
                        label="Learner"
                        :options="$learnerOptions->all()"
                        placeholder="Choose a learner"
                        :old="false"
                        required
                        class="min-w-0 sm:flex-1"
                    />
                    <x-button type="submit" icon-trailing="arrow-right">Continue</x-button>
                </form>
            </x-section>
        @endif
    @else
        <div class="grid grid-cols-1 gap-6 xl:grid-cols-3">
            <x-monitoring.feedback-form
                :feedback="$feedback"
                :student="$student"
                :action="route('teacher.feedback.store')"
                :classroom-options="$classroomOptions"
                :attempt-options="$attemptOptions"
                :cancel="route('teacher.students.show', $student)"
                class="min-w-0 xl:col-span-2"
            />

            <aside class="flex min-w-0 flex-col gap-6">
                <x-monitoring.learner-snapshot :student="$student" :summary="$summary" />
                <x-monitoring.suggestions :suggestions="$suggestions" />
            </aside>
        </div>
    @endif
</x-layouts.app>
