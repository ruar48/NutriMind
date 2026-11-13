@props([
    'name',
    'label' => null,
    'value' => null,
    'rows' => 4,
    'hint' => null,
    'required' => false,
    'optional' => false,
    'maxLength' => null,
    'bag' => 'default',
    'errorKey' => null,
    'id' => null,
    'hideLabel' => false,
    'old' => true,
])

@php
    $errorKey ??= \Illuminate\Support\Str::of($name)->replace(['[', ']'], ['.', ''])->trim('.')->toString();
    $id ??= \Illuminate\Support\Str::of($name)->replace(['[', ']', '.'], ['-', '', '-'])->trim('-')->toString();
    $message = isset($errors) ? $errors->getBag($bag)->first($errorKey) : null;
    $hasError = filled($message);
    $normalized = $value instanceof \BackedEnum ? $value->value : $value;
    $current = (string) ($old ? old($errorKey, $normalized) : $normalized);
    $limit = $maxLength !== null ? (int) $maxLength : null;
    $describedBy = collect([$hint ? $id.'-hint' : null, $hasError ? $id.'-error' : null, $limit ? $id.'-count' : null])->filter()->implode(' ');
@endphp

<div {{ $attributes->only('class')->class('flex flex-col gap-1.5') }}>
    @if ($label)
        <x-form.label :for="$id" :required="$required" :optional="$optional" @class(['sr-only' => $hideLabel])>{{ $label }}</x-form.label>
    @endif

    <textarea
        id="{{ $id }}"
        name="{{ $name }}"
        rows="{{ $rows }}"
        @required($required)
        @if ($limit) maxlength="{{ $limit }}" data-max-length="{{ $limit }}" @endif
        @if ($hasError) aria-invalid="true" @endif
        @if ($describedBy !== '') aria-describedby="{{ $describedBy }}" @endif
        {{ $attributes->except('class')->class([
            'block w-full resize-y rounded-xl border-0 bg-white px-3.5 py-2.5 text-sm leading-relaxed text-ink-900 shadow-xs ring-1 ring-inset transition placeholder:text-ink-400 focus:ring-2 focus:ring-inset focus:outline-hidden disabled:cursor-not-allowed disabled:bg-ink-50 dark:bg-white/5 dark:text-ink-50 dark:placeholder:text-ink-500',
            'ring-ink-900/12 focus:ring-leaf-500 dark:ring-white/12 dark:focus:ring-leaf-400' => ! $hasError,
            'ring-berry-500/70 focus:ring-berry-500 dark:ring-berry-400/60 dark:focus:ring-berry-400' => $hasError,
        ]) }}
    >{{ $current }}</textarea>

    @if ($hint || $limit)
        <div class="flex items-start justify-between gap-3">
            @if ($hint)
                <x-form.hint :id="$id.'-hint'">{{ $hint }}</x-form.hint>
            @else
                <span></span>
            @endif
            @if ($limit)
                <span
                    id="{{ $id }}-count"
                    data-char-count-for="{{ $id }}"
                    class="shrink-0 text-xs text-ink-500 tabular-nums data-near-limit:font-semibold data-near-limit:text-sun-800 data-over-limit:font-semibold data-over-limit:text-berry-700 dark:text-ink-400 dark:data-near-limit:text-sun-300 dark:data-over-limit:text-berry-300"
                >{{ number_format(mb_strlen($current)) }} / {{ number_format($limit) }}</span>
            @endif
        </div>
    @endif
    @if ($hasError)
        <x-form.error :id="$id.'-error'" :message="$message" />
    @endif
</div>
