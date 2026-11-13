@php
    $isEditing = $module->exists;
    $quarterOptions = [1 => '1st Quarter', 2 => '2nd Quarter', 3 => '3rd Quarter', 4 => '4th Quarter'];
@endphp

<x-layouts.app :title="$isEditing ? 'Edit '.$module->title : 'New learning module'">
    <x-page-header
        eyebrow="Game content"
        :title="$isEditing ? 'Edit module' : 'New learning module'"
        :description="$isEditing ? $module->title : 'A module is one lesson of a game world. You add its activities after saving it.'"
        :breadcrumbs="array_values(array_filter([
            ['label' => 'Learning modules', 'url' => route('admin.modules.index')],
            $isEditing ? ['label' => $module->title, 'url' => route('admin.modules.show', $module)] : null,
            ['label' => $isEditing ? 'Edit' : 'New module'],
        ]))"
    />

    @if ($isPrefilled)
        <x-alert variant="info" title="Prefilled from lesson alignment" data-animate>
            The subject, grade, quarter and competency come from the teachers’ lesson plans. Check them, then name the module.
        </x-alert>
    @endif

    <form method="POST" action="{{ $isEditing ? route('admin.modules.update', $module) : route('admin.modules.store') }}" class="flex flex-col gap-8" data-submit-once data-animate style="--stagger: 1">
        @csrf
        @if ($isEditing)
            @method('PUT')
        @endif

        <x-form.section title="Module details" description="Learners see the title and the game world on the module’s map." columns="2">
            <x-form.input name="title" label="Title" :value="$module->title" maxlength="150" placeholder="e.g. Go, Grow and Glow Foods" required class="col-span-full" />
            <x-form.select name="subject_id" label="Subject" :options="$subjectOptions" :value="$module->subject_id" placeholder="Choose a subject" required />
            <x-form.select name="grade_level" label="Grade level" :options="\App\Enums\GradeLevel::options()" :value="$module->grade_level" placeholder="Choose a grade" required />
            <x-form.select name="quarter" label="Quarter" :options="$quarterOptions" :value="$module->quarter" placeholder="No quarter" optional />
            <x-form.input name="world_name" label="Game world" :value="$module->world_name" maxlength="100" prefix-icon="map" placeholder="e.g. Veggie Valley" optional />
            <x-form.textarea name="description" label="Description" :value="$module->description" :max-length="2000" rows="4" optional class="col-span-full" />
        </x-form.section>

        <x-form.section title="Curriculum alignment" description="The DepEd learning competency this module teaches.">
            <x-slot:aside>
                <p class="flex items-start gap-2 text-sm text-ink-600 dark:text-ink-300">
                    <x-icon name="link" class="mt-0.5 size-4 shrink-0 text-leaf-600 dark:text-leaf-400" />
                    <span>Lesson plans with the same subject, grade and competency code are matched to this module on the
                        <a href="{{ route('admin.content-alignment.index') }}" class="font-medium text-leaf-700 underline-offset-2 hover:underline dark:text-leaf-300">Lesson alignment</a> page.</span>
                </p>
            </x-slot:aside>
            <x-form.input name="competency_code" label="Competency code" :value="$module->competency_code" :maxlength="\App\Services\Content\CompetencyCode::MAX_LENGTH" prefix-icon="hash" input-class="font-mono uppercase" placeholder="e.g. H5N-IA-1" autocomplete="off" spellcheck="false" hint="Saved in capital letters, so “h5n-ia-1” and “H5N-IA-1” match." optional />
            <x-form.textarea name="learning_competency" label="Learning competency" :value="$module->learning_competency" :max-length="1000" rows="3" placeholder="e.g. Classifies foods into Go, Grow and Glow groups." optional />
        </x-form.section>

        <x-form.section title="In the game" description="Where the module sits on the map and whether learners can play it." columns="2">
            <x-form.input name="sort_order" type="number" label="Display order" :value="$module->sort_order" min="0" max="999" hint="Lower numbers come first within the grade and quarter." required />
            <div class="col-span-full">
                <x-form.toggle name="is_published" label="Published" description="Learners can play the module’s published activities. Turn this off to prepare it as a draft." :checked="(bool) $module->is_published" unchecked-value="0" />
            </div>
        </x-form.section>

        <x-form.actions
            :cancel="$isEditing ? route('admin.modules.show', $module) : route('admin.modules.index')"
            :submit="$isEditing ? 'Save changes' : 'Create module'"
            :submit-icon="$isEditing ? 'check' : 'plus'"
        >
            <x-icon name="info" class="size-4" />
            Changes reach learners the next time their game syncs.
        </x-form.actions>
    </form>
</x-layouts.app>
