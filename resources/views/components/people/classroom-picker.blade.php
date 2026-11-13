@props([
    'classrooms',
    'selected' => [],
    'grade' => null,
    'filterByGrade' => false,
])

@php
    $selectedIds = array_map(intval(...), $selected);
    $chosenGrade = $grade instanceof \App\Enums\GradeLevel ? $grade->value : (filled($grade) ? (int) $grade : null);
    $groups = collect(\App\Enums\GradeLevel::cases())
        ->map(fn (\App\Enums\GradeLevel $level): array => [
            'grade' => $level,
            'classrooms' => $classrooms->filter(fn (\App\Models\Classroom $classroom): bool => $classroom->grade_level === $level)->values(),
        ])
        ->filter(fn (array $group): bool => $group['classrooms']->isNotEmpty())
        ->values();
@endphp

<div {{ $attributes->class('flex flex-col gap-5') }} data-classroom-picker>
    @if ($groups->isEmpty())
        <x-empty-state compact icon="school" title="No classrooms open this school year"
            description="Teachers create classrooms in the Teacher Portal. You can enroll the learner later from this form." />
    @else
        @if ($filterByGrade)
            <p class="text-sm text-ink-500 dark:text-ink-400" data-show-when="grade_level:!{{ $groups->map(fn ($group) => $group['grade']->value)->join(',') }}" @if ($chosenGrade !== null) hidden @endif>
                Choose a grade level to see its classrooms.
            </p>
        @endif

        @foreach ($groups as $group)
            <fieldset
                class="flex min-w-0 flex-col gap-3"
                data-classroom-group="{{ $group['grade']->value }}"
                @if ($filterByGrade) data-show-when="grade_level:{{ $group['grade']->value }}" @endif
                @if ($filterByGrade && $chosenGrade !== null && $chosenGrade !== $group['grade']->value) hidden @endif
            >
                <legend class="mb-1 flex items-center gap-2 text-sm font-semibold text-ink-900 dark:text-ink-50">
                    <x-status-badge :status="$group['grade']" size="sm" />
                    <span>{{ trans_choice(':count classroom|:count classrooms', $group['classrooms']->count()) }}</span>
                </legend>
                <div class="grid grid-cols-1 gap-3 @lg:grid-cols-2">
                    @foreach ($group['classrooms'] as $classroom)
                        <x-form.checkbox
                            name="classroom_ids[]"
                            :value="$classroom->id"
                            :label="$classroom->name"
                            :description="$classroom->subject->name.' · '.$classroom->teacher->name.' · Section '.$classroom->section"
                            :checked="in_array($classroom->id, $selectedIds, true)"
                        />
                    @endforeach
                </div>
            </fieldset>
        @endforeach
    @endif

    <x-form.error for="classroom_ids" />
</div>
