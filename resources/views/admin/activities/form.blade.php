@php
    $isEditing = $activity->exists;
@endphp

<x-layouts.app :title="$isEditing ? 'Edit '.$activity->title : 'New activity'">
    <x-page-header
        eyebrow="Learning module · {{ $module->title }}"
        :title="$isEditing ? 'Edit activity' : 'New activity'"
        :description="$isEditing ? $activity->title : 'Add a lesson, video, reading, mini-game or practice round to '.$module->title.'.'"
        :breadcrumbs="[
            ['label' => 'Learning modules', 'url' => route('admin.modules.index')],
            ['label' => $module->title, 'url' => route('admin.modules.show', $module)],
            ['label' => $isEditing ? 'Edit activity' : 'New activity'],
        ]"
    >
        <x-slot:meta>
            <x-subject-chip :subject="$module->subject" size="sm" />
            <x-badge :color="$module->grade_level->color()" size="sm">{{ $module->grade_level->label() }}</x-badge>
            @unless ($module->is_published)
                <x-badge color="slate" icon="eye-off" size="sm">Module is a draft</x-badge>
            @endunless
        </x-slot:meta>
    </x-page-header>

    <form method="POST" action="{{ $isEditing ? route('admin.activities.update', $activity) : route('admin.modules.activities.store', $module) }}" class="flex flex-col gap-8" data-submit-once data-animate style="--stagger: 1">
        @csrf
        @if ($isEditing)
            @method('PUT')
        @endif

        <x-form.section title="Activity" description="What learners do in this step of the module.">
            <x-form.input name="title" label="Title" :value="$activity->title" maxlength="150" placeholder="e.g. Sort the Food Groups" required />
            <x-form.radio-cards name="type" label="Activity type" :options="\App\Enums\ActivityType::cases()" :value="$activity->type" columns="3" required />
            <x-form.textarea name="description" label="Description" :value="$activity->description" :max-length="2000" rows="4" optional />
            <x-form.input name="content_url" type="url" label="Content link" :value="$activity->content_url" maxlength="255" prefix-icon="link" placeholder="https://" inputmode="url" hint="A video, reading or resource the activity opens (http or https)." optional />
        </x-form.section>

        <x-form.section title="Play settings" description="How long it takes, what it is worth and when learners see it." columns="2">
            <x-form.input name="estimated_minutes" type="number" label="Estimated time" :value="$activity->estimated_minutes" min="1" max="240" suffix="min" optional />
            <x-form.input name="points" type="number" label="Points" :value="$activity->points" min="0" max="1000" suffix="XP" required />
            <x-form.input name="sort_order" type="number" label="Play order" :value="$activity->sort_order" min="0" max="999" hint="Lower numbers are played first." required />
            <div class="col-span-full">
                <x-form.toggle name="is_published" label="Published" description="Learners can play the activity when its module is published." :checked="(bool) $activity->is_published" unchecked-value="0" />
            </div>
        </x-form.section>

        <x-form.actions
            :cancel="route('admin.modules.show', $module)"
            :submit="$isEditing ? 'Save changes' : 'Add activity'"
            :submit-icon="$isEditing ? 'check' : 'plus'"
        >
            <x-icon name="info" class="size-4" />
            Learners keep the points they already earned when you change an activity.
        </x-form.actions>
    </form>
</x-layouts.app>
