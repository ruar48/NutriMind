@php
    $descriptions = [
        'admin' => 'School setup, accounts, game content and school-wide reports.',
        'teacher' => 'Classrooms, quizzes, learner progress and feedback.',
        'parent' => 'Your child’s missions, scores, rewards and the parent forum.',
    ];

    $tiles = [
        'leaf' => 'bg-leaf-600 text-white',
        'grape' => 'bg-grape-600 text-white',
        'carrot' => 'bg-carrot-600 text-white',
        'sun' => 'bg-sun-400 text-sun-950',
        'berry' => 'bg-berry-600 text-white',
        'sky' => 'bg-sky-600 text-white',
        'teal' => 'bg-teal-600 text-white',
        'lime' => 'bg-lime-500 text-lime-950',
        'pink' => 'bg-pink-600 text-white',
        'indigo' => 'bg-indigo-600 text-white',
        'slate' => 'bg-slate-600 text-white',
    ];

    $hovers = [
        'leaf' => 'hover:ring-leaf-500/45 dark:hover:ring-leaf-400/45',
        'grape' => 'hover:ring-grape-500/45 dark:hover:ring-grape-400/45',
        'carrot' => 'hover:ring-carrot-500/45 dark:hover:ring-carrot-400/45',
        'sun' => 'hover:ring-sun-500/45 dark:hover:ring-sun-400/45',
        'berry' => 'hover:ring-berry-500/45 dark:hover:ring-berry-400/45',
        'sky' => 'hover:ring-sky-500/45 dark:hover:ring-sky-400/45',
        'teal' => 'hover:ring-teal-500/45 dark:hover:ring-teal-400/45',
        'lime' => 'hover:ring-lime-500/45 dark:hover:ring-lime-400/45',
        'pink' => 'hover:ring-pink-500/45 dark:hover:ring-pink-400/45',
        'indigo' => 'hover:ring-indigo-500/45 dark:hover:ring-indigo-400/45',
        'slate' => 'hover:ring-slate-500/45 dark:hover:ring-slate-400/45',
    ];
@endphp

<x-layouts.guest title="Choose your portal" portal="leaf" :school-name="$schoolName ?? null">
    <div>
        <p class="text-xs font-semibold tracking-[0.16em] text-leaf-700 uppercase dark:text-leaf-300">Welcome to NutriMind</p>
        <h1 class="mt-2 font-display text-3xl font-bold tracking-tight text-ink-950 dark:text-white">Choose your portal</h1>
        <p class="mt-2 text-sm text-ink-600 dark:text-ink-300">Sign in with the account your school gave you.</p>
    </div>

    @if (is_string(session('status')) && filled(session('status')))
        <x-alert variant="success" class="mt-6">{{ session('status') }}</x-alert>
    @endif
    @if (is_string(session('error')) && filled(session('error')))
        <x-alert variant="danger" class="mt-6">{{ session('error') }}</x-alert>
    @endif

    <div class="mt-8 grid gap-3">
        @foreach (\App\Enums\UserRole::webPortals() as $role)
            <a
                href="{{ route('login.portal', $role->value) }}"
                @class([
                    'group flex items-center gap-4 rounded-2xl bg-white p-4 shadow-card ring-1 ring-ink-900/8 transition duration-200 hover:-translate-y-px hover:shadow-lift focus-visible:outline-2 focus-visible:outline-offset-2 sm:p-5 dark:bg-ink-900 dark:ring-white/10',
                    $hovers[$role->color()] ?? $hovers['leaf'],
                ])
                data-portal="{{ $role->value }}"
            >
                <span @class(['grid size-12 shrink-0 place-items-center rounded-2xl shadow-sm transition group-hover:scale-105', $tiles[$role->color()] ?? $tiles['leaf']])>
                    <x-icon :name="$role->icon()" class="size-6" />
                </span>
                <span class="min-w-0 flex-1">
                    <span class="block font-display text-base font-semibold text-ink-950 dark:text-white">{{ $role->portalLabel() }}</span>
                    <span class="mt-0.5 block text-sm text-ink-500 dark:text-ink-400">{{ $descriptions[$role->value] ?? '' }}</span>
                </span>
                <x-icon name="arrow-right" class="size-5 shrink-0 text-ink-300 transition group-hover:translate-x-0.5 group-hover:text-ink-700 dark:text-ink-600 dark:group-hover:text-ink-200" />
            </a>
        @endforeach
    </div>

    <p class="mt-8 flex items-start gap-2 rounded-2xl bg-sky-50 p-4 text-sm text-sky-900 ring-1 ring-sky-600/15 ring-inset dark:bg-sky-400/10 dark:text-sky-100 dark:ring-sky-400/20">
        <x-icon name="gamepad-2" class="mt-0.5 size-4 shrink-0 text-sky-600 dark:text-sky-300" />
        Learners play NutriMind in the game app with the username their teacher gives them — no web sign-in needed.
    </p>
</x-layouts.guest>
