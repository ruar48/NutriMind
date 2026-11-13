@props([
    'name',
    'label' => null,
    'accept' => null,
    'hint' => null,
    'required' => false,
    'optional' => false,
    'current' => null,
    'currentLabel' => null,
    'preview' => false,
    'removeName' => null,
    'removeLabel' => 'Remove the current file',
    'bag' => 'default',
    'errorKey' => null,
    'id' => null,
])

@php
    $errorKey ??= \Illuminate\Support\Str::of($name)->replace(['[', ']'], ['.', ''])->trim('.')->toString();
    $id ??= \Illuminate\Support\Str::of($name)->replace(['[', ']', '.'], ['-', '', '-'])->trim('-')->toString();
    $message = isset($errors) ? $errors->getBag($bag)->first($errorKey) : null;
    $hasError = filled($message);
    $describedBy = collect([$hint ? $id.'-hint' : null, $hasError ? $id.'-error' : null])->filter()->implode(' ');
    $previewId = $id.'-preview';
@endphp

<div data-file-field {{ $attributes->only('class')->class('flex flex-col gap-1.5') }}>
    @if ($label)
        <x-form.label :for="$id" :required="$required" :optional="$optional">{{ $label }}</x-form.label>
    @endif

    <div class="flex flex-col gap-3 sm:flex-row sm:items-stretch">
        @if ($preview)
            <div data-image-preview-wrapper @if (! $current) hidden @endif class="relative size-24 shrink-0 overflow-hidden rounded-2xl bg-ink-50 ring-1 ring-ink-900/10 dark:bg-white/5 dark:ring-white/10">
                <img id="{{ $previewId }}" @if ($current) src="{{ $current }}" @endif alt="{{ $currentLabel ?? 'Selected image preview' }}" class="size-full object-cover">
            </div>
        @endif

        <label for="{{ $id }}" @class([
            'group flex min-h-24 flex-1 cursor-pointer items-center gap-3 rounded-2xl border-2 border-dashed bg-white/70 px-4 py-4 transition hover:bg-leaf-50/50 has-focus-visible:outline-2 has-focus-visible:outline-offset-2 has-focus-visible:outline-leaf-500 dark:bg-white/3 dark:hover:bg-leaf-400/5',
            'border-ink-900/15 hover:border-leaf-500/60 dark:border-white/15 dark:hover:border-leaf-400/50' => ! $hasError,
            'border-berry-500/60 dark:border-berry-400/50' => $hasError,
        ])>
            <span class="grid size-10 shrink-0 place-items-center rounded-xl bg-leaf-50 text-leaf-700 ring-1 ring-leaf-600/15 transition ring-inset group-hover:scale-105 dark:bg-leaf-400/10 dark:text-leaf-300 dark:ring-leaf-400/20">
                <x-icon name="upload" class="size-5" />
            </span>
            <span class="min-w-0">
                <span class="block text-sm font-semibold text-ink-800 dark:text-ink-100">{{ $current ? 'Choose a replacement file' : 'Choose a file' }}</span>
                <span data-file-name-for="{{ $id }}" data-empty-text="No file selected" class="block truncate text-xs text-ink-500 dark:text-ink-400">No file selected</span>
            </span>
            <input
                id="{{ $id }}"
                type="file"
                name="{{ $name }}"
                class="sr-only"
                @if ($accept) accept="{{ $accept }}" @endif
                @required($required)
                @if ($preview) data-image-preview="{{ $previewId }}" @endif
                @if ($hasError) aria-invalid="true" @endif
                @if ($describedBy !== '') aria-describedby="{{ $describedBy }}" @endif
                {{ $attributes->except('class') }}
            >
        </label>
    </div>

    @if ($current && ! $preview)
        <div data-file-current class="flex items-center gap-2 text-sm text-ink-600 transition data-removing:line-through data-removing:opacity-50 dark:text-ink-300">
            <x-icon name="file-text" class="size-4 text-ink-400" />
            <a href="{{ $current }}" target="_blank" rel="noopener noreferrer" class="truncate font-medium text-leaf-700 hover:underline dark:text-leaf-300">{{ $currentLabel ?? 'Current file' }}</a>
        </div>
    @endif

    @if ($current && $removeName)
        <x-form.checkbox :name="$removeName" :label="$removeLabel" data-file-remove />
    @endif

    @if ($hint)
        <x-form.hint :id="$id.'-hint'">{{ $hint }}</x-form.hint>
    @endif
    @if ($hasError)
        <x-form.error :id="$id.'-error'" :message="$message" />
    @endif
</div>
