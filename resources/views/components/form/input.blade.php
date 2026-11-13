@props([
    'name',
    'label' => null,
    'type' => 'text',
    'value' => null,
    'hint' => null,
    'required' => false,
    'optional' => false,
    'prefixIcon' => null,
    'suffixIcon' => null,
    'prefix' => null,
    'suffix' => null,
    'bag' => 'default',
    'errorKey' => null,
    'id' => null,
    'hideLabel' => false,
    'inputClass' => null,
    'old' => true,
])

@php
    $errorKey ??= \Illuminate\Support\Str::of($name)->replace(['[', ']'], ['.', ''])->trim('.')->toString();
    $id ??= \Illuminate\Support\Str::of($name)->replace(['[', ']', '.'], ['-', '', '-'])->trim('-')->toString();
    $message = isset($errors) ? $errors->getBag($bag)->first($errorKey) : null;
    $hasError = filled($message);

    $normalized = match (true) {
        $value instanceof \BackedEnum => $value->value,
        $value instanceof \DateTimeInterface && $type === 'date' => $value->format('Y-m-d'),
        $value instanceof \DateTimeInterface && $type === 'datetime-local' => $value->format('Y-m-d\TH:i'),
        $value instanceof \DateTimeInterface && $type === 'time' => $value->format('H:i'),
        default => $value,
    };
    $current = in_array($type, ['password', 'file'], true) ? null : ($old ? old($errorKey, $normalized) : $normalized);

    $describedBy = collect([$hint ? $id.'-hint' : null, $hasError ? $id.'-error' : null])->filter()->implode(' ');
    $hasLeading = $prefixIcon || filled($prefix);
    $hasTrailing = $suffixIcon || filled($suffix) || $hasError;
@endphp

<div {{ $attributes->only('class')->class('flex flex-col gap-1.5') }}>
    @if ($label)
        <x-form.label :for="$id" :required="$required" :optional="$optional" @class(['sr-only' => $hideLabel])>{{ $label }}</x-form.label>
    @endif

    <div class="relative">
        @if ($prefixIcon)
            <x-icon :name="$prefixIcon" class="pointer-events-none absolute top-1/2 left-3.5 size-4 -translate-y-1/2 text-ink-400 dark:text-ink-500" />
        @elseif (filled($prefix))
            <span class="pointer-events-none absolute top-1/2 left-3.5 -translate-y-1/2 text-sm text-ink-500 dark:text-ink-400">{{ $prefix }}</span>
        @endif

        <input
            id="{{ $id }}"
            name="{{ $name }}"
            type="{{ $type }}"
            @if ($current !== null && ! is_array($current)) value="{{ $current }}" @endif
            @required($required)
            @if ($hasError) aria-invalid="true" @endif
            @if ($describedBy !== '') aria-describedby="{{ $describedBy }}" @endif
            {{ $attributes->except('class')->class([
                'block h-11 w-full rounded-xl border-0 bg-white px-3.5 text-sm text-ink-900 shadow-xs ring-1 ring-inset transition placeholder:text-ink-400 focus:ring-2 focus:ring-inset focus:outline-hidden disabled:cursor-not-allowed disabled:bg-ink-50 disabled:text-ink-500 dark:bg-white/5 dark:text-ink-50 dark:placeholder:text-ink-500 dark:disabled:bg-white/3 dark:disabled:text-ink-400',
                'ring-ink-900/12 focus:ring-leaf-500 dark:ring-white/12 dark:focus:ring-leaf-400' => ! $hasError,
                'ring-berry-500/70 focus:ring-berry-500 dark:ring-berry-400/60 dark:focus:ring-berry-400' => $hasError,
                'pl-10' => $prefixIcon,
                'pl-9' => ! $prefixIcon && filled($prefix),
                'pr-12' => filled($suffix),
                'pr-10' => ! filled($suffix) && ($suffixIcon || $hasError),
                'file:mr-3 file:rounded-lg file:border-0 file:bg-leaf-50 file:px-3 file:py-1.5 file:text-sm file:font-semibold file:text-leaf-800 dark:file:bg-leaf-400/10 dark:file:text-leaf-200' => $type === 'file',
                $inputClass,
            ]) }}
        >

        @if (filled($suffix))
            <span class="pointer-events-none absolute top-1/2 right-3.5 -translate-y-1/2 text-sm text-ink-500 dark:text-ink-400">{{ $suffix }}</span>
        @elseif ($hasError)
            <x-icon name="circle-alert" class="pointer-events-none absolute top-1/2 right-3.5 size-4 -translate-y-1/2 text-berry-500 dark:text-berry-400" />
        @elseif ($suffixIcon)
            <x-icon :name="$suffixIcon" class="pointer-events-none absolute top-1/2 right-3.5 size-4 -translate-y-1/2 text-ink-400 dark:text-ink-500" />
        @endif
    </div>

    @if ($hint)
        <x-form.hint :id="$id.'-hint'">{{ $hint }}</x-form.hint>
    @endif
    @if ($hasError)
        <x-form.error :id="$id.'-error'" :message="$message" />
    @endif
</div>
