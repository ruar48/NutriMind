@php
    $profile = $student->studentProfile;
@endphp

<x-layouts.app :title="'Edit '.$student->name">
    <x-page-header
        :title="'Edit '.$student->name"
        description="Update the learner’s profile, game username and classrooms of the current school year."
        :breadcrumbs="[
            ['label' => 'Learners', 'url' => route('admin.students.index')],
            ['label' => $student->name, 'url' => route('admin.students.show', $student)],
            ['label' => 'Edit'],
        ]"
    >
        <x-slot:leading>
            <x-avatar :name="$student->name" size="xl" class="shadow-card ring-4 ring-white dark:ring-ink-900" />
        </x-slot:leading>
        <x-slot:meta>
            <x-status-badge :status="$student->role" />
            @if ($profile)
                <x-status-badge :status="$profile->grade_level" />
            @endif
            <x-people.account-status :user="$student" />
        </x-slot:meta>
        <x-slot:actions>
            <x-button variant="secondary" icon="eye" :href="route('admin.students.show', $student)">View profile</x-button>
        </x-slot:actions>
    </x-page-header>

    <x-credentials-alert :credentials="session('credentials', [])" />

    <form method="POST" action="{{ route('admin.students.update', $student) }}" class="flex flex-col gap-8" data-submit-once>
        @csrf
        @method('PUT')

        <x-form.section title="Learner profile" description="As written in the learner’s school records (LRN from the DepEd LIS)." columns="2">
            <x-form.input name="name" label="Full name" :value="$student->name" autocomplete="off" maxlength="255" required class="col-span-full" />
            <x-form.input name="lrn" label="LRN" :value="$profile?->lrn" inputmode="numeric" maxlength="14" hint="12-digit Learner Reference Number." optional />
            <x-form.select name="grade_level" label="Grade level" :options="\App\Enums\GradeLevel::options()" :value="$profile?->grade_level" placeholder="Choose a grade"
                hint="Changing the grade requires leaving classrooms of the old grade." required />
            <x-form.select name="sex" label="Sex" :options="\App\Enums\Sex::options()" :value="$profile?->sex" placeholder="Not specified" optional />
            <x-form.input name="birthdate" type="date" label="Birthdate" :value="$profile?->birthdate" :max="now()->subDay()->toDateString()" optional />
        </x-form.section>

        <x-form.section title="Game sign-in" description="The username the learner types in the NutriMind game. Reset the password below." columns="2">
            <x-form.input name="username" label="Username" :value="$student->username" autocomplete="off" maxlength="30" hint="Letters, numbers, dashes and underscores." required />
        </x-form.section>

        <x-form.section title="Classrooms" description="Classrooms of the current school year. Unticking one removes the learner from it; archived and past classrooms are kept.">
            <x-people.classroom-picker :classrooms="$classrooms" :selected="$enrolledClassroomIds" class="col-span-full" />
        </x-form.section>

        <x-form.actions :sticky="false" :cancel="route('admin.students.show', $student)" submit="Save changes" />
    </form>

    <x-divider />

    <x-people.password-panel :user="$student" />

    <x-section title="Account status" description="Deactivate the learner account to stop it without losing progress." icon="shield" color="slate">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <p class="text-sm text-ink-600 dark:text-ink-300">
                This learner account is <strong class="font-semibold text-ink-900 dark:text-white">{{ $student->is_active ? 'active' : 'deactivated' }}</strong>.
            </p>
            <div class="flex flex-wrap items-center gap-2">
                <x-people.status-dialog :user="$student" button-variant="secondary" />
                <x-people.delete-dialog :user="$student" button-variant="danger" button="Delete learner" />
            </div>
        </div>
    </x-section>
</x-layouts.app>
