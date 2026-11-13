@php
    $isEditing = $mission->exists;
@endphp

<x-layouts.app :title="$isEditing ? 'Edit '.$mission->title : 'New mission'">
    <x-page-header
        eyebrow="Game content"
        :title="$isEditing ? 'Edit mission' : 'New mission'"
        :description="$isEditing ? $mission->title : 'Missions give learners a goal and bonus points when they reach it.'"
        :breadcrumbs="array_values(array_filter([
            ['label' => 'Missions', 'url' => route('admin.missions.index')],
            $startingModule ? ['label' => $startingModule->title, 'url' => route('admin.modules.show', $startingModule)] : null,
            ['label' => $isEditing ? 'Edit' : 'New mission'],
        ]))"
    />

    <form method="POST" action="{{ $isEditing ? route('admin.missions.update', $mission) : route('admin.missions.store') }}" class="flex flex-col gap-8" data-submit-once data-animate style="--stagger: 1">
        @csrf
        @if ($isEditing)
            @method('PUT')
        @endif

        <x-form.section title="Mission" description="Learners read the objective in the game’s mission log." columns="2">
            <x-form.input name="title" label="Title" :value="$mission->title" maxlength="150" placeholder="e.g. Breakfast Champion" required class="col-span-full" />
            <x-form.input name="objective" label="Objective" :value="$mission->objective" maxlength="255" prefix-icon="target" placeholder="e.g. Eat a healthy breakfast five days in a row." required class="col-span-full" />
            <x-form.textarea name="description" label="Description" :value="$mission->description" :max-length="2000" rows="4" required class="col-span-full" />
            <x-form.radio-cards name="type" label="Mission type" :options="\App\Enums\MissionType::cases()" :value="$mission->type" columns="4" required class="col-span-full" />
            <x-form.radio-cards name="difficulty" label="Difficulty" :options="\App\Enums\Difficulty::cases()" :value="$mission->difficulty" columns="3" required class="col-span-full" />
        </x-form.section>

        <x-form.section title="Where it appears" description="The subject and grade decide which learners get the mission. A module ties it to one game world." columns="2">
            <x-form.select name="subject_id" label="Subject" :options="$subjectOptions" :value="$mission->subject_id" placeholder="Choose a subject" required />
            <x-form.select name="grade_level" label="Grade level" :options="\App\Enums\GradeLevel::options()" :value="$mission->grade_level" placeholder="Both grades" hint="Leave on “Both grades” for missions every learner can play." />
            <x-form.select
                name="learning_module_id"
                label="Learning module"
                :value="$mission->learning_module_id"
                placeholder="Not tied to a module"
                hint="Only modules of the chosen subject (and grade) fit."
                optional
                class="col-span-full"
                data-content-module-picker
                data-content-subject-field="subject_id"
                data-content-grade-field="grade_level"
            >
                <x-content.module-options :modules="$modules" :selected="old('learning_module_id', $mission->learning_module_id)" />
            </x-form.select>
        </x-form.section>

        <x-form.section title="Reward and schedule" description="Points are added to the learner’s XP when they complete the mission." columns="2">
            <x-form.input name="points_reward" type="number" label="Reward points" :value="$mission->points_reward" min="0" max="10000" suffix="XP" required />
            <x-form.input name="sort_order" type="number" label="Display order" :value="$mission->sort_order" min="0" max="999" hint="Lower numbers are listed first." required />
            <x-form.input name="starts_on" type="date" label="Starts on" :value="$mission->starts_on" hint="Leave empty to start right away." optional />
            <x-form.input name="ends_on" type="date" label="Ends on" :value="$mission->ends_on" hint="Leave empty to keep it open." optional />
            <div class="col-span-full">
                <x-form.toggle name="is_active" label="Active" description="Learners can see and play the mission. Turn this off to retire it without losing progress." :checked="(bool) $mission->is_active" unchecked-value="0" />
            </div>
        </x-form.section>

        <x-form.actions
            :cancel="route('admin.missions.index')"
            :submit="$isEditing ? 'Save changes' : 'Create mission'"
            :submit-icon="$isEditing ? 'check' : 'plus'"
        >
            <x-icon name="info" class="size-4" />
            Changes reach learners the next time their game syncs.
        </x-form.actions>
    </form>
</x-layouts.app>
