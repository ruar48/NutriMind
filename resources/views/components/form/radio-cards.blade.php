@props([
    'name',
    'label' => null,
    'options' => [],
    'value' => null,
    'columns' => 3,
    'hint' => null,
    'required' => false,
    'bag' => 'default',
    'errorKey' => null,
    'old' => true,
])

@php
    $tones = [
        'leaf' => 'bg-leaf-50 text-leaf-700 dark:bg-leaf-400/10 dark:text-leaf-300',
        'carrot' => 'bg-carrot-50 text-carrot-700 dark:bg-carrot-400/10 dark:text-carrot-300',
        'sun' => 'bg-sun-50 text-sun-800 dark:bg-sun-400/10 dark:text-sun-300',
        'berry' => 'bg-berry-50 text-berry-700 dark:bg-berry-400/10 dark:text-berry-300',
        'grape' => 'bg-grape-50 text-grape-700 dark:bg-grape-400/10 dark:text-grape-300',
        'sky' => 'bg-sky-50 text-sky-700 dark:bg-sky-400/10 dark:text-sky-300',
        'teal' => 'bg-teal-50 text-teal-700 dark:bg-teal-400/10 dark:text-teal-300',
        'lime' => 'bg-lime-50 text-lime-800 dark:bg-lime-400/10 dark:text-lime-300',
        'pink' => 'bg-pink-50 text-pink-700 dark:bg-pink-400/10 dark:text-pink-300',
        'indigo' => 'bg-indigo-50 text-indigo-700 dark:bg-indigo-400/10 dark:text-indigo-300',
        'slate' => 'bg-slate-100 text-slate-700 dark:bg-slate-400/10 dark:text-slate-300',
    ];

    $grids = [
        1 => 'grid-cols-1',
        2 => 'grid-cols-1 @md:grid-cols-2',
        3 => 'grid-cols-1 @md:grid-cols-2 @2xl:grid-cols-3',
        4 => 'grid-cols-1 @md:grid-cols-2 @4xl:grid-cols-4',
    ];

    $errorKey ??= \Illuminate\Support\Str::of($name)->replace(['[', ']'], ['.', ''])->trim('.')->toString();
    $baseId = \Illuminate\Support\Str::of($name)->replace(['[', ']', '.'], ['-', '', '-'])->trim('-')->toString();
    $message = isset($errors) ? $errors->getBag($bag)->first($errorKey) : null;
    $hasError = filled($message);
    $normalized = $value instanceof \BackedEnum ? $value->value : $value;
    $selected = $old ? old($errorKey, $normalized) : $normalized;

    $cards = collect($options)->map(function ($option) {
        if ($option instanceof \BackedEnum) {
            return [
                'value' => (string) $option->value,
                'label' => method_exists($option, 'label') ? $option->label() : $option->name,
                'description' => method_exists($option, 'description') ? $option->description() : null,
                'icon' => method_exists($option, 'icon') ? $option->icon() : null,
                'color' => method_exists($option, 'color') ? $option->color() : 'leaf',
            ];
        }

        return [
            'value' => (string) ($option['value'] ?? ''),
            'label' => $option['label'] ?? '',
            'description' => $option['description'] ?? null,
            'icon' => $option['icon'] ?? null,
            'color' => $option['color'] ?? 'leaf',
            'disabled' => $option['disabled'] ?? false,
        ];
    })->values();
@endphp

<fieldset {{ $attributes->class('@container min-w-0') }} @if ($hasError) aria-describedby="{{ $baseId }}-error" @endif>
    @if ($label)
        <legend class="mb-2 flex items-baseline gap-1 text-sm font-medium text-ink-800 dark:text-ink-100">
            {{ $label }}
            @if ($required)
                <span class="text-berry-600 dark:text-berry-400" aria-hidden="true">*</span>
            @endif
        </legend>
    @endif

    <div @class(['grid gap-3', $grids[(int) $columns] ?? $grids[3]])>
        @foreach ($cards as $card)
            @php $optionId = $baseId.'-'.\Illuminate\Support\Str::slug($card['value'] !== '' ? $card['value'] : 'none'); @endphp
            <label for="{{ $optionId }}" @class([
                'group relative flex cursor-pointer items-start gap-3 rounded-2xl bg-white p-4 ring-1 transition ring-inset has-checked:bg-leaf-50/60 has-checked:ring-2 has-checked:ring-leaf-500 has-focus-visible:outline-2 has-focus-visible:outline-offset-2 has-focus-visible:outline-leaf-500 has-disabled:cursor-not-allowed has-disabled:opacity-60 dark:bg-white/3 dark:has-checked:bg-leaf-400/10 dark:has-checked:ring-leaf-400',
                'ring-ink-900/10 hover:ring-ink-900/25 dark:ring-white/10 dark:hover:ring-white/25' => ! $hasError,
                'ring-berry-500/60 dark:ring-berry-400/50' => $hasError,
            ])>
                <input
                    id="{{ $optionId }}"
                    type="radio"
                    name="{{ $name }}"
                    value="{{ $card['value'] }}"
                    class="sr-only"
                    @checked((string) $selected === $card['value'])
                    @required($required)
                    @disabled($card['disabled'] ?? false)
                >
                @if ($card['icon'])
                    <span @class(['grid size-10 shrink-0 place-items-center rounded-xl', $tones[$card['color']] ?? $tones['leaf']])>
                        <x-icon :name="$card['icon']" class="size-5" />
                    </span>
                @endif
                <span class="min-w-0 flex-1 pr-6">
                    <span class="block text-sm font-semibold text-ink-900 dark:text-white">{{ $card['label'] }}</span>
                    @if ($card['description'])
                        <span class="mt-0.5 block text-xs leading-relaxed text-ink-500 dark:text-ink-400">{{ $card['description'] }}</span>
                    @endif
                </span>
                <x-icon name="circle-check" class="absolute top-3.5 right-3.5 size-5 scale-75 text-leaf-600 opacity-0 transition group-has-checked:scale-100 group-has-checked:opacity-100 dark:text-leaf-400" stroke-width="2.25" />
            </label>
        @endforeach
    </div>

    @if ($hint)
        <x-form.hint class="mt-2">{{ $hint }}</x-form.hint>
    @endif
    @if ($hasError)
        <x-form.error :id="$baseId.'-error'" :message="$message" class="mt-2" />
    @endif
</fieldset>
