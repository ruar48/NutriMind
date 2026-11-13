@php
    $schoolName = filled($schoolName ?? null) ? $schoolName : config('nutrimind.settings.school_name', 'NutriMind');
    $hasRoute = fn (string $name): bool => \Illuminate\Support\Facades\Route::has($name);
    $signInUrl = $hasRoute('login') ? route('login') : url('/login');
    $portalUrl = fn (\App\Enums\UserRole $role): string => $hasRoute('login.portal') ? route('login.portal', $role->value) : url('/login/'.$role->value);

    $portalCopy = [
        'admin' => 'Set up school years, subjects and accounts, keep game content aligned to lesson plans, and read school-wide analytics.',
        'teacher' => 'Run your classrooms, build reviewed quizzes from a shared question bank, and coach every learner with live progress data.',
        'parent' => 'Follow your child’s missions, quiz results and rewards, read teacher feedback, and swap ideas with other families.',
    ];

    $portalTones = [
        'leaf' => ['tile' => 'bg-leaf-600 text-white', 'glow' => 'bg-leaf-400/25', 'link' => 'text-leaf-700 dark:text-leaf-300', 'ring' => 'hover:ring-leaf-500/40'],
        'grape' => ['tile' => 'bg-grape-600 text-white', 'glow' => 'bg-grape-400/25', 'link' => 'text-grape-700 dark:text-grape-300', 'ring' => 'hover:ring-grape-500/40'],
        'carrot' => ['tile' => 'bg-carrot-600 text-white', 'glow' => 'bg-carrot-400/25', 'link' => 'text-carrot-700 dark:text-carrot-300', 'ring' => 'hover:ring-carrot-500/40'],
        'sun' => ['tile' => 'bg-sun-400 text-sun-950', 'glow' => 'bg-sun-300/30', 'link' => 'text-sun-800 dark:text-sun-300', 'ring' => 'hover:ring-sun-500/40'],
        'berry' => ['tile' => 'bg-berry-600 text-white', 'glow' => 'bg-berry-400/25', 'link' => 'text-berry-700 dark:text-berry-300', 'ring' => 'hover:ring-berry-500/40'],
        'sky' => ['tile' => 'bg-sky-600 text-white', 'glow' => 'bg-sky-400/25', 'link' => 'text-sky-700 dark:text-sky-300', 'ring' => 'hover:ring-sky-500/40'],
        'teal' => ['tile' => 'bg-teal-600 text-white', 'glow' => 'bg-teal-400/25', 'link' => 'text-teal-700 dark:text-teal-300', 'ring' => 'hover:ring-teal-500/40'],
        'lime' => ['tile' => 'bg-lime-500 text-lime-950', 'glow' => 'bg-lime-400/25', 'link' => 'text-lime-800 dark:text-lime-300', 'ring' => 'hover:ring-lime-500/40'],
        'pink' => ['tile' => 'bg-pink-600 text-white', 'glow' => 'bg-pink-400/25', 'link' => 'text-pink-700 dark:text-pink-300', 'ring' => 'hover:ring-pink-500/40'],
        'indigo' => ['tile' => 'bg-indigo-600 text-white', 'glow' => 'bg-indigo-400/25', 'link' => 'text-indigo-700 dark:text-indigo-300', 'ring' => 'hover:ring-indigo-500/40'],
        'slate' => ['tile' => 'bg-slate-600 text-white', 'glow' => 'bg-slate-400/25', 'link' => 'text-slate-700 dark:text-slate-300', 'ring' => 'hover:ring-slate-500/40'],
    ];

    $features = [
        ['icon' => 'map', 'tone' => 'bg-leaf-50 text-leaf-700 ring-leaf-600/15 dark:bg-leaf-400/10 dark:text-leaf-300 dark:ring-leaf-400/20', 'title' => 'Game worlds', 'text' => 'Learning modules become worlds to explore, each aligned to Grade 5–6 health competencies and quarters.'],
        ['icon' => 'flag', 'tone' => 'bg-carrot-50 text-carrot-700 ring-carrot-600/15 dark:bg-carrot-400/10 dark:text-carrot-300 dark:ring-carrot-400/20', 'title' => 'Missions', 'text' => 'Story, daily and weekly missions reward healthy choices with points, levels and badges.'],
        ['icon' => 'clipboard-check', 'tone' => 'bg-grape-50 text-grape-700 ring-grape-600/15 dark:bg-grape-400/10 dark:text-grape-300 dark:ring-grape-400/20', 'title' => 'Quizzes', 'text' => 'Teachers publish reviewed quizzes with images and videos — results arrive even after offline play syncs.'],
        ['icon' => 'chart-line', 'tone' => 'bg-sky-50 text-sky-700 ring-sky-600/15 dark:bg-sky-400/10 dark:text-sky-300 dark:ring-sky-400/20', 'title' => 'Analytics', 'text' => 'Trends, DepEd score bands and at-risk alerts for every class, subject and quarter.'],
        ['icon' => 'messages-square', 'tone' => 'bg-berry-50 text-berry-700 ring-berry-600/15 dark:bg-berry-400/10 dark:text-berry-300 dark:ring-berry-400/20', 'title' => 'Parent forum', 'text' => 'A moderated space where families and teachers share ideas for healthier homes.'],
    ];
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <x-shell.head title="Nutrition learning that feels like play" description="NutriMind turns Grade 5–6 nutrition lessons into a game, with portals for administrators, teachers and parents." />
    </head>
    <body class="min-h-dvh">
        <a href="#content" class="fixed top-3 left-3 z-[70] -translate-y-24 rounded-xl bg-leaf-700 px-4 py-2.5 text-sm font-semibold text-white shadow-lift transition focus:translate-y-0">Skip to content</a>

        <div class="relative isolate overflow-hidden">
            <div aria-hidden="true" class="pointer-events-none absolute inset-0 -z-10">
                <div class="bg-dot-grid absolute inset-x-0 top-0 h-[44rem] [mask-image:linear-gradient(to_bottom,#000,transparent)]"></div>
                <div class="absolute -top-48 right-[-10rem] size-[40rem] rounded-full bg-leaf-300/30 blur-3xl dark:bg-leaf-500/10"></div>
                <div class="absolute top-[28rem] left-[-12rem] size-[32rem] rounded-full bg-sun-300/25 blur-3xl dark:bg-sun-400/5"></div>
            </div>

            <header class="mx-auto flex max-w-7xl items-center justify-between gap-4 px-4 py-5 sm:px-8">
                <a href="{{ url('/') }}" class="rounded-lg">
                    <x-logo />
                </a>
                <nav class="flex items-center gap-2" aria-label="Site">
                    <x-theme-switch />
                    <x-button :href="$signInUrl" variant="secondary" icon="log-in">Sign in</x-button>
                </nav>
            </header>

            <main id="content">
                <section class="mx-auto grid max-w-7xl items-center gap-12 px-4 pt-8 pb-20 sm:px-8 lg:grid-cols-[minmax(0,1.05fr)_minmax(0,1fr)] lg:pt-14 lg:pb-28">
                    <div data-animate>
                        <p class="inline-flex max-w-full items-center gap-2 rounded-full bg-white px-3 py-1.5 text-xs font-semibold text-leaf-800 shadow-card ring-1 ring-leaf-600/15 dark:bg-white/5 dark:text-leaf-200 dark:ring-leaf-400/20">
                            <x-icon name="sparkles" class="size-3.5 text-sun-500" />
                            <span class="truncate">{{ $schoolName }}</span>
                        </p>
                        <h1 class="mt-6 font-display text-[2.6rem] leading-[1.04] font-extrabold tracking-tight text-ink-950 sm:text-6xl lg:text-[4.25rem] dark:text-white">
                            Nutrition learning that feels like
                            <span class="relative whitespace-nowrap text-leaf-600 dark:text-leaf-400">
                                play
                                <svg aria-hidden="true" viewBox="0 0 120 14" preserveAspectRatio="none" class="absolute -bottom-2 left-0 h-3 w-full text-sun-400" fill="none">
                                    <path d="M3 9c18-6 36-6 54-2s39 5 60-3" stroke="currentColor" stroke-width="5" stroke-linecap="round" />
                                </svg>
                            </span>
                        </h1>
                        <p class="mt-7 max-w-xl text-lg text-ink-600 dark:text-ink-300">
                            NutriMind turns Grade 5 and 6 nutrition lessons into a game of missions, quizzes and rewards — and gives administrators, teachers and families a clear view of every learner’s progress.
                        </p>
                        <div class="mt-9 flex flex-wrap items-center gap-3">
                            <x-button href="#portals" size="lg" icon-trailing="arrow-right">Choose your portal</x-button>
                            <x-button href="#features" variant="secondary" size="lg">See what’s inside</x-button>
                        </div>
                    </div>

                    <div class="relative" data-animate style="--stagger: 2">
                        <div class="relative overflow-hidden rounded-[2rem] bg-leaf-900 p-8 text-white shadow-lift ring-1 ring-white/10">
                            <div aria-hidden="true" class="pointer-events-none absolute inset-0">
                                <div class="absolute -top-20 -right-16 size-72 rounded-full bg-leaf-400/45 blur-3xl"></div>
                                <div class="absolute -bottom-24 left-10 size-64 rounded-full bg-sun-300/30 blur-3xl"></div>
                                <div class="absolute inset-0 bg-[radial-gradient(rgb(255_255_255/0.08)_1px,transparent_1px)] bg-size-[18px_18px]"></div>
                            </div>
                            <x-illustration name="hero-produce" label="A bowl of fresh produce with game coins and sparkles" class="relative mx-auto h-64 w-auto text-white/90 sm:h-72" />
                        </div>

                        <div class="absolute -bottom-8 -left-2 w-64 rounded-2xl bg-white p-4 shadow-lift ring-1 ring-ink-900/5 sm:-left-8 dark:bg-ink-900 dark:ring-white/10" aria-hidden="true">
                            <div class="flex items-center gap-3">
                                <x-avatar name="Ana Reyes" size="sm" />
                                <div class="min-w-0">
                                    <p class="truncate text-sm font-semibold text-ink-900 dark:text-white">Ana Reyes</p>
                                    <p class="truncate text-xs text-ink-500 dark:text-ink-400">Grade 6 · Sampaguita</p>
                                </div>
                                <x-streak :days="5" size="sm" class="ml-auto" />
                            </div>
                            <x-xp-bar :level="7" :current="320" :needed="500" :show-labels="false" class="mt-3" />
                        </div>

                        <div class="absolute -top-5 right-2 flex items-center gap-2 rounded-2xl bg-white px-3 py-2.5 shadow-lift ring-1 ring-ink-900/5 sm:-right-4 dark:bg-ink-900 dark:ring-white/10" aria-hidden="true">
                            <x-level-badge :level="12" size="sm" />
                            <div>
                                <p class="text-[11px] text-ink-500 dark:text-ink-400">Quiz score</p>
                                <x-score-pill :value="92.5" size="sm" />
                            </div>
                        </div>
                    </div>
                </section>

                <section id="portals" class="mx-auto max-w-7xl scroll-mt-8 px-4 pb-20 sm:px-8 lg:pb-28" aria-labelledby="portals-title">
                    <div class="max-w-2xl">
                        <p class="text-xs font-semibold tracking-[0.16em] text-leaf-700 uppercase dark:text-leaf-300">Portals</p>
                        <h2 id="portals-title" class="mt-2 font-display text-3xl font-bold tracking-tight text-ink-950 sm:text-4xl dark:text-white">Three portals, one learning journey</h2>
                        <p class="mt-3 text-base text-ink-600 dark:text-ink-300">Learners play in the NutriMind game. Everyone who supports them signs in here.</p>
                    </div>

                    <div class="mt-10 grid gap-5 md:grid-cols-3">
                        @foreach (\App\Enums\UserRole::webPortals() as $role)
                            @php $tone = $portalTones[$role->color()] ?? $portalTones['leaf']; @endphp
                            <a href="{{ $portalUrl($role) }}" @class(['group relative isolate flex flex-col overflow-hidden rounded-3xl bg-white p-6 shadow-card ring-1 ring-ink-900/5 transition duration-200 hover:-translate-y-1 hover:shadow-lift focus-visible:outline-2 focus-visible:outline-offset-2 sm:p-7 dark:bg-ink-900 dark:ring-white/8', $tone['ring']]) data-animate style="--stagger: {{ $loop->index + 1 }}">
                                <span aria-hidden="true" @class(['absolute -top-16 -right-16 -z-10 size-40 rounded-full blur-2xl transition duration-300 group-hover:scale-125', $tone['glow']])></span>
                                <span @class(['grid size-12 place-items-center rounded-2xl shadow-sm', $tone['tile']])>
                                    <x-icon :name="$role->icon()" class="size-6" />
                                </span>
                                <h3 class="mt-5 font-display text-xl font-bold tracking-tight text-ink-950 dark:text-white">{{ $role->portalLabel() }}</h3>
                                <p class="mt-2 flex-1 text-sm text-ink-600 dark:text-ink-300">{{ $portalCopy[$role->value] ?? '' }}</p>
                                <span @class(['mt-6 inline-flex items-center gap-1.5 text-sm font-semibold', $tone['link']])>
                                    Sign in
                                    <x-icon name="arrow-right" class="size-4 transition group-hover:translate-x-1" />
                                </span>
                            </a>
                        @endforeach
                    </div>
                </section>

                <section id="features" class="mx-auto max-w-7xl scroll-mt-8 px-4 pb-24 sm:px-8" aria-labelledby="features-title">
                    <div class="rounded-[2rem] bg-white p-6 shadow-card ring-1 ring-ink-900/5 sm:p-10 dark:bg-ink-900 dark:ring-white/8">
                        <div class="max-w-2xl">
                            <p class="text-xs font-semibold tracking-[0.16em] text-leaf-700 uppercase dark:text-leaf-300">Inside NutriMind</p>
                            <h2 id="features-title" class="mt-2 font-display text-3xl font-bold tracking-tight text-ink-950 dark:text-white">Built like a game, run like a school system</h2>
                        </div>
                        <ul class="mt-10 grid gap-x-8 gap-y-10 sm:grid-cols-2 lg:grid-cols-5">
                            @foreach ($features as $feature)
                                <li>
                                    <span @class(['grid size-11 place-items-center rounded-2xl ring-1 ring-inset', $feature['tone']])>
                                        <x-icon :name="$feature['icon']" class="size-5" />
                                    </span>
                                    <h3 class="mt-4 font-display text-base font-semibold text-ink-950 dark:text-white">{{ $feature['title'] }}</h3>
                                    <p class="mt-1.5 text-sm text-ink-600 dark:text-ink-300">{{ $feature['text'] }}</p>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                </section>
            </main>

            <footer class="border-t border-ink-900/8 dark:border-white/8">
                <div class="mx-auto flex max-w-7xl flex-col items-center justify-between gap-4 px-4 py-8 text-sm text-ink-500 sm:flex-row sm:px-8 dark:text-ink-400">
                    <x-logo size="sm" />
                    <p>&copy; {{ now()->year }} {{ $schoolName }}. Nutrition education for Grade 5–6 learners.</p>
                </div>
            </footer>
        </div>
    </body>
</html>
