@props([
    'title' => null,
    'description' => null,
])

@php
    $user = auth()->user();
    $role = $user?->role instanceof \App\Enums\UserRole ? $user->role : null;

    $navigation = is_array($navigation ?? null) ? $navigation : [];
    $portal = is_array($portal ?? null) ? $portal : ($role
        ? ['key' => $role->value, 'label' => $role->portalLabel(), 'color' => $role->color()]
        : ['key' => 'nutrimind', 'label' => 'NutriMind', 'color' => 'leaf']);
    $schoolName = filled($schoolName ?? null) ? $schoolName : config('nutrimind.settings.school_name', 'NutriMind');
    $activeSchoolYear = $activeSchoolYear ?? null;
    $activeTerm = $activeTerm ?? null;
    $announcement = filled($announcement ?? null) ? (string) $announcement : null;
    $searchUrl = $searchUrl ?? null;
    $searchPlaceholder = filled($searchPlaceholder ?? null) ? $searchPlaceholder : 'Search…';

    $homeUrl = \Illuminate\Support\Facades\Route::has('dashboard') ? route('dashboard') : url('/');
    $announcementId = $announcement !== null ? substr(hash('sha256', $announcement), 0, 16) : null;
    $documentTitle = filled($title) ? $title.' · '.($portal['label'] ?? 'NutriMind') : ($portal['label'] ?? null);
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" @if ($announcementId) data-announcement-id="{{ $announcementId }}" @endif>
    <head>
        <x-shell.head :title="$documentTitle" :description="$description" />
    </head>
    <body class="min-h-dvh">
        <a href="#content" class="fixed top-3 left-3 z-[70] -translate-y-24 rounded-xl bg-leaf-700 px-4 py-2.5 text-sm font-semibold text-white shadow-lift transition focus:translate-y-0">Skip to content</a>

        <x-shell.sidebar :navigation="$navigation" :portal="$portal" :school-name="$schoolName" :home-url="$homeUrl" />

        <div class="flex min-h-dvh flex-col lg:pl-68">
            <x-shell.topbar
                :user="$user"
                :search-url="$searchUrl"
                :search-placeholder="$searchPlaceholder"
                :active-school-year="$activeSchoolYear"
                :active-term="$activeTerm"
                :home-url="$homeUrl"
            />

            @if ($announcement !== null)
                <x-announcement :text="$announcement" :id="$announcementId" />
            @endif

            <main id="content" tabindex="-1" class="relative isolate flex-1 focus:outline-none">
                <div aria-hidden="true" class="bg-dot-grid pointer-events-none absolute inset-x-0 top-0 -z-10 h-[30rem] [mask-image:linear-gradient(to_bottom,#000_10%,transparent)]"></div>
                <div class="mx-auto flex w-full max-w-[1440px] flex-col gap-6 px-4 py-6 sm:gap-8 sm:px-6 sm:py-8 lg:px-8">
                    {{ $slot }}
                </div>
            </main>
        </div>

        <x-toasts />
    </body>
</html>
