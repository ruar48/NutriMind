@props([
    'name',
    'label' => null,
    'value' => '1',
    'checked' => false,
    'description' => null,
    'uncheckedValue' => null,
    'bag' => 'default',
    'errorKey' => null,
    'id' => null,
    'old' => true,
])

@php
    $errorKey ??= \Illuminate\Support\Str::of($name)->replace(['[', ']'], ['.', ''])->trim('.')->toString();
    $baseId = \Illuminate\Support\Str::of($name)->replace(['[', ']', '.'], ['-', '', '-'])->trim('-')->toString();
    $id ??= str_ends_with($name, '[]') ? $baseId.'-'.\Illuminate\Support\Str::slug((string) $value) : $baseId;
    $message = isset($errors) ? ($errors->getBag($bag)->first($errorKey) ?: $errors->getBag($bag)->first($errorKey.'.*')) : null;
    $hasError = filled($message) && ! str_ends_with($name, '[]');

    $oldInput = old();
    $isChecked = $old && is_array($oldInput) && $oldInput !== []
        ? in_array((string) $value, array_map('strval', \Illuminate\Support\Arr::wrap(old($errorKey))), true)
        : (bool) $checked;
    $descriptionId = $description ? $id.'-description' : null;
@endphp

<div {{ $attributes->only('class')->class('flex flex-col gap-1.5') }}>
    <div class="flex items-start gap-3">
        @if ($uncheckedValue !== null)
            <input type="hidden" name="{{ $name }}" value="{{ $uncheckedValue }}">
        @endif
        <span class="relative mt-0.5 grid size-5 shrink-0 place-items-center">
            <input
                id="{{ $id }}"
                type="checkbox"
                name="{{ $name }}"
                value="{{ $value }}"
                @checked($isChecked)
                @if ($hasError) aria-invalid="true" @endif
                @if ($descriptionId) aria-describedby="{{ $descriptionId }}" @endif
                {{ $attributes->except('class')->class([
                    'peer size-5 cursor-pointer appearance-none rounded-md bg-white ring-1 ring-inset transition checked:bg-leaf-600 checked:ring-leaf-600 indeterminate:bg-leaf-600 indeterminate:ring-leaf-600 focus-visible:outline-2 focus-visible:outline-offset-2 disabled:cursor-not-allowed disabled:opacity-50 dark:bg-white/5 dark:checked:bg-leaf-500 dark:checked:ring-leaf-500 dark:indeterminate:bg-leaf-500',
                    'ring-ink-900/25 dark:ring-white/20' => ! $hasError,
                    'ring-berry-500 dark:ring-berry-400' => $hasError,
                ]) }}
            >
            <x-icon name="check" class="pointer-events-none absolute hidden size-3.5 text-white peer-checked:block" stroke-width="3" />
            <x-icon name="minus" class="pointer-events-none absolute hidden size-3.5 text-white peer-indeterminate:block" stroke-width="3" />
        </span>
        @if ($label || $description || $slot->hasActualContent())
            <div class="min-w-0 text-sm">
                <label for="{{ $id }}" class="cursor-pointer font-medium text-ink-800 dark:text-ink-100">{{ $label ?? $slot }}</label>
                @if ($description)
                    <p id="{{ $descriptionId }}" class="mt-0.5 text-ink-500 dark:text-ink-400">{{ $description }}</p>
                @endif
            </div>
        @endif
    </div>
    @if ($hasError)
        <x-form.error :message="$message" class="pl-8" />
    @endif
</div>
