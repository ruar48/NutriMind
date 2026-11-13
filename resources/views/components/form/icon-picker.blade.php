@props([
    'name',
    'label' => 'Icon',
    'value' => null,
    'icons' => null,
    'hint' => null,
    'required' => false,
    'bag' => 'default',
    'errorKey' => null,
    'old' => true,
])

@php
    $defaultIcons = [
        'book-open', 'apple', 'carrot', 'salad', 'leaf', 'sprout', 'cherry', 'citrus', 'banana', 'wheat', 'egg', 'fish',
        'milk', 'glass-water', 'droplet', 'utensils', 'cooking-pot', 'heart-pulse', 'heart', 'brain', 'lightbulb', 'flame',
        'zap', 'rocket', 'target', 'flag', 'map', 'compass', 'gamepad-2', 'puzzle', 'trophy', 'award', 'medal', 'crown',
        'star', 'sparkles', 'gift', 'party-popper', 'smile', 'hand-heart', 'shield-check', 'badge-check', 'messages-square',
        'megaphone', 'calendar-days', 'graduation-cap', 'school', 'library', 'notebook-pen', 'palette', 'globe', 'house-heart',
    ];

    $choices = collect($icons ?? $defaultIcons)->filter(fn ($icon) => \App\View\Components\Icon::has($icon))->unique()->values();
    $errorKey ??= \Illuminate\Support\Str::of($name)->replace(['[', ']'], ['.', ''])->trim('.')->toString();
    $baseId = \Illuminate\Support\Str::of($name)->replace(['[', ']', '.'], ['-', '', '-'])->trim('-')->toString();
    $message = isset($errors) ? $errors->getBag($bag)->first($errorKey) : null;
    $selected = (string) ($old ? old($errorKey, $value) : $value);
@endphp

<fieldset {{ $attributes->class('min-w-0') }} @if (filled($message)) aria-describedby="{{ $baseId }}-error" @endif>
    <legend class="mb-2 flex items-baseline gap-1 text-sm font-medium text-ink-800 dark:text-ink-100">
        {{ $label }}
        @if ($required)
            <span class="text-berry-600 dark:text-berry-400" aria-hidden="true">*</span>
        @endif
    </legend>
    <div class="grid grid-cols-[repeat(auto-fill,minmax(2.75rem,1fr))] gap-1.5 rounded-2xl bg-ink-50/70 p-2 ring-1 ring-ink-900/5 ring-inset dark:bg-white/3 dark:ring-white/8">
        @foreach ($choices as $icon)
            @php $iconName = \Illuminate\Support\Str::of($icon)->replace('-', ' ')->replaceMatches('/\s\d+$/', '')->ucfirst()->toString(); @endphp
            <label for="{{ $baseId }}-{{ $icon }}" class="relative grid size-11 cursor-pointer place-items-center rounded-xl text-ink-500 transition hover:bg-white hover:text-ink-900 has-checked:bg-white has-checked:text-leaf-700 has-checked:shadow-sm has-checked:ring-2 has-checked:ring-leaf-500 has-focus-visible:outline-2 has-focus-visible:outline-leaf-500 dark:text-ink-400 dark:hover:bg-white/8 dark:hover:text-white dark:has-checked:bg-white/10 dark:has-checked:text-leaf-300 dark:has-checked:ring-leaf-400" title="{{ $iconName }}">
                <input id="{{ $baseId }}-{{ $icon }}" type="radio" name="{{ $name }}" value="{{ $icon }}" class="sr-only" @checked($selected === $icon) @required($required)>
                <x-icon :name="$icon" class="size-5" />
                <span class="sr-only">{{ $iconName }}</span>
            </label>
        @endforeach
    </div>
    @if ($hint)
        <x-form.hint class="mt-2">{{ $hint }}</x-form.hint>
    @endif
    @if (filled($message))
        <x-form.error :id="$baseId.'-error'" :message="$message" class="mt-2" />
    @endif
</fieldset>
