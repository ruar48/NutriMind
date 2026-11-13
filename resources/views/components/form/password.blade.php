@props([
    'name' => 'password',
    'label' => 'Password',
    'hint' => null,
    'required' => false,
    'bag' => 'default',
    'errorKey' => null,
    'id' => null,
    'hideLabel' => false,
])

@php
    $errorKey ??= \Illuminate\Support\Str::of($name)->replace(['[', ']'], ['.', ''])->trim('.')->toString();
    $id ??= \Illuminate\Support\Str::of($name)->replace(['[', ']', '.'], ['-', '', '-'])->trim('-')->toString();
    $message = isset($errors) ? $errors->getBag($bag)->first($errorKey) : null;
    $hasError = filled($message);
    $describedBy = collect([$hint ? $id.'-hint' : null, $hasError ? $id.'-error' : null])->filter()->implode(' ');
@endphp

<div {{ $attributes->only('class')->class('flex flex-col gap-1.5') }}>
    @if ($label)
        <x-form.label :for="$id" :required="$required" @class(['sr-only' => $hideLabel])>{{ $label }}</x-form.label>
    @endif

    <div class="relative">
        <input
            id="{{ $id }}"
            name="{{ $name }}"
            type="password"
            data-password-input
            @required($required)
            @if ($hasError) aria-invalid="true" @endif
            @if ($describedBy !== '') aria-describedby="{{ $describedBy }}" @endif
            {{ $attributes->except('class')->class([
                'block h-11 w-full rounded-xl border-0 bg-white pr-12 pl-3.5 text-sm text-ink-900 shadow-xs ring-1 ring-inset transition placeholder:text-ink-400 focus:ring-2 focus:ring-inset focus:outline-hidden dark:bg-white/5 dark:text-ink-50 dark:placeholder:text-ink-500',
                'ring-ink-900/12 focus:ring-leaf-500 dark:ring-white/12 dark:focus:ring-leaf-400' => ! $hasError,
                'ring-berry-500/70 focus:ring-berry-500 dark:ring-berry-400/60 dark:focus:ring-berry-400' => $hasError,
            ]) }}
        >
        <button
            type="button"
            data-password-toggle
            data-js-only
            aria-controls="{{ $id }}"
            aria-pressed="false"
            aria-label="Show password"
            data-label-show="Show password"
            data-label-hide="Hide password"
            class="group absolute top-1/2 right-1 grid size-9 -translate-y-1/2 place-items-center rounded-lg text-ink-400 transition hover:bg-ink-900/5 hover:text-ink-700 pointer-coarse:size-11 dark:text-ink-500 dark:hover:bg-white/8 dark:hover:text-ink-200"
        >
            <x-icon name="eye" class="size-4 group-aria-pressed:hidden" />
            <x-icon name="eye-off" class="hidden size-4 group-aria-pressed:block" />
        </button>
    </div>

    @if ($hint)
        <x-form.hint :id="$id.'-hint'">{{ $hint }}</x-form.hint>
    @endif
    @if ($hasError)
        <x-form.error :id="$id.'-error'" :message="$message" />
    @endif
</div>
