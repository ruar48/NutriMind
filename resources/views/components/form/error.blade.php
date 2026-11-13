@props([
    'for' => null,
    'bag' => 'default',
    'message' => null,
    'id' => null,
])

@php
    $errorKey = $for !== null ? \Illuminate\Support\Str::of($for)->replace(['[', ']'], ['.', ''])->trim('.')->toString() : null;
    $text = $message;

    if ($text === null && $errorKey !== null && isset($errors)) {
        $text = $errors->getBag($bag)->first($errorKey) ?: $errors->getBag($bag)->first($errorKey.'.*');
    }
@endphp

@if (filled($text))
    <p @if ($id) id="{{ $id }}" @endif {{ $attributes->class('flex items-start gap-1.5 text-xs font-medium text-berry-700 dark:text-berry-300') }} data-error-for="{{ $errorKey }}">
        <x-icon name="circle-alert" class="mt-px size-3.5" stroke-width="2" />
        <span>{{ $text }}</span>
    </p>
@endif
