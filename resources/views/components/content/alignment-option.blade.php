{{--
    One checkbox row of the lesson plan alignment editor (activities[], quizzes[] or missions[]).
    Props: name, value, checked, title, meta (one line under the title), group (data-check-group for
    "select all"), suggested (adds a "Suggested" badge). Slot `badges`: extra state badges after the title.
--}}
@props([
    'name',
    'value',
    'checked' => false,
    'title',
    'meta' => null,
    'group' => null,
    'suggested' => false,
])

<x-form.checkbox
    :name="$name"
    :value="$value"
    :checked="$checked"
    :description="$meta"
    :data-check-group="$group"
    {{ $attributes->class('rounded-xl px-3 py-2.5 ring-1 ring-transparent transition ring-inset hover:bg-ink-50 has-checked:bg-leaf-50/70 has-checked:ring-leaf-600/15 dark:hover:bg-white/3 dark:has-checked:bg-leaf-400/8 dark:has-checked:ring-leaf-400/20') }}
>
    <span class="inline-flex flex-wrap items-center gap-1.5">
        <span>{{ $title }}</span>
        @if ($suggested)
            <x-badge color="sun" icon="sparkles" size="sm">Suggested</x-badge>
        @endif
        {{ $badges ?? '' }}
    </span>
</x-form.checkbox>
