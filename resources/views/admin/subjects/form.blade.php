@php
    $isEditing = $subject->exists;
    $previewColor = (string) old('color', $subject->color ?? 'leaf');
    $previewColor = in_array($previewColor, $colors, true) ? $previewColor : 'leaf';
    $previewIcon = (string) old('icon', $subject->icon ?? 'book-open');
    $previewIcon = in_array($previewIcon, $icons, true) ? $previewIcon : 'book-open';
    $previewName = (string) old('name', $subject->name);
    $previewCode = \Illuminate\Support\Str::upper((string) old('code', $subject->code));
    $usage = $isEditing ? [
        'classrooms' => $subject->classrooms_count,
        'learning modules' => $subject->learning_modules_count,
        'missions' => $subject->missions_count,
        'quizzes' => $subject->quizzes_count,
        'question bank items' => $subject->question_bank_items_count,
        'lesson plans' => $subject->lesson_plans_count,
    ] : [];
    $isUsed = array_sum($usage) > 0;
@endphp

<x-layouts.app :title="$isEditing ? 'Edit '.$subject->name : 'New subject'">
    <x-page-header
        eyebrow="Academic setup"
        :title="$isEditing ? 'Edit '.$subject->name : 'New subject'"
        description="The color and icon identify the subject on classrooms, quizzes, missions and reports."
        :breadcrumbs="[
            ['label' => 'Dashboard', 'url' => route('admin.dashboard')],
            ['label' => 'Subjects', 'url' => route('admin.subjects.index')],
            ['label' => $isEditing ? 'Edit '.$subject->name : 'New subject'],
        ]"
    >
        @if ($isEditing)
            <x-slot:meta>
                <x-subject-chip :subject="$subject" />
                <x-badge :color="$subject->is_active ? 'leaf' : 'slate'" :icon="$subject->is_active ? 'circle-check' : 'eye-off'">{{ $subject->is_active ? 'Active' : 'Inactive' }}</x-badge>
            </x-slot:meta>
            @unless ($isUsed)
                <x-slot:actions>
                    <x-confirm-dialog
                        :action="route('admin.subjects.destroy', $subject)"
                        :title="'Delete '.$subject->name.'?'"
                        message="The subject and its teacher assignments are removed. This cannot be undone."
                        confirm="Delete subject"
                        button="Delete subject"
                        button-variant="ghost"
                        button-size="md"
                    />
                </x-slot:actions>
            @endunless
        @endif
    </x-page-header>

    <form
        method="POST"
        action="{{ $isEditing ? route('admin.subjects.update', $subject) : route('admin.subjects.store') }}"
        class="flex flex-col gap-8"
        data-submit-once
        data-setup-subject-form
    >
        @csrf
        @if ($isEditing)
            @method('PUT')
        @endif

        <x-form.section title="Subject details" description="Teachers and parents see the name; the code appears on reports and exports." columns="2">
            <x-form.input name="name" label="Subject name" :value="$subject->name" placeholder="Health" maxlength="100" autocomplete="off" required class="col-span-full" data-setup-subject-name />
            <x-form.input name="code" label="Subject code" :value="$subject->code" placeholder="HEALTH" maxlength="20" autocomplete="off" hint="Letters, numbers and hyphens. Saved in capitals." input-class="font-mono uppercase" required data-setup-subject-code />
            <x-form.textarea name="description" label="Description" :value="$subject->description" :max-length="500" rows="3" optional class="col-span-full" placeholder="What learners study in this subject." />
        </x-form.section>

        <x-form.section title="Color & icon" description="Pick a color and an icon that learners and teachers recognize at a glance.">
            <x-form.color-picker name="color" label="Color" :colors="$colors" :value="$subject->color ?? 'leaf'" required />
            <x-form.icon-picker name="icon" label="Icon" :icons="$icons" :value="$subject->icon ?? 'book-open'" required />

            <x-slot:aside>
                <div class="mt-4" aria-live="polite">
                    <p class="mb-2 text-xs font-semibold tracking-wide text-ink-500 uppercase dark:text-ink-400">Preview</p>
                    <div class="group overflow-hidden rounded-2xl bg-white shadow-card ring-1 ring-ink-900/5 dark:bg-ink-900 dark:ring-white/8" data-setup-subject-preview data-color="{{ $previewColor }}">
                        <div aria-hidden="true" class="h-2 bg-linear-to-r group-data-[color=berry]:from-berry-500 group-data-[color=berry]:to-berry-400 group-data-[color=carrot]:from-carrot-500 group-data-[color=carrot]:to-carrot-400 group-data-[color=grape]:from-grape-500 group-data-[color=grape]:to-grape-400 group-data-[color=indigo]:from-indigo-500 group-data-[color=indigo]:to-indigo-400 group-data-[color=leaf]:from-leaf-500 group-data-[color=leaf]:to-leaf-400 group-data-[color=lime]:from-lime-500 group-data-[color=lime]:to-lime-400 group-data-[color=pink]:from-pink-500 group-data-[color=pink]:to-pink-400 group-data-[color=sky]:from-sky-500 group-data-[color=sky]:to-sky-400 group-data-[color=slate]:from-slate-500 group-data-[color=slate]:to-slate-400 group-data-[color=sun]:from-sun-400 group-data-[color=sun]:to-sun-300 group-data-[color=teal]:from-teal-500 group-data-[color=teal]:to-teal-400"></div>
                        <div class="flex items-center gap-3 p-4">
                            <span aria-hidden="true" class="grid size-12 shrink-0 place-items-center rounded-2xl shadow-sm group-data-[color=berry]:bg-berry-500 group-data-[color=berry]:text-white group-data-[color=carrot]:bg-carrot-500 group-data-[color=carrot]:text-white group-data-[color=grape]:bg-grape-500 group-data-[color=grape]:text-white group-data-[color=indigo]:bg-indigo-500 group-data-[color=indigo]:text-white group-data-[color=leaf]:bg-leaf-600 group-data-[color=leaf]:text-white group-data-[color=lime]:bg-lime-500 group-data-[color=lime]:text-lime-950 group-data-[color=pink]:bg-pink-500 group-data-[color=pink]:text-white group-data-[color=sky]:bg-sky-600 group-data-[color=sky]:text-white group-data-[color=slate]:bg-slate-500 group-data-[color=slate]:text-white group-data-[color=sun]:bg-sun-400 group-data-[color=sun]:text-sun-950 group-data-[color=teal]:bg-teal-600 group-data-[color=teal]:text-white" data-setup-subject-preview-icon>
                                <x-icon :name="$previewIcon" class="size-6" />
                            </span>
                            <div class="min-w-0">
                                <p class="truncate font-display text-base font-semibold tracking-tight text-ink-950 dark:text-white" data-setup-subject-preview-name data-placeholder="Subject name">{{ $previewName !== '' ? $previewName : 'Subject name' }}</p>
                                <p class="truncate font-mono text-xs tracking-wide text-ink-500 dark:text-ink-400" data-setup-subject-preview-code data-placeholder="CODE">{{ $previewCode !== '' ? $previewCode : 'CODE' }}</p>
                            </div>
                        </div>
                    </div>
                </div>
            </x-slot:aside>
        </x-form.section>

        <x-form.section title="Availability" description="Inactive subjects keep their existing classrooms, quizzes and content, but can't be used for anything new.">
            <x-form.toggle
                name="is_active"
                label="Active"
                description="Teachers can open classrooms and build quizzes for active subjects they are assigned to."
                :checked="$subject->exists ? $subject->is_active : true"
                unchecked-value="0"
            />

            @if ($isEditing)
                <div class="rounded-2xl bg-ink-50 p-4 ring-1 ring-ink-900/5 ring-inset dark:bg-white/3 dark:ring-white/8" data-subject-usage>
                    <p class="text-sm font-medium text-ink-800 dark:text-ink-100">
                        {{ trans_choice(':count teacher is|:count teachers are', $subject->teachers_count) }} assigned to {{ $subject->name }}.
                    </p>
                    @if ($isUsed)
                        <ul class="mt-2 flex flex-wrap gap-2">
                            @foreach ($usage as $label => $count)
                                @if ($count > 0)
                                    <li><x-badge color="slate">{{ number_format($count) }} {{ $label }}</x-badge></li>
                                @endif
                            @endforeach
                        </ul>
                        <p class="mt-2 text-xs text-ink-500 dark:text-ink-400">A subject in use can't be deleted. Deactivate it instead.</p>
                    @else
                        <p class="mt-1 text-xs text-ink-500 dark:text-ink-400">Nothing uses this subject yet, so it can still be deleted.</p>
                    @endif
                </div>
            @endif
        </x-form.section>

        <x-form.actions
            :cancel="route('admin.subjects.index')"
            :submit="$isEditing ? 'Save changes' : 'Create subject'"
            :submit-icon="$isEditing ? 'check' : 'plus'"
        />
    </form>
</x-layouts.app>
