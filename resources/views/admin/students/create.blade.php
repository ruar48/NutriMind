<x-layouts.app title="Add learner">
    <x-page-header
        title="Add learner"
        description="Create a learner account for the NutriMind game. Leave the username or password empty to generate them."
        :breadcrumbs="[['label' => 'Learners', 'url' => route('admin.students.index')], ['label' => 'Add learner']]"
    >
        <x-slot:actions>
            <x-button variant="secondary" icon="file-up" :href="route('admin.students.import.create')">Import a CSV instead</x-button>
        </x-slot:actions>
    </x-page-header>

    <form method="POST" action="{{ route('admin.students.store') }}" class="flex flex-col gap-8" data-submit-once>
        @csrf

        <x-form.section title="Learner profile" description="As written in the learner’s school records (LRN from the DepEd LIS)." columns="2">
            <x-form.input name="name" label="Full name" autocomplete="off" maxlength="255" placeholder="Juan Dela Cruz" required class="col-span-full" />
            <x-form.input name="lrn" label="LRN" inputmode="numeric" maxlength="14" placeholder="136742210001" hint="12-digit Learner Reference Number." optional />
            <x-form.select name="grade_level" label="Grade level" :options="\App\Enums\GradeLevel::options()" placeholder="Choose a grade" required />
            <x-form.select name="sex" label="Sex" :options="\App\Enums\Sex::options()" placeholder="Not specified" optional />
            <x-form.input name="birthdate" type="date" label="Birthdate" :max="now()->subDay()->toDateString()" optional />
        </x-form.section>

        <x-form.section title="Game sign-in" description="What the learner types in the NutriMind game. Generated details are shown to you once, ready to print or copy." columns="2">
            <x-form.input name="username" label="Username" autocomplete="off" maxlength="30" hint="Optional. Generated from the name when empty (e.g. jdelacruz)." />
            <div class="hidden @lg:block" aria-hidden="true"></div>
            <x-form.password name="password" label="Password" autocomplete="new-password" hint="Optional. Generated when empty (e.g. mango-4821)." />
            <x-form.password name="password_confirmation" label="Confirm password" autocomplete="new-password" />
        </x-form.section>

        <x-form.section title="Classrooms" description="Optional. Enroll the learner in classrooms of the current school year that match their grade.">
            <x-people.classroom-picker :classrooms="$classrooms" :grade="old('grade_level')" filter-by-grade class="col-span-full" />
        </x-form.section>

        <x-form.actions :cancel="route('admin.students.index')" submit="Add learner" submit-icon="user-plus">
            <x-icon name="info" class="size-4" />
            Link parents or guardians from their profile after adding the learner.
        </x-form.actions>
    </form>
</x-layouts.app>
