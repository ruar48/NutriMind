@php
    $isEditing = $schoolYear->exists;
    $canCreateQuarters = ! $isEditing || $termsCount === 0;
    $otherActiveYear = $activeSchoolYear && ! $activeSchoolYear->is($schoolYear) ? $activeSchoolYear : null;
@endphp

<x-layouts.app :title="$isEditing ? 'Edit '.$schoolYear->name : 'New school year'">
    <x-page-header
        eyebrow="Academic setup"
        :title="$isEditing ? 'Edit '.$schoolYear->name : 'New school year'"
        :description="$isEditing ? 'Change the name or the dates. Quarters must stay inside the school year.' : 'Set the first and last day of classes. You can split the year into quarters right away.'"
        :breadcrumbs="array_values(array_filter([
            ['label' => 'Dashboard', 'url' => route('admin.dashboard')],
            ['label' => 'School years', 'url' => route('admin.school-years.index')],
            $isEditing ? ['label' => $schoolYear->name, 'url' => route('admin.school-years.show', $schoolYear)] : null,
            ['label' => $isEditing ? 'Edit' : 'New school year'],
        ]))"
    >
        @if ($isEditing)
            <x-slot:meta>
                <x-setup.year-status :school-year="$schoolYear" />
            </x-slot:meta>
        @endif
    </x-page-header>

    <form
        method="POST"
        action="{{ $isEditing ? route('admin.school-years.update', $schoolYear) : route('admin.school-years.store') }}"
        class="flex flex-col gap-8"
        data-submit-once
        data-setup-school-year-form
    >
        @csrf
        @if ($isEditing)
            @method('PUT')
        @endif

        <x-form.section title="School year" description="Most schools name a school year after the two calendar years it spans." columns="2">
            <x-form.input
                name="name"
                label="School year name"
                :value="$schoolYear->name"
                placeholder="2027–2028"
                maxlength="50"
                hint="A typed hyphen becomes an en dash: 2027-2028 is saved as 2027–2028."
                prefix-icon="calendar-range"
                autocomplete="off"
                required
                class="col-span-full"
                data-setup-year-name
            />
            <x-form.input name="starts_on" type="date" label="Start date" :value="$schoolYear->starts_on" hint="First day of classes." required data-setup-year-start />
            <x-form.input name="ends_on" type="date" label="End date" :value="$schoolYear->ends_on" hint="Last day of classes." required data-setup-year-end />
        </x-form.section>

        <x-form.section title="Quarters & activation" description="Quarters organize quizzes and lesson plans. Only one school year is active at a time.">
            <div class="flex flex-col gap-5">
                @if ($canCreateQuarters)
                    <x-form.toggle
                        name="create_quarters"
                        label="Create four quarters"
                        description="Splits the dates into the 1st to 4th Quarter of about equal length. You can adjust each quarter afterwards."
                        :checked="$isEditing ? request()->boolean('quarters') : true"
                        unchecked-value="0"
                    />
                @else
                    <x-alert variant="info" :title="'This school year has '.trans_choice(':count quarter|:count quarters', $termsCount)">
                        Add, edit or delete quarters on the
                        <a href="{{ route('admin.school-years.show', $schoolYear) }}" class="font-semibold underline underline-offset-2">school year page</a>.
                    </x-alert>
                @endif

                @if ($schoolYear->is_active)
                    <x-alert variant="success" title="This is the active school year">
                        To switch years, make another school year active from the list.
                    </x-alert>
                @else
                    <x-form.toggle
                        name="make_active"
                        label="Make this the active school year"
                        :description="$otherActiveYear
                            ? $otherActiveYear->name.' stops being active. The quarter that covers today becomes the current quarter.'
                            : 'The quarter that covers today becomes the current quarter.'"
                        :checked="false"
                        unchecked-value="0"
                    />
                @endif
            </div>
        </x-form.section>

        <x-form.actions
            :cancel="$isEditing ? route('admin.school-years.show', $schoolYear) : route('admin.school-years.index')"
            :submit="$isEditing ? 'Save changes' : 'Create school year'"
            :submit-icon="$isEditing ? 'check' : 'plus'"
        >
            <x-icon name="info" class="size-4" />
            School years may not overlap each other.
        </x-form.actions>
    </form>
</x-layouts.app>
