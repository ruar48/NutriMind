@php
    $options = [
        'light' => ['icon' => 'sun', 'label' => 'Light'],
        'dark' => ['icon' => 'moon', 'label' => 'Dark'],
        'system' => ['icon' => 'monitor', 'label' => 'System'],
    ];
@endphp

<x-dropdown align="right" width="sm" label="Change theme" data-js-only {{ $attributes }}>
    <x-slot:trigger>
        <span class="grid size-10 place-items-center rounded-xl text-ink-500 transition hover:bg-ink-900/5 hover:text-ink-900 pointer-coarse:size-11 dark:text-ink-400 dark:hover:bg-white/8 dark:hover:text-white" title="Change theme">
            @foreach ($options as $value => $option)
                <x-icon :name="$option['icon']" class="size-5" data-theme-icon="{{ $value }}" />
            @endforeach
        </span>
    </x-slot:trigger>

    <x-dropdown.label>Appearance</x-dropdown.label>
    @foreach ($options as $value => $option)
        <x-dropdown.item
            :icon="$option['icon']"
            data-theme-set="{{ $value }}"
            aria-pressed="false"
            class="aria-pressed:bg-leaf-50 aria-pressed:text-leaf-800 dark:aria-pressed:bg-leaf-400/10 dark:aria-pressed:text-leaf-200"
        >{{ $option['label'] }}</x-dropdown.item>
    @endforeach
</x-dropdown>
