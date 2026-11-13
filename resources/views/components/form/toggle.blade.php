@props([
    'name',
    'label',
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
    $id ??= \Illuminate\Support\Str::of($name)->replace(['[', ']', '.'], ['-', '', '-'])->trim('-')->toString();
    $message = isset($errors) ? $errors->getBag($bag)->first($errorKey) : null;
    $oldInput = old();
    $isChecked = $old && is_array($oldInput) && $oldInput !== []
        ? in_array((string) $value, array_map('strval', \Illuminate\Support\Arr::wrap(old($errorKey))), true)
        : (bool) $checked;
    $descriptionId = $description ? $id.'-description' : null;
@endphp

<div {{ $attributes->only('class')->class('flex flex-col gap-1.5') }}>
    <label for="{{ $id }}" class="group flex cursor-pointer items-start justify-between gap-4">
        <span class="min-w-0 text-sm">
            <span class="font-medium text-ink-800 dark:text-ink-100">{{ $label }}</span>
            @if ($description)
                <span id="{{ $descriptionId }}" class="mt-0.5 block text-ink-500 dark:text-ink-400">{{ $description }}</span>
            @endif
        </span>
        <span class="relative inline-flex shrink-0 items-center">
            @if ($uncheckedValue !== null)
                <input type="hidden" name="{{ $name }}" value="{{ $uncheckedValue }}">
            @endif
            <input
                id="{{ $id }}"
                type="checkbox"
                role="switch"
                name="{{ $name }}"
                value="{{ $value }}"
                @checked($isChecked)
                @if ($descriptionId) aria-describedby="{{ $descriptionId }}" @endif
                {{ $attributes->except('class')->class('peer sr-only') }}
            >
            <span aria-hidden="true" class="h-7 w-12 rounded-full bg-ink-200 shadow-inner ring-1 ring-ink-900/5 ring-inset transition duration-200 peer-checked:bg-leaf-600 peer-focus-visible:outline-2 peer-focus-visible:outline-offset-2 peer-focus-visible:outline-leaf-500 peer-disabled:opacity-50 dark:bg-white/12 dark:peer-checked:bg-leaf-500"></span>
            <span aria-hidden="true" class="pointer-events-none absolute left-1 size-5 rounded-full bg-white shadow-sm ring-1 ring-ink-900/5 transition duration-200 ease-out peer-checked:translate-x-5"></span>
        </span>
    </label>
    @if (filled($message))
        <x-form.error :message="$message" />
    @endif
</div>
