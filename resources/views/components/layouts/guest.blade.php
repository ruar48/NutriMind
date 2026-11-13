@props([
    'title' => null,
    'portal' => 'leaf',
    'heading' => 'Nutrition learning that feels like play',
    'subheading' => 'Missions, quizzes and game worlds that turn healthy-eating lessons into progress your school can see.',
    'schoolName' => null,
])

@php
    $accents = [
        'leaf' => ['base' => 'bg-leaf-900', 'glowA' => 'bg-leaf-400/45', 'glowB' => 'bg-sun-300/30', 'strip' => 'bg-leaf-600', 'chip' => 'text-leaf-200'],
        'grape' => ['base' => 'bg-grape-900', 'glowA' => 'bg-grape-400/50', 'glowB' => 'bg-berry-300/25', 'strip' => 'bg-grape-600', 'chip' => 'text-grape-200'],
        'carrot' => ['base' => 'bg-carrot-900', 'glowA' => 'bg-carrot-400/50', 'glowB' => 'bg-sun-300/35', 'strip' => 'bg-carrot-600', 'chip' => 'text-carrot-200'],
        'sun' => ['base' => 'bg-sun-900', 'glowA' => 'bg-sun-400/45', 'glowB' => 'bg-carrot-300/30', 'strip' => 'bg-sun-500', 'chip' => 'text-sun-200'],
        'berry' => ['base' => 'bg-berry-900', 'glowA' => 'bg-berry-400/45', 'glowB' => 'bg-carrot-300/25', 'strip' => 'bg-berry-600', 'chip' => 'text-berry-200'],
        'sky' => ['base' => 'bg-sky-900', 'glowA' => 'bg-sky-400/45', 'glowB' => 'bg-teal-300/25', 'strip' => 'bg-sky-600', 'chip' => 'text-sky-200'],
        'teal' => ['base' => 'bg-teal-900', 'glowA' => 'bg-teal-400/45', 'glowB' => 'bg-leaf-300/25', 'strip' => 'bg-teal-600', 'chip' => 'text-teal-200'],
        'lime' => ['base' => 'bg-lime-900', 'glowA' => 'bg-lime-400/40', 'glowB' => 'bg-sun-300/30', 'strip' => 'bg-lime-600', 'chip' => 'text-lime-200'],
        'pink' => ['base' => 'bg-pink-900', 'glowA' => 'bg-pink-400/45', 'glowB' => 'bg-sun-300/25', 'strip' => 'bg-pink-600', 'chip' => 'text-pink-200'],
        'indigo' => ['base' => 'bg-indigo-900', 'glowA' => 'bg-indigo-400/45', 'glowB' => 'bg-sky-300/25', 'strip' => 'bg-indigo-600', 'chip' => 'text-indigo-200'],
        'slate' => ['base' => 'bg-slate-800', 'glowA' => 'bg-slate-400/40', 'glowB' => 'bg-leaf-300/20', 'strip' => 'bg-slate-600', 'chip' => 'text-slate-200'],
    ];

    $accent = $accents[$portal] ?? $accents['leaf'];
    $schoolName = filled($schoolName) ? $schoolName : config('nutrimind.settings.school_name', 'NutriMind');
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <x-shell.head :title="$title" />
    </head>
    <body class="min-h-dvh">
        <div class="grid min-h-dvh lg:grid-cols-[minmax(0,5fr)_minmax(0,7fr)]">
            <aside @class(['relative isolate hidden overflow-hidden p-10 text-white lg:flex lg:flex-col xl:p-14', $accent['base']]) data-portal="{{ $portal }}">
                <div aria-hidden="true" class="pointer-events-none absolute inset-0 -z-10">
                    <div @class(['absolute -top-32 -right-24 size-[28rem] rounded-full blur-3xl', $accent['glowA']])></div>
                    <div @class(['absolute -bottom-40 -left-24 size-[26rem] rounded-full blur-3xl', $accent['glowB']])></div>
                    <div class="absolute inset-0 bg-[radial-gradient(rgb(255_255_255/0.08)_1px,transparent_1px)] bg-size-[20px_20px] [mask-image:linear-gradient(to_bottom,#000,transparent_85%)]"></div>
                </div>

                <a href="{{ url('/') }}" class="w-fit rounded-lg">
                    <x-logo inverted size="md" />
                </a>

                <div class="mt-auto max-w-md">
                    <x-illustration name="hero-produce" class="-ml-2 h-44 w-auto text-white/90 xl:h-52" />
                    <h2 class="mt-8 font-display text-3xl leading-tight font-bold tracking-tight text-balance xl:text-4xl">{{ $heading }}</h2>
                    <p class="mt-3 text-base text-white/75">{{ $subheading }}</p>
                    <ul class="mt-8 grid gap-3 text-sm text-white/85">
                        <li class="flex items-center gap-3"><x-icon name="map" @class(['size-5', $accent['chip']]) />Game worlds aligned to DepEd competencies</li>
                        <li class="flex items-center gap-3"><x-icon name="chart-line" @class(['size-5', $accent['chip']]) />Live progress for every learner and class</li>
                        <li class="flex items-center gap-3"><x-icon name="hand-heart" @class(['size-5', $accent['chip']]) />Families stay close through a moderated forum</li>
                    </ul>
                </div>

                <p class="mt-12 text-xs text-white/55">{{ $schoolName }}</p>
            </aside>

            <main id="content" class="relative isolate flex min-h-dvh flex-col">
                <div aria-hidden="true" class="bg-dot-grid pointer-events-none absolute inset-x-0 top-0 -z-10 h-96 [mask-image:linear-gradient(to_bottom,#000,transparent)]"></div>

                <div @class(['h-1.5 lg:hidden', $accent['strip']])></div>
                <div class="flex items-center justify-between px-5 pt-6 sm:px-8 lg:hidden">
                    <a href="{{ url('/') }}" class="rounded-lg">
                        <x-logo size="sm" />
                    </a>
                </div>

                <div class="flex flex-1 items-center justify-center px-4 py-10 sm:px-8">
                    <div class="w-full max-w-md" data-animate>
                        {{ $slot }}
                    </div>
                </div>

                <footer class="px-6 pb-6 text-center text-xs text-ink-500 dark:text-ink-400">
                    &copy; {{ now()->year }} {{ $schoolName }} · NutriMind
                </footer>
            </main>
        </div>
    </body>
</html>
