@php
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

    $eyebrows = [
        'leaf' => 'text-leaf-700 dark:text-leaf-300',
        'grape' => 'text-grape-700 dark:text-grape-300',
        'carrot' => 'text-carrot-700 dark:text-carrot-300',
        'sun' => 'text-sun-800 dark:text-sun-300',
        'berry' => 'text-berry-700 dark:text-berry-300',
        'sky' => 'text-sky-700 dark:text-sky-300',
        'teal' => 'text-teal-700 dark:text-teal-300',
        'lime' => 'text-lime-800 dark:text-lime-300',
        'pink' => 'text-pink-700 dark:text-pink-300',
        'indigo' => 'text-indigo-700 dark:text-indigo-300',
        'slate' => 'text-slate-700 dark:text-slate-300',
    ];

    $color = $portal->color();
@endphp

<x-layouts.guest :title="'Sign in · '.$portal->portalLabel()" :portal="$color">
    <a href="{{ route('login') }}" class="inline-flex items-center gap-1.5 rounded-lg py-1 text-sm font-medium text-ink-500 transition hover:text-ink-900 pointer-coarse:py-2.5 dark:text-ink-400 dark:hover:text-white">
        <x-icon name="arrow-left" class="size-4" />
        Back to portals
    </a>

    <div class="mt-6 flex items-center gap-4">
        <span @class(['grid size-12 shrink-0 place-items-center rounded-2xl shadow-sm', $tiles[$color] ?? $tiles['leaf']])>
            <x-icon :name="$portal->icon()" class="size-6" />
        </span>
        <div>
            <p @class(['text-xs font-semibold tracking-[0.16em] uppercase', $eyebrows[$color] ?? $eyebrows['leaf']])>{{ $portal->portalLabel() }}</p>
            <h1 class="font-display text-3xl font-bold tracking-tight text-ink-950 dark:text-white">Welcome back</h1>
        </div>
    </div>
    <p class="mt-3 text-sm text-ink-600 dark:text-ink-300">Sign in with your {{ strtolower($portal->label()) }} account to continue.</p>

    @if (is_string(session('status')) && filled(session('status')))
        <x-alert variant="success" class="mt-6">{{ session('status') }}</x-alert>
    @endif
    @if (is_string(session('error')) && filled(session('error')))
        <x-alert variant="danger" class="mt-6">{{ session('error') }}</x-alert>
    @endif

    <form method="POST" action="{{ route('login.portal.store', $portal->value) }}" class="mt-7 flex flex-col gap-5" data-submit-once>
        @csrf

        <x-form.input
            name="email"
            type="email"
            label="Email address"
            prefix-icon="mail"
            placeholder="name@school.edu.ph"
            autocomplete="username"
            inputmode="email"
            required
            autofocus
        />

        <x-form.password name="password" label="Password" autocomplete="current-password" required />

        <x-form.checkbox name="remember" label="Keep me signed in on this device" />

        <x-button type="submit" size="lg" icon-trailing="arrow-right" class="w-full">Sign in</x-button>
    </form>

    <p class="mt-8 text-center text-sm text-ink-500 dark:text-ink-400">
        Forgot your password? Ask your school administrator to reset it.
    </p>
</x-layouts.guest>
