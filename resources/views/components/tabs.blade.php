@props([
    'label' => 'Sections',
])

<nav aria-label="{{ $label }}" {{ $attributes->class('relative') }}>
    <div class="scrollbar-none -mb-px flex gap-1 overflow-x-auto border-b border-ink-900/8 dark:border-white/10">
        {{ $slot }}
    </div>
</nav>
