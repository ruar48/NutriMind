{{--
    Standalone error layout. It must render while the database or session is unavailable, so it never queries:
    auth()->hasUser() only checks an already-resolved user, and links use Route::has() + url().
--}}
@props([
    'code',
    'title',
    'message' => null,
    'illustration' => 'empty-plate',
])

@php
    $isSignedIn = auth()->hasUser();
    $hasDashboard = \Illuminate\Support\Facades\Route::has('dashboard');
    $primaryUrl = $isSignedIn && $hasDashboard ? route('dashboard') : url('/');
    $primaryLabel = $isSignedIn && $hasDashboard ? 'Go to dashboard' : 'Back home';
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <x-shell.head :title="$title" :csrf="false" />
    </head>
    <body class="min-h-dvh">
        <div class="relative isolate flex min-h-dvh flex-col overflow-hidden">
            <div aria-hidden="true" class="pointer-events-none absolute inset-0 -z-10">
                <div class="bg-dot-grid absolute inset-x-0 top-0 h-[34rem] [mask-image:linear-gradient(to_bottom,#000,transparent)]"></div>
                <div class="absolute -top-40 -right-32 size-[30rem] rounded-full bg-leaf-300/25 blur-3xl dark:bg-leaf-500/10"></div>
                <div class="absolute -bottom-48 -left-32 size-[28rem] rounded-full bg-sun-300/25 blur-3xl dark:bg-sun-400/5"></div>
            </div>

            <header class="px-6 py-6 sm:px-10">
                <a href="{{ url('/') }}" class="inline-flex rounded-lg">
                    <x-logo size="sm" />
                </a>
            </header>

            <main id="content" class="flex flex-1 items-center justify-center px-4 pb-16">
                <div class="w-full max-w-xl text-center" data-animate>
                    <x-illustration :name="$illustration" class="mx-auto h-40 w-auto text-ink-700 sm:h-44 dark:text-ink-200" />
                    <p class="mt-6 font-display text-sm font-bold tracking-[0.22em] text-leaf-700 uppercase dark:text-leaf-300">Error {{ $code }}</p>
                    <h1 class="mt-2 font-display text-3xl font-bold tracking-tight text-ink-950 sm:text-4xl dark:text-white">{{ $title }}</h1>
                    @if (filled($message))
                        <p class="mx-auto mt-3 max-w-md text-base text-ink-600 dark:text-ink-300">{{ $message }}</p>
                    @endif
                    @if ($slot->hasActualContent())
                        <div class="mx-auto mt-4 max-w-md text-sm text-ink-600 dark:text-ink-300">{{ $slot }}</div>
                    @endif
                    <div class="mt-8 flex flex-wrap items-center justify-center gap-3">
                        <x-button :href="$primaryUrl" :icon="$isSignedIn && $hasDashboard ? 'layout-dashboard' : 'home'" size="lg">{{ $primaryLabel }}</x-button>
                        {{ $actions ?? '' }}
                    </div>
                </div>
            </main>
        </div>
    </body>
</html>
