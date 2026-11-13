@php
    $isEditing = $term->exists;
    $title = $isEditing ? 'Edit '.$term->name : 'Add quarter';
    $yearRange = $schoolYear->starts_on->format('M j, Y').' – '.$schoolYear->ends_on->format('M j, Y');
@endphp

<x-layouts.app :title="$title.' · '.$schoolYear->name">
    <x-page-header
        :eyebrow="$schoolYear->name"
        :title="$title"
        :description="'Quarters stay inside the school year ('.$yearRange.') and may not overlap each other.'"
        :breadcrumbs="[
            ['label' => 'Dashboard', 'url' => route('admin.dashboard')],
            ['label' => 'School years', 'url' => route('admin.school-years.index')],
            ['label' => $schoolYear->name, 'url' => route('admin.school-years.show', $schoolYear)],
            ['label' => $isEditing ? 'Edit '.$term->name : 'Add quarter'],
        ]"
    >
        @if ($isEditing)
            <x-slot:meta>
                <x-setup.term-status :term="$term" />
            </x-slot:meta>
        @endif
    </x-page-header>

    <form
        method="POST"
        action="{{ $isEditing ? route('admin.terms.update', $term) : route('admin.school-years.terms.store', $schoolYear) }}"
        class="flex flex-col gap-8"
        data-submit-once
    >
        @csrf
        @if ($isEditing)
            @method('PUT')
        @endif

        <x-form.section title="Quarter" description="The number orders the quarters on calendars and in reports." columns="2">
            <x-form.input name="name" label="Quarter name" :value="$term->name" placeholder="1st Quarter" maxlength="50" autocomplete="off" required />
            <x-form.input name="sequence" type="number" label="Quarter number" :value="$term->sequence" min="1" max="{{ \App\Services\Setup\AcademicCalendar::MAX_TERMS }}" hint="1 to {{ \App\Services\Setup\AcademicCalendar::MAX_TERMS }}, unique in the school year." required />
            <x-form.input name="starts_on" type="date" label="Start date" :value="$term->starts_on" :min="$schoolYear->starts_on->format('Y-m-d')" :max="$schoolYear->ends_on->format('Y-m-d')" :hint="'On or after '.$schoolYear->starts_on->format('M j, Y').'.'" required />
            <x-form.input name="ends_on" type="date" label="End date" :value="$term->ends_on" :min="$schoolYear->starts_on->format('Y-m-d')" :max="$schoolYear->ends_on->format('Y-m-d')" :hint="'On or before '.$schoolYear->ends_on->format('M j, Y').'.'" required />

            <x-slot:aside>
                <div class="mt-4 flex flex-col gap-2" data-other-terms>
                    <p class="text-xs font-semibold tracking-wide text-ink-500 uppercase dark:text-ink-400">Other quarters</p>
                    @forelse ($otherTerms as $otherTerm)
                        <div class="flex items-center justify-between gap-3 rounded-xl bg-white px-3 py-2 text-sm ring-1 ring-ink-900/5 dark:bg-white/3 dark:ring-white/8">
                            <span class="min-w-0 truncate font-medium text-ink-800 dark:text-ink-100">{{ $otherTerm->name }}</span>
                            <x-setup.date-range :from="$otherTerm->starts_on" :to="$otherTerm->ends_on" class="text-xs text-ink-500 dark:text-ink-400" />
                        </div>
                    @empty
                        <p class="text-sm text-ink-500 dark:text-ink-400">This is the first quarter of {{ $schoolYear->name }}.</p>
                    @endforelse
                </div>
            </x-slot:aside>
        </x-form.section>

        <x-form.actions
            :cancel="route('admin.school-years.show', $schoolYear)"
            :submit="$isEditing ? 'Save changes' : 'Add quarter'"
            :submit-icon="$isEditing ? 'check' : 'plus'"
        >
            <x-icon name="info" class="size-4" />
            Set the current quarter from the school year page.
        </x-form.actions>
    </form>
</x-layouts.app>
