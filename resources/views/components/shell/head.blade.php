@props([
    'title' => null,
    'description' => null,
    'themeScript' => true,
    'csrf' => true,
])

@php
    $favicon = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 40 40"><rect width="40" height="40" rx="11" fill="#1a8250"/><path d="M9.6 30.4C9.2 19.5 16.4 10.6 31.2 9.2c1 14.6-7.4 22.2-21.6 21.2Z" fill="#effaf3"/><polyline points="11.2,28.8 16.9,23.4 15.7,19.6 21.9,17.6 20.9,14.3 27.6,12.2" fill="none" stroke="#1a8250" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round"/><path d="M31.6 24.2l.95 2.25 2.25.95-2.25.95-.95 2.25-.95-2.25-2.25-.95 2.25-.95Z" fill="#ffd446"/></svg>';
@endphp

<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="color-scheme" content="light dark">
<meta name="theme-color" content="#f7f6f1">
@if ($csrf)
    <meta name="csrf-token" content="{{ csrf_token() }}">
@endif
@if ($description)
    <meta name="description" content="{{ $description }}">
@endif
<title>{{ filled($title) ? $title.' · NutriMind' : 'NutriMind' }}</title>
<link rel="icon" type="image/svg+xml" href="data:image/svg+xml,{{ rawurlencode($favicon) }}">
@if ($themeScript)
    <script>
        (function () {
            var root = document.documentElement;
            var preference = 'system';
            root.classList.add('js');
            try {
                var stored = window.localStorage.getItem('nutrimind.theme');
                if (stored === 'light' || stored === 'dark') {
                    preference = stored;
                }
                var dismissed = window.localStorage.getItem('nutrimind.announcement.dismissed');
                if (dismissed && dismissed === root.getAttribute('data-announcement-id')) {
                    root.setAttribute('data-announcement-dismissed', '');
                }
            } catch (error) {
                preference = 'system';
            }
            var isDark = preference === 'dark' || (preference === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches);
            root.classList.toggle('dark', isDark);
            root.setAttribute('data-theme-preference', preference);
        })();
    </script>
@else
    <script>
        document.documentElement.classList.add('js');
    </script>
@endif
@fonts
@vite(['resources/css/app.css', 'resources/js/app.js'])
{{ $slot }}
