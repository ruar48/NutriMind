@props([
    'action',
    'method' => 'DELETE',
    'title' => 'Are you sure?',
    'message' => null,
    'confirm' => 'Delete',
    'cancel' => 'Cancel',
    'button' => 'Delete',
    'buttonVariant' => 'danger',
    'buttonSize' => 'sm',
    'buttonIcon' => 'trash-2',
    'danger' => true,
    'icon' => null,
    'id' => null,
])

@php
    $spoofedMethod = strtoupper($method);
    $formMethod = $spoofedMethod === 'GET' ? 'GET' : 'POST';
    $dialogId = $id ?? 'confirm-'.substr(md5($action.'|'.$spoofedMethod.'|'.$title.'|'.$button), 0, 12);
    $dialogIcon = $icon ?? ($danger ? 'triangle-alert' : 'circle-help');
@endphp

<form method="{{ $formMethod }}" action="{{ $action }}" data-submit-once {{ $attributes->class('contents') }}>
    @if ($formMethod === 'POST')
        @csrf
    @endif
    @if (! in_array($spoofedMethod, ['GET', 'POST'], true))
        @method($spoofedMethod)
    @endif
    {{ $fields ?? '' }}

    @if ($buttonVariant === 'menu')
        <x-dropdown.item type="submit" :icon="$buttonIcon" :danger="$danger" data-confirm-open="{{ $dialogId }}" data-dropdown-keep-open>
            {{ $trigger ?? $button }}
        </x-dropdown.item>
    @elseif ($buttonVariant === 'icon')
        <x-icon-button type="submit" :icon="$buttonIcon" :label="$button" :variant="$danger ? 'danger' : 'ghost'" size="sm" data-confirm-open="{{ $dialogId }}" />
    @else
        <x-button type="submit" :variant="$buttonVariant" :size="$buttonSize" :icon="$buttonIcon" data-confirm-open="{{ $dialogId }}">
            {{ $trigger ?? $button }}
        </x-button>
    @endif

    <dialog
        id="{{ $dialogId }}"
        data-confirm-dialog
        role="alertdialog"
        aria-labelledby="{{ $dialogId }}-title"
        @if ($message) aria-describedby="{{ $dialogId }}-message" @endif
        class="m-auto max-h-[calc(100dvh-2rem)] w-[calc(100%-2rem)] max-w-md overflow-y-auto rounded-3xl bg-white p-0 text-left text-ink-900 shadow-2xl ring-1 ring-ink-900/10 open:animate-pop dark:bg-ink-900 dark:text-ink-50 dark:ring-white/10"
    >
        <div class="flex gap-4 px-6 pt-6 pb-5">
            <span @class([
                'grid size-11 shrink-0 place-items-center rounded-2xl ring-1 ring-inset',
                'bg-berry-50 text-berry-600 ring-berry-600/15 dark:bg-berry-400/10 dark:text-berry-300 dark:ring-berry-400/20' => $danger,
                'bg-leaf-50 text-leaf-700 ring-leaf-600/15 dark:bg-leaf-400/10 dark:text-leaf-300 dark:ring-leaf-400/20' => ! $danger,
            ])>
                <x-icon :name="$dialogIcon" class="size-5" />
            </span>
            <div class="min-w-0 flex-1 pt-0.5">
                <h2 id="{{ $dialogId }}-title" class="font-display text-lg font-semibold tracking-tight text-ink-950 dark:text-white">{{ $title }}</h2>
                @if ($message)
                    <p id="{{ $dialogId }}-message" class="mt-1.5 text-sm text-ink-600 dark:text-ink-300">{{ $message }}</p>
                @endif
                @if ($slot->hasActualContent())
                    <div class="mt-4 text-sm text-ink-700 dark:text-ink-200">{{ $slot }}</div>
                @endif
            </div>
        </div>
        <div class="flex flex-col-reverse gap-2 rounded-b-3xl border-t border-ink-900/5 bg-ink-50/70 px-6 py-4 sm:flex-row sm:justify-end dark:border-white/8 dark:bg-white/3">
            <x-button variant="secondary" data-modal-close data-modal-initial-focus>{{ $cancel }}</x-button>
            <x-button type="submit" :variant="$danger ? 'danger' : 'primary'" :icon="$danger ? 'trash-2' : 'check'" data-confirm-accept>{{ $confirm }}</x-button>
        </div>
    </dialog>
</form>
