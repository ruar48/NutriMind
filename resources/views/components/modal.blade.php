@props([
    'name',
    'title',
    'description' => null,
    'icon' => null,
    'size' => 'md',
    'open' => false,
    'action' => null,
    'method' => 'POST',
    'files' => false,
])

@php
    $sizes = [
        'sm' => 'max-w-md',
        'md' => 'max-w-lg',
        'lg' => 'max-w-2xl',
        'xl' => 'max-w-4xl',
    ];

    $titleId = 'modal-'.\Illuminate\Support\Str::slug($name).'-title';
    $descriptionId = 'modal-'.\Illuminate\Support\Str::slug($name).'-description';
    $spoofedMethod = strtoupper($method);
@endphp

<dialog
    data-modal="{{ $name }}"
    aria-labelledby="{{ $titleId }}"
    @if ($description) aria-describedby="{{ $descriptionId }}" @endif
    @if ($open) data-modal-autoopen @endif
    {{ $attributes->class([
        'm-auto max-h-[calc(100dvh-2rem)] w-[calc(100%-2rem)] overflow-y-auto overscroll-contain rounded-3xl bg-white p-0 text-ink-900 shadow-2xl ring-1 ring-ink-900/10 open:animate-pop dark:bg-ink-900 dark:text-ink-50 dark:ring-white/10',
        $sizes[$size] ?? $sizes['md'],
    ]) }}
>
    @if ($action)
        <form method="POST" action="{{ $action }}" @if ($files) enctype="multipart/form-data" @endif data-submit-once>
            @csrf
            @if ($spoofedMethod !== 'POST')
                @method($spoofedMethod)
            @endif
    @endif

    <div class="flex items-start gap-4 px-6 pt-6">
        @if ($icon)
            <span class="grid size-11 shrink-0 place-items-center rounded-2xl bg-leaf-50 text-leaf-700 ring-1 ring-leaf-600/15 ring-inset dark:bg-leaf-400/10 dark:text-leaf-300 dark:ring-leaf-400/20">
                <x-icon :name="$icon" class="size-5" />
            </span>
        @endif
        <div class="min-w-0 flex-1 pt-0.5">
            <h2 id="{{ $titleId }}" class="font-display text-lg font-semibold tracking-tight text-ink-950 dark:text-white">{{ $title }}</h2>
            @if ($description)
                <p id="{{ $descriptionId }}" class="mt-1 text-sm text-ink-500 dark:text-ink-400">{{ $description }}</p>
            @endif
        </div>
        <button type="button" data-modal-close class="-mt-1 -mr-2 grid size-10 shrink-0 place-items-center rounded-xl text-ink-400 transition hover:bg-ink-900/5 hover:text-ink-900 pointer-coarse:size-11 dark:hover:bg-white/8 dark:hover:text-white">
            <x-icon name="x" class="size-5" />
            <span class="sr-only">Close</span>
        </button>
    </div>

    <div class="px-6 py-5">
        {{ $slot }}
    </div>

    @isset($footer)
        <div class="flex flex-col-reverse gap-2 rounded-b-3xl border-t border-ink-900/5 bg-ink-50/70 px-6 py-4 sm:flex-row sm:items-center sm:justify-end dark:border-white/8 dark:bg-white/3">
            {{ $footer }}
        </div>
    @endisset

    @if ($action)
        </form>
    @endif
</dialog>
