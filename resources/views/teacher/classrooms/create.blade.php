<x-layouts.app title="New classroom">
    <x-page-header
        eyebrow="Teaching"
        title="New classroom"
        description="Set up a class for one of your sections. Learners join it in the NutriMind game with the class code you get next."
        :breadcrumbs="[['label' => 'Classrooms', 'url' => route('teacher.classrooms.index')], ['label' => 'New classroom']]"
    />

    @if ($schoolYear === null)
        <x-empty-state
            variant="card"
            illustration="hourglass"
            title="No active school year yet"
            description="Classrooms belong to a school year. Ask your school administrator to set up and activate the current school year, then come back to create your classes."
        >
            <x-slot:actions>
                <x-button variant="secondary" icon="arrow-left" :href="route('teacher.classrooms.index')">Back to classrooms</x-button>
            </x-slot:actions>
        </x-empty-state>
    @else
        <x-classrooms.form
            :classroom="$classroom"
            :action="route('teacher.classrooms.store')"
            :cancel="route('teacher.classrooms.index')"
            :school-year="$schoolYear"
            :subject-options="$subjectOptions"
            :subject-short-names="$subjectShortNames"
            data-animate
        />
    @endif
</x-layouts.app>
