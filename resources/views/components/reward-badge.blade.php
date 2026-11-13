@props([
    'reward' => null,
    'name' => null,
    'icon' => null,
    'color' => null,
    'description' => null,
    'earned' => true,
    'awardedAt' => null,
    'size' => 'md',
])

@php
    $tones = [
        'leaf' => ['burst' => 'fill-leaf-400', 'core' => 'fill-leaf-50 dark:fill-leaf-950', 'icon' => 'text-leaf-700 dark:text-leaf-300'],
        'carrot' => ['burst' => 'fill-carrot-400', 'core' => 'fill-carrot-50 dark:fill-carrot-950', 'icon' => 'text-carrot-700 dark:text-carrot-300'],
        'sun' => ['burst' => 'fill-sun-400', 'core' => 'fill-sun-50 dark:fill-sun-950', 'icon' => 'text-sun-700 dark:text-sun-300'],
        'berry' => ['burst' => 'fill-berry-400', 'core' => 'fill-berry-50 dark:fill-berry-950', 'icon' => 'text-berry-700 dark:text-berry-300'],
        'grape' => ['burst' => 'fill-grape-400', 'core' => 'fill-grape-50 dark:fill-grape-950', 'icon' => 'text-grape-700 dark:text-grape-300'],
        'sky' => ['burst' => 'fill-sky-400', 'core' => 'fill-sky-50 dark:fill-sky-950', 'icon' => 'text-sky-700 dark:text-sky-300'],
        'teal' => ['burst' => 'fill-teal-400', 'core' => 'fill-teal-50 dark:fill-teal-950', 'icon' => 'text-teal-700 dark:text-teal-300'],
        'lime' => ['burst' => 'fill-lime-400', 'core' => 'fill-lime-50 dark:fill-lime-950', 'icon' => 'text-lime-800 dark:text-lime-300'],
        'pink' => ['burst' => 'fill-pink-400', 'core' => 'fill-pink-50 dark:fill-pink-950', 'icon' => 'text-pink-700 dark:text-pink-300'],
        'indigo' => ['burst' => 'fill-indigo-400', 'core' => 'fill-indigo-50 dark:fill-indigo-950', 'icon' => 'text-indigo-700 dark:text-indigo-300'],
        'slate' => ['burst' => 'fill-slate-400', 'core' => 'fill-slate-50 dark:fill-slate-950', 'icon' => 'text-slate-700 dark:text-slate-300'],
    ];

    $locked = ['burst' => 'fill-ink-200 dark:fill-ink-700', 'core' => 'fill-ink-50 dark:fill-ink-900', 'icon' => 'text-ink-400 dark:text-ink-500'];

    $sizes = [
        'sm' => ['medal' => 'size-12', 'icon' => 'size-5', 'width' => 'w-16', 'text' => 'text-[11px]'],
        'md' => ['medal' => 'size-16', 'icon' => 'size-6', 'width' => 'w-24', 'text' => 'text-xs'],
        'lg' => ['medal' => 'size-20', 'icon' => 'size-8', 'width' => 'w-28', 'text' => 'text-sm'],
    ];

    $label = $name ?? $reward?->name ?? 'Reward';
    $iconName = $icon ?? $reward?->icon ?? 'award';
    $colorKey = $color ?? $reward?->color ?? 'sun';
    $colorKey = array_key_exists($colorKey, $tones) ? $colorKey : 'sun';
    $details = $description ?? $reward?->description;
    $awarded = $awardedAt ?? ($reward?->relationLoaded('pivot') ? $reward->pivot->awarded_at : null);
    if (is_string($awarded) && $awarded !== '') {
        $awarded = \Illuminate\Support\Carbon::parse($awarded);
    }
    $tone = $earned ? $tones[$colorKey] : $locked;
    $dimensions = $sizes[$size] ?? $sizes['md'];

    $points = [];
    for ($i = 0; $i < 24; $i++) {
        $angle = deg2rad($i * 15 - 90);
        $radius = $i % 2 === 0 ? 31 : 27;
        $points[] = round(32 + $radius * cos($angle), 2).','.round(32 + $radius * sin($angle), 2);
    }
    $burst = implode(' ', $points);
@endphp

<figure {{ $attributes->class(['flex flex-col items-center text-center', $dimensions['width']]) }} @if ($details) title="{{ $details }}" @endif data-earned="{{ $earned ? 'true' : 'false' }}">
    <div @class(['relative grid place-items-center', $dimensions['medal'], 'grayscale' => ! $earned])>
        <svg viewBox="0 0 64 64" class="absolute inset-0 size-full drop-shadow-[0_3px_4px_rgb(13_20_16/0.18)]" aria-hidden="true">
            <polygon points="{{ $burst }}" @class([$tone['burst']]) />
            <circle cx="32" cy="32" r="22" @class([$tone['core']]) />
            <circle cx="32" cy="32" r="22" class="fill-none stroke-white/70 dark:stroke-white/15" stroke-width="2" />
        </svg>
        <x-icon :name="$iconName" @class(['relative', $dimensions['icon'], $tone['icon']]) stroke-width="2" />
        @unless ($earned)
            <span class="absolute -right-0.5 -bottom-0.5 grid size-6 place-items-center rounded-full bg-ink-700 text-white ring-2 ring-white dark:bg-ink-600 dark:ring-ink-900">
                <x-icon name="lock" class="size-3" stroke-width="2.25" />
            </span>
        @endunless
    </div>
    <figcaption class="mt-2 w-full">
        <span @class(['line-clamp-2 font-semibold', $dimensions['text'], 'text-ink-900 dark:text-ink-100' => $earned, 'text-ink-400 dark:text-ink-500' => ! $earned])>{{ $label }}</span>
        @if (! $earned)
            <span class="mt-0.5 block text-[11px] text-ink-400 dark:text-ink-500">Locked</span>
        @elseif ($awarded instanceof \DateTimeInterface)
            <time datetime="{{ $awarded->format('Y-m-d') }}" class="mt-0.5 block text-[11px] text-ink-500 dark:text-ink-400">{{ $awarded->format('M j, Y') }}</time>
        @endif
    </figcaption>
</figure>
