@props([
    'title',
    'eyebrow' => null,
    'description' => null,
    'color' => 'leaf',
    'illustration' => 'hero-produce',
    'headingLevel' => 'h1',
])

@php
    $accents = [
        'leaf' => ['base' => 'bg-leaf-900', 'glowA' => 'bg-leaf-400/50', 'glowB' => 'bg-sun-300/35', 'glowC' => 'bg-teal-300/25', 'eyebrow' => 'text-leaf-200', 'blob' => 'fill-leaf-300/12'],
        'grape' => ['base' => 'bg-grape-900', 'glowA' => 'bg-grape-400/55', 'glowB' => 'bg-berry-300/30', 'glowC' => 'bg-sky-300/25', 'eyebrow' => 'text-grape-200', 'blob' => 'fill-grape-300/14'],
        'carrot' => ['base' => 'bg-carrot-900', 'glowA' => 'bg-carrot-400/55', 'glowB' => 'bg-sun-300/40', 'glowC' => 'bg-berry-300/25', 'eyebrow' => 'text-carrot-200', 'blob' => 'fill-carrot-300/14'],
        'sun' => ['base' => 'bg-sun-900', 'glowA' => 'bg-sun-400/50', 'glowB' => 'bg-carrot-300/35', 'glowC' => 'bg-leaf-300/25', 'eyebrow' => 'text-sun-200', 'blob' => 'fill-sun-300/14'],
        'berry' => ['base' => 'bg-berry-900', 'glowA' => 'bg-berry-400/50', 'glowB' => 'bg-carrot-300/30', 'glowC' => 'bg-grape-300/25', 'eyebrow' => 'text-berry-200', 'blob' => 'fill-berry-300/14'],
        'sky' => ['base' => 'bg-sky-900', 'glowA' => 'bg-sky-400/50', 'glowB' => 'bg-teal-300/30', 'glowC' => 'bg-grape-300/25', 'eyebrow' => 'text-sky-200', 'blob' => 'fill-sky-300/14'],
        'teal' => ['base' => 'bg-teal-900', 'glowA' => 'bg-teal-400/50', 'glowB' => 'bg-leaf-300/30', 'glowC' => 'bg-sky-300/25', 'eyebrow' => 'text-teal-200', 'blob' => 'fill-teal-300/14'],
        'lime' => ['base' => 'bg-lime-900', 'glowA' => 'bg-lime-400/45', 'glowB' => 'bg-sun-300/35', 'glowC' => 'bg-leaf-300/25', 'eyebrow' => 'text-lime-200', 'blob' => 'fill-lime-300/14'],
        'pink' => ['base' => 'bg-pink-900', 'glowA' => 'bg-pink-400/50', 'glowB' => 'bg-sun-300/30', 'glowC' => 'bg-grape-300/25', 'eyebrow' => 'text-pink-200', 'blob' => 'fill-pink-300/14'],
        'indigo' => ['base' => 'bg-indigo-900', 'glowA' => 'bg-indigo-400/50', 'glowB' => 'bg-sky-300/30', 'glowC' => 'bg-grape-300/25', 'eyebrow' => 'text-indigo-200', 'blob' => 'fill-indigo-300/14'],
        'slate' => ['base' => 'bg-slate-800', 'glowA' => 'bg-slate-400/45', 'glowB' => 'bg-sky-300/25', 'glowC' => 'bg-leaf-300/20', 'eyebrow' => 'text-slate-200', 'blob' => 'fill-slate-300/12'],
    ];

    $accent = $accents[$color] ?? $accents['leaf'];
    $headingTag = in_array($headingLevel, ['h1', 'h2'], true) ? $headingLevel : 'h1';
@endphp

<section {{ $attributes->class(['relative isolate overflow-hidden rounded-3xl px-6 py-7 text-white shadow-lift ring-1 ring-white/10 sm:px-8 sm:py-9', $accent['base']]) }} data-animate>
    <div aria-hidden="true" class="pointer-events-none absolute inset-0 -z-10">
        <div @class(['absolute -top-24 -right-16 size-80 rounded-full blur-3xl', $accent['glowA']])></div>
        <div @class(['absolute right-1/4 -bottom-32 size-72 rounded-full blur-3xl', $accent['glowB']])></div>
        <div @class(['absolute top-1/3 -left-24 size-60 rounded-full blur-3xl', $accent['glowC']])></div>
        <svg viewBox="0 0 600 300" preserveAspectRatio="xMaxYMid slice" class="absolute inset-0 size-full">
            <path d="M360 -20c70 30 150 10 210 60s40 140-10 190-150 90-230 60-110-110-90-180 50-160 120-130Z" @class([$accent['blob']]) />
            <path d="M610 210c-40 20-60 70-120 80s-90-30-80-70 50-50 100-60 140 30 100 50Z" @class([$accent['blob']]) />
        </svg>
        <div class="absolute inset-0 bg-[radial-gradient(rgb(255_255_255/0.09)_1px,transparent_1px)] bg-size-[18px_18px] [mask-image:linear-gradient(to_left,#000,transparent_70%)]"></div>
    </div>

    <div class="relative grid items-center gap-6 md:grid-cols-[minmax(0,1fr)_auto]">
        <div class="min-w-0 max-w-2xl">
            @if ($eyebrow)
                <p @class(['mb-2 text-xs font-semibold tracking-[0.16em] uppercase', $accent['eyebrow']])>{{ $eyebrow }}</p>
            @endif
            <{{ $headingTag }} class="font-display text-2xl leading-tight font-bold tracking-tight text-balance sm:text-3xl lg:text-[2.5rem]">{{ $title }}</{{ $headingTag }}>
            @if ($description)
                <p class="mt-2.5 max-w-xl text-sm text-white/80 sm:text-base">{{ $description }}</p>
            @endif
            @if ($slot->hasActualContent())
                <div class="mt-5">{{ $slot }}</div>
            @endif
            @isset($actions)
                <div class="mt-6 flex flex-wrap items-center gap-2.5">{{ $actions }}</div>
            @endisset
        </div>
        @if ($illustration)
            <x-illustration :name="$illustration" class="hidden h-40 w-auto text-white/90 md:block lg:h-48" />
        @endif
    </div>
</section>
