@props([
    'name' => 'empty-plate',
    'label' => null,
])

@php
    $known = ['hero-produce', 'empty-plate', 'trophy', 'classroom', 'forum', 'lock', 'lost-map', 'hourglass', 'snail', 'spill', 'sprout', 'search', 'chart'];
    $name = in_array($name, $known, true) ? $name : 'empty-plate';
@endphp

<svg
    viewBox="0 0 200 160"
    fill="none"
    stroke="currentColor"
    stroke-width="2.5"
    stroke-linecap="round"
    stroke-linejoin="round"
    {{ $attributes->class('shrink-0') }}
    @if ($label) role="img" aria-label="{{ $label }}" @else aria-hidden="true" @endif
    data-illustration="{{ $name }}"
>
    @switch($name)
        @case('hero-produce')
            <path d="M18 72C36 28 92 12 128 22s58 34 56 58" stroke-dasharray="1 8" stroke-width="3" />
            <path d="M40 28l1.8 4.7 4.7 1.8-4.7 1.8L40 41l-1.8-4.7-4.7-1.8 4.7-1.8Z" class="fill-sun-300" stroke="none" />
            <path d="M170 16l1.4 3.6 3.6 1.4-3.6 1.4-1.4 3.6-1.4-3.6-3.6-1.4 3.6-1.4Z" class="fill-sun-300" stroke="none" />
            <path d="M118 97l25-50c2.4-4.8 9.6-1.9 7.8 3.2L131 101" class="fill-carrot-400" />
            <path d="M146 46c-1-6 2-11 6-13M150 49c5-3 11-2 14 1M144 44c-4-4-10-5-14-3" />
            <path d="M127 80l6 3M133 68l5 2.5" />
            <path d="M74 98c-13 0-20-10-20-21 0-10 7-18 16-18 4 0 6 2 9 2s5-2 9-2c9 0 16 8 16 18 0 11-7 21-20 21-2 0-3-1-5-1s-3 1-5 1Z" class="fill-berry-400" />
            <path d="M79 61c0-6 2-10 6-12" />
            <path d="M84 52c4-5 11-6 15-3-3 5-10 7-15 3Z" class="fill-leaf-400" />
            <circle cx="104" cy="76" r="10" class="fill-leaf-400" />
            <circle cx="117" cy="82" r="9" class="fill-leaf-500" />
            <path d="M28 96h144c0 26-30 44-72 44S28 122 28 96Z" class="fill-leaf-300/40" />
            <path d="M22 96h156" />
            <path d="M86 140v7M114 140v7M76 147h48" />
            <circle cx="172" cy="120" r="12" class="fill-sun-400" />
            <path d="M172 113.5l2 4.2 4.6.6-3.3 3.2.8 4.6-4.1-2.2-4.1 2.2.8-4.6-3.3-3.2 4.6-.6Z" class="fill-sun-100" stroke-width="1.5" />
            <path d="M34 130c0-14 10-24 26-24 0 14-10 24-26 24Z" class="fill-leaf-400" />
            <path d="M34 130l12-12" />
            @break

        @case('empty-plate')
            <ellipse cx="100" cy="94" rx="62" ry="34" class="fill-white dark:fill-ink-800" />
            <ellipse cx="100" cy="94" rx="42" ry="21" class="fill-ink-100 dark:fill-ink-900" />
            <circle cx="114" cy="90" r="4.5" class="fill-leaf-400" />
            <path d="M24 46v12a6 6 0 0 0 12 0V46M30 46v12M30 64v66" />
            <ellipse cx="174" cy="62" rx="8" ry="13" class="fill-white dark:fill-ink-800" />
            <path d="M174 75v55" />
            <path d="M86 50c-4-6 4-10 0-16M100 44c-4-6 4-10 0-16M114 50c-4-6 4-10 0-16" stroke-width="2" />
            <path d="M150 26l1.4 3.6 3.6 1.4-3.6 1.4-1.4 3.6-1.4-3.6-3.6-1.4 3.6-1.4Z" class="fill-sun-300" stroke="none" />
            <path d="M58 132c0-9 7-15 17-15 0 9-7 15-17 15Z" class="fill-leaf-300" />
            @break

        @case('trophy')
            <path d="M70 26h60v28c0 22-14 38-30 38S70 76 70 54Z" class="fill-sun-300" />
            <path d="M70 38H54c0 16 8 26 20 28M130 38h16c0 16-8 26-20 28" />
            <path d="M100 92v16" />
            <path d="M84 124l4-16h24l4 16Z" class="fill-sun-400" />
            <path d="M74 124h52v12H74Z" class="fill-carrot-400" />
            <path d="M100 41l4.5 9.1 10 1.5-7.3 7 1.7 10-8.9-4.7-8.9 4.7 1.7-10-7.3-7 10-1.5Z" class="fill-white dark:fill-sun-100" />
            <path d="M38 34l7 5M160 28l-6 7M30 92l8-2M170 96l-7-4" />
            <circle cx="46" cy="64" r="3.5" class="fill-berry-400" stroke="none" />
            <circle cx="156" cy="62" r="3.5" class="fill-sky-400" stroke="none" />
            <circle cx="150" cy="120" r="3" class="fill-leaf-400" stroke="none" />
            <path d="M52 120l1.4 3.6 3.6 1.4-3.6 1.4-1.4 3.6-1.4-3.6-3.6-1.4 3.6-1.4Z" class="fill-sun-300" stroke="none" />
            @break

        @case('classroom')
            <rect x="34" y="20" width="132" height="86" rx="8" class="fill-leaf-800" />
            <path d="M58 82V64M76 82V52M94 82V70" class="stroke-sun-300" stroke-width="7" stroke-linecap="butt" />
            <path d="M52 86h52" class="stroke-white/70" stroke-width="2" />
            <path d="M136 72c-9 0-14-7-14-14 0-7 5-12 11-12 3 0 4 1 6 1s3-1 6-1c6 0 11 5 11 12 0 7-5 14-14 14-1 0-2-.6-3-.6s-2 .6-3 .6Z" class="fill-berry-400 stroke-white/80" stroke-width="2" />
            <path d="M139 46c0-4 1-6 4-8" class="stroke-white/80" stroke-width="2" />
            <path d="M120 34l1.4 3.6 3.6 1.4-3.6 1.4-1.4 3.6-1.4-3.6-3.6-1.4 3.6-1.4Z" class="fill-sun-300" stroke="none" />
            <path d="M40 106h120" />
            <path d="M118 104h18" class="stroke-white" stroke-width="4" />
            <path d="M56 106l-8 34M144 106l8 34M52 124h96" />
            @break

        @case('forum')
            <path d="M26 40c0-10 8-18 18-18h70c10 0 18 8 18 18v30c0 10-8 18-18 18H62l-20 16V88c-9-1-16-8-16-18Z" class="fill-leaf-300/70" />
            <path d="M78 82c0-9 7-16 16-16h62c9 0 16 7 16 16v28c0 9-7 16-16 16h-6v16l-20-16H94c-9 0-16-7-16-16Z" class="fill-carrot-300/80" />
            <circle cx="56" cy="55" r="3.5" fill="currentColor" stroke="none" />
            <circle cx="75" cy="55" r="3.5" fill="currentColor" stroke="none" />
            <circle cx="94" cy="55" r="3.5" fill="currentColor" stroke="none" />
            <path d="M126 112c-7-5-12-9-12-14a6 6 0 0 1 12-2 6 6 0 0 1 12 2c0 5-5 9-12 14Z" class="fill-berry-400" />
            <path d="M170 34l1.4 3.6 3.6 1.4-3.6 1.4-1.4 3.6-1.4-3.6-3.6-1.4 3.6-1.4Z" class="fill-sun-300" stroke="none" />
            @break

        @case('lock')
            <path d="M74 70V52a26 26 0 0 1 52 0v18" stroke-width="5" />
            <rect x="56" y="70" width="88" height="66" rx="14" class="fill-sun-300" />
            <circle cx="100" cy="97" r="8" fill="currentColor" stroke="none" />
            <path d="M100 102v14" stroke-width="5" />
            <path d="M150 66c0-14 10-24 26-24 0 14-10 24-26 24Z" class="fill-leaf-400" />
            <path d="M150 66l12-12" />
            <path d="M36 40l1.4 3.6 3.6 1.4-3.6 1.4-1.4 3.6-1.4-3.6-3.6-1.4 3.6-1.4Z" class="fill-sun-300" stroke="none" />
            <path d="M40 116l1.1 2.8 2.8 1.1-2.8 1.1-1.1 2.8-1.1-2.8-2.8-1.1 2.8-1.1Z" class="fill-carrot-300" stroke="none" />
            @break

        @case('lost-map')
            <path d="M26 46l42-14 56 14 46-14v92l-46 14-56-14-42 14Z" class="fill-white dark:fill-ink-800" />
            <path d="M68 32v92M124 46v92" />
            <path d="M42 108c14-18 28-6 40-24s26-22 38-8" stroke-dasharray="2 7" />
            <path d="M42 116l6-6M48 116l-6-6" class="stroke-berry-500" stroke-width="3" />
            <path d="M150 46c-11 0-20 9-20 20 0 15 20 34 20 34s20-19 20-34c0-11-9-20-20-20Z" class="fill-carrot-400" />
            <path d="M145 63a5 5 0 1 1 7 4.6c-1.5.7-2 1.6-2 3.4" stroke-width="2.5" />
            <circle cx="150" cy="77" r="1.8" fill="currentColor" stroke="none" />
            <path d="M178 22l1.4 3.6 3.6 1.4-3.6 1.4-1.4 3.6-1.4-3.6-3.6-1.4 3.6-1.4Z" class="fill-sun-300" stroke="none" />
            @break

        @case('hourglass')
            <path d="M64 22h72M64 138h72" stroke-width="5" />
            <path d="M74 22c0 28 24 36 24 58s-24 30-24 58M126 22c0 28-24 36-24 58s24 30 24 58" class="fill-white/40 dark:fill-white/5" />
            <path d="M82 40h36c-3 12-12 18-18 24-6-6-15-12-18-24Z" class="fill-sun-300" stroke-width="2" />
            <path d="M80 132c4-14 12-20 20-24 8 4 16 10 20 24Z" class="fill-sun-400" stroke-width="2" />
            <path d="M100 82v18" stroke-dasharray="1 5" />
            <path d="M150 70c0-12 9-20 22-20 0 12-9 20-22 20Z" class="fill-leaf-400" />
            <path d="M150 70l10-10" />
            <path d="M38 52l1.4 3.6 3.6 1.4-3.6 1.4-1.4 3.6-1.4-3.6-3.6-1.4 3.6-1.4Z" class="fill-sun-300" stroke="none" />
            @break

        @case('snail')
            <path d="M34 124h104c14 0 24-10 24-24v-8" class="fill-leaf-300" />
            <path d="M34 124c0-8 6-12 14-12h12" />
            <circle cx="98" cy="86" r="32" class="fill-carrot-300" />
            <path d="M98 86a5 5 0 0 1 10 0 10 10 0 0 1-20 0 15 15 0 0 1 30 0 20 20 0 0 1-40 0 25 25 0 0 1 48-8" stroke-width="2.5" />
            <path d="M156 92l-6-24M166 94l6-22" />
            <circle cx="150" cy="66" r="4" fill="currentColor" stroke="none" />
            <circle cx="172" cy="70" r="4" fill="currentColor" stroke="none" />
            <path d="M138 26h12l-12 12h12M160 14h8l-8 8h8" stroke-width="2.5" />
            <path d="M24 136h152" stroke-dasharray="1 7" />
            @break

        @case('spill')
            <path d="M44 100l40-40 30 30-40 40Z" class="fill-white dark:fill-ink-800" />
            <path d="M60 116l26-26 14 14-26 26Z" class="fill-carrot-300" stroke="none" />
            <path d="M44 100l40-40 30 30-40 40Z" />
            <path d="M92 128c10-8 28-6 42-10s26 2 30 10-14 14-36 14-44-4-36-14Z" class="fill-carrot-300" />
            <circle cx="150" cy="104" r="4" class="fill-carrot-400" stroke="none" />
            <circle cx="164" cy="96" r="2.5" class="fill-carrot-400" stroke="none" />
            <circle cx="152" cy="44" r="16" class="fill-sun-300" />
            <path d="M152 28v32M136 44h32M141 33l22 22M163 33l-22 22" stroke-width="1.5" />
            <path d="M40 40l1.4 3.6 3.6 1.4-3.6 1.4-1.4 3.6-1.4-3.6-3.6-1.4 3.6-1.4Z" class="fill-sun-300" stroke="none" />
            @break

        @case('sprout')
            <path d="M70 104h60l-8 36H78Z" class="fill-carrot-400" />
            <path d="M64 94h72v12H64Z" class="fill-carrot-300" />
            <path d="M100 94V62" />
            <path d="M100 74c-16 0-26-10-26-24 16 0 26 10 26 24Z" class="fill-leaf-400" />
            <path d="M100 66c0-16 10-26 26-26 0 16-10 26-26 26Z" class="fill-leaf-300" />
            <path d="M144 34h28v26h-28Z" class="fill-sky-300" />
            <path d="M144 42l-18-12" stroke-width="3" />
            <path d="M172 40c9 0 9 16 0 16" />
            <path d="M120 38v6M114 46v6M126 48v6" stroke-width="2" class="stroke-sky-400" />
            <path d="M40 60l1.4 3.6 3.6 1.4-3.6 1.4-1.4 3.6-1.4-3.6-3.6-1.4 3.6-1.4Z" class="fill-sun-300" stroke="none" />
            @break

        @case('search')
            <circle cx="88" cy="72" r="36" class="fill-white dark:fill-ink-800" />
            <path d="M114 98l32 32" stroke-width="9" />
            <path d="M72 88c0-18 12-30 32-30 0 18-12 30-32 30Z" class="fill-leaf-400" />
            <path d="M72 88l16-16" />
            <path d="M150 34l1.4 3.6 3.6 1.4-3.6 1.4-1.4 3.6-1.4-3.6-3.6-1.4 3.6-1.4Z" class="fill-sun-300" stroke="none" />
            <path d="M36 112l1.1 2.8 2.8 1.1-2.8 1.1-1.1 2.8-1.1-2.8-2.8-1.1 2.8-1.1Z" class="fill-carrot-300" stroke="none" />
            @break

        @case('chart')
            <path d="M34 22v106h136" />
            <rect x="52" y="96" width="16" height="32" rx="3" class="fill-ink-200 dark:fill-ink-700" stroke="none" />
            <rect x="80" y="80" width="16" height="48" rx="3" class="fill-ink-200 dark:fill-ink-700" stroke="none" />
            <rect x="108" y="88" width="16" height="40" rx="3" class="fill-ink-200 dark:fill-ink-700" stroke="none" />
            <path d="M50 92c18-8 32-26 50-20s34-22 50-30" stroke-dasharray="3 7" />
            <path d="M160 62V42" />
            <path d="M160 50c-10 0-16-6-16-15 10 0 16 6 16 15Z" class="fill-leaf-400" />
            <path d="M160 46c0-10 6-16 16-16 0 10-6 16-16 16Z" class="fill-leaf-300" />
            @break
    @endswitch
</svg>
