{{--
    The <option>s of a learning-module select, grouped by "Subject · Grade". Use it as the slot of x-form.select.
    Each option carries data-subject / data-grade (filtered by resources/js/modules/content-module-picker.js) and
    data-code / data-competency (copied into empty competency fields when a module is picked).
    Props: modules (LearningModule collection with `subject` loaded), selected (current value, old input included).
--}}
@props([
    'modules' => [],
    'selected' => null,
])

@php
    $selectedValue = is_scalar($selected) ? (string) $selected : '';
    $groups = collect($modules)
        ->groupBy(fn ($module) => $module->subject->name.' · '.$module->grade_level->label())
        ->sortKeys(SORT_NATURAL | SORT_FLAG_CASE);
@endphp

@foreach ($groups as $groupLabel => $groupModules)
    <optgroup label="{{ $groupLabel }}" data-content-module-group>
        @foreach ($groupModules as $module)
            <option
                value="{{ $module->id }}"
                data-subject="{{ $module->subject_id }}"
                data-grade="{{ $module->grade_level->value }}"
                data-code="{{ $module->competency_code }}"
                data-competency="{{ $module->learning_competency }}"
                @selected($selectedValue === (string) $module->id)
            >{{ $module->quarter ? 'Q'.$module->quarter.' · ' : '' }}{{ $module->title }}{{ $module->is_published ? '' : ' (draft)' }}</option>
        @endforeach
    </optgroup>
@endforeach
