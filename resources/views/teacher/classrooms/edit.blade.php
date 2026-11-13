<x-layouts.app :title="'Edit '.$classroom->name">
    <x-page-header
        eyebrow="Teaching"
        title="Edit classroom"
        :description="$classroom->name.' · '.$classroom->grade_level->label().' · SY '.$classroom->schoolYear->name"
        :breadcrumbs="[
            ['label' => 'Classrooms', 'url' => route('teacher.classrooms.index')],
            ['label' => $classroom->name, 'url' => route('teacher.classrooms.show', $classroom)],
            ['label' => 'Edit'],
        ]"
    >
        <x-slot:leading>
            <x-classrooms.subject-tile :subject="$classroom->subject" size="lg" />
        </x-slot:leading>
    </x-page-header>

    <x-classrooms.form
        :classroom="$classroom"
        :action="route('teacher.classrooms.update', $classroom)"
        :cancel="route('teacher.classrooms.show', $classroom)"
        :school-year="$classroom->schoolYear"
        :subject-short-name="$subjectShortName"
        data-animate
    />
</x-layouts.app>
