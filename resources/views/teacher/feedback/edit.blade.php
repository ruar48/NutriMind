<x-layouts.app :title="'Edit feedback for '.$student->name">
    <x-page-header
        eyebrow="Learner guidance"
        :title="'Edit feedback for '.$student->name"
        :description="'Sent '.$feedback->created_at->format('M j, Y g:i A').($feedback->is_visible_to_guardians ? ($feedback->read_at ? ' · Seen by a parent '.$feedback->read_at->diffForHumans() : ' · Not seen by a parent yet') : ' · Private note')"
        :breadcrumbs="[
            ['label' => 'Feedback', 'url' => route('teacher.feedback.index')],
            ['label' => $student->name, 'url' => route('teacher.feedback.index', ['student' => $student->getKey()])],
            ['label' => 'Edit'],
        ]"
    >
        <x-slot:meta>
            <x-status-badge :status="$feedback->type" />
        </x-slot:meta>
        <x-slot:actions>
            <x-confirm-dialog
                :action="route('teacher.feedback.destroy', $feedback)"
                :title="'Delete this feedback for '.$student->name.'?'"
                message="The message is removed for you and for the learner’s parents and guardians. This cannot be undone."
                button="Delete"
                button-variant="secondary"
                button-size="md"
                confirm="Delete feedback"
            />
        </x-slot:actions>
    </x-page-header>

    <div class="grid grid-cols-1 gap-6 xl:grid-cols-3">
        <x-monitoring.feedback-form
            :feedback="$feedback"
            :student="$student"
            :action="route('teacher.feedback.update', $feedback)"
            method="PUT"
            :classroom-options="$classroomOptions"
            :attempt-options="$attemptOptions"
            :classroom-unavailable="$classroomUnavailable"
            :attempt-unavailable="$attemptUnavailable"
            submit="Save changes"
            submit-icon="check"
            :cancel="route('teacher.feedback.index')"
            class="min-w-0 xl:col-span-2"
        />

        <aside class="flex min-w-0 flex-col gap-6">
            <x-monitoring.learner-snapshot :student="$student" :summary="$summary" />
            @if ($canViewLearner)
                <x-monitoring.suggestions :suggestions="$suggestions" />
            @endif
        </aside>
    </div>
</x-layouts.app>
