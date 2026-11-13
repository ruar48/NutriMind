@props([
    'name',
    'label' => null,
    'options' => [],
    'value' => null,
    'placeholder' => null,
    'hint' => null,
    'required' => false,
    'optional' => false,
    'multiple' => false,
    'bag' => 'default',
    'errorKey' => null,
    'id' => null,
    'hideLabel' => false,
    'old' => true,
])

@php
    $errorKey ??= \Illuminate\Support\Str::of($name)->replace(['[', ']'], ['.', ''])->trim('.')->toString();
    $id ??= \Illuminate\Support\Str::of($name)->replace(['[', ']', '.'], ['-', '', '-'])->trim('-')->toString();
    $message = null;
    if (isset($errors)) {
        $message = $errors->getBag($bag)->first($errorKey) ?: ($multiple ? $errors->getBag($bag)->first($errorKey.'.*') : null);
    }
    $hasError = filled($message);

    $toScalar = fn ($item) => $item instanceof \BackedEnum ? (string) $item->value : (string) $item;
    $selected = $old ? old($errorKey, $value) : $value;
    $selectedValues = collect(\Illuminate\Support\Arr::wrap($selected instanceof \Illuminate\Support\Collection ? $selected->all() : $selected))
        ->filter(fn ($item) => $item !== null)
        ->map($toScalar)
        ->all();
    $isSelected = fn ($optionValue) => in_array((string) $optionValue, $selectedValues, true);

    $describedBy = collect([$hint ? $id.'-hint' : null, $hasError ? $id.'-error' : null])->filter()->implode(' ');
@endphp

<div {{ $attributes->only('class')->class('flex flex-col gap-1.5') }}>
    @if ($label)
        <x-form.label :for="$id" :required="$required" :optional="$optional" @class(['sr-only' => $hideLabel])>{{ $label }}</x-form.label>
    @endif

    <div class="relative">
        <select
            id="{{ $id }}"
            name="{{ $name }}"
            @required($required)
            @if ($multiple) multiple @endif
            @if ($hasError) aria-invalid="true" @endif
            @if ($describedBy !== '') aria-describedby="{{ $describedBy }}" @endif
            {{ $attributes->except('class')->class([
                'block w-full appearance-none rounded-xl border-0 bg-white pl-3.5 text-sm text-ink-900 shadow-xs ring-1 ring-inset transition focus:ring-2 focus:ring-inset focus:outline-hidden disabled:cursor-not-allowed disabled:bg-ink-50 disabled:text-ink-500 dark:bg-white/5 dark:text-ink-50',
                'h-11 pr-10' => ! $multiple,
                'min-h-28 py-2 pr-3.5' => $multiple,
                'ring-ink-900/12 focus:ring-leaf-500 dark:ring-white/12 dark:focus:ring-leaf-400' => ! $hasError,
                'ring-berry-500/70 focus:ring-berry-500 dark:ring-berry-400/60 dark:focus:ring-berry-400' => $hasError,
            ]) }}
        >
            @if ($placeholder !== null && ! $multiple)
                <option value="" @selected($selectedValues === [] || $isSelected(''))>{{ $placeholder }}</option>
            @endif

            @if ($slot->hasActualContent())
                {{ $slot }}
            @else
                @foreach ($options as $optionValue => $optionLabel)
                    @if (is_array($optionLabel))
                        <optgroup label="{{ $optionValue }}">
                            @foreach ($optionLabel as $groupValue => $groupLabel)
                                <option value="{{ $groupValue }}" @selected($isSelected($groupValue))>{{ $groupLabel }}</option>
                            @endforeach
                        </optgroup>
                    @else
                        <option value="{{ $optionValue }}" @selected($isSelected($optionValue))>{{ $optionLabel }}</option>
                    @endif
                @endforeach
            @endif
        </select>

        @unless ($multiple)
            <x-icon name="chevron-down" class="pointer-events-none absolute top-1/2 right-3.5 size-4 -translate-y-1/2 text-ink-400 dark:text-ink-500" />
        @endunless
    </div>

    @if ($hint)
        <x-form.hint :id="$id.'-hint'">{{ $hint }}</x-form.hint>
    @endif
    @if ($hasError)
        <x-form.error :id="$id.'-error'" :message="$message" />
    @endif
</div>
