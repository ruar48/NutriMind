@props([
    'name',
    'label' => 'Color',
    'value' => null,
    'colors' => null,
    'hint' => null,
    'required' => false,
    'bag' => 'default',
    'errorKey' => null,
    'old' => true,
])

@php
    $swatches = [
        'leaf' => ['fill' => 'bg-leaf-500', 'check' => 'text-white', 'name' => 'Leaf green'],
        'carrot' => ['fill' => 'bg-carrot-500', 'check' => 'text-white', 'name' => 'Carrot orange'],
        'sun' => ['fill' => 'bg-sun-400', 'check' => 'text-sun-950', 'name' => 'Sunny yellow'],
        'berry' => ['fill' => 'bg-berry-500', 'check' => 'text-white', 'name' => 'Berry red'],
        'grape' => ['fill' => 'bg-grape-500', 'check' => 'text-white', 'name' => 'Grape violet'],
        'sky' => ['fill' => 'bg-sky-500', 'check' => 'text-white', 'name' => 'Sky blue'],
        'teal' => ['fill' => 'bg-teal-500', 'check' => 'text-white', 'name' => 'Teal'],
        'lime' => ['fill' => 'bg-lime-500', 'check' => 'text-lime-950', 'name' => 'Lime'],
        'pink' => ['fill' => 'bg-pink-500', 'check' => 'text-white', 'name' => 'Pink'],
        'indigo' => ['fill' => 'bg-indigo-500', 'check' => 'text-white', 'name' => 'Indigo'],
        'slate' => ['fill' => 'bg-slate-500', 'check' => 'text-white', 'name' => 'Slate'],
    ];

    $keys = collect($colors ?? array_keys($swatches))->filter(fn ($key) => array_key_exists($key, $swatches))->values();
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
    <div class="flex flex-wrap gap-1.5">
        @foreach ($keys as $key)
            <label for="{{ $baseId }}-{{ $key }}" class="relative grid size-11 cursor-pointer place-items-center rounded-full transition hover:bg-ink-900/5 has-focus-visible:outline-2 has-focus-visible:outline-leaf-500 dark:hover:bg-white/5" title="{{ $swatches[$key]['name'] }}">
                <input id="{{ $baseId }}-{{ $key }}" type="radio" name="{{ $name }}" value="{{ $key }}" class="peer sr-only" @checked($selected === $key) @required($required)>
                <span @class(['size-8 rounded-full shadow-sm ring-2 ring-white transition peer-checked:scale-110 peer-checked:shadow-md peer-checked:ring-ink-900/70 dark:ring-ink-900 dark:peer-checked:ring-white/80', $swatches[$key]['fill']])></span>
                <x-icon name="check" @class(['pointer-events-none absolute size-4 opacity-0 transition peer-checked:opacity-100', $swatches[$key]['check']]) stroke-width="3" />
                <span class="sr-only">{{ $swatches[$key]['name'] }}</span>
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
