{{--
    Proportional calendar bar of a school year: one block per quarter placed by its dates, plus a "Today" marker
    while the year is in session. Decorative for screen readers, which get the same facts as a sentence.
    Props: schoolYear (SchoolYear with `terms` loaded).
--}}
@props([
    'schoolYear',
])

@php
    $yearStart = \Carbon\CarbonImmutable::parse($schoolYear->starts_on->format('Y-m-d'));
    $yearEnd = \Carbon\CarbonImmutable::parse($schoolYear->ends_on->format('Y-m-d'));
    $totalDays = max(1, (int) $yearStart->diffInDays($yearEnd) + 1);
    $today = \Carbon\CarbonImmutable::parse(today()->format('Y-m-d'));
    $percent = fn (float $value): string => number_format(min(100, max(0, $value)), 3, '.', '');

    $states = [
        'current' => ['block' => 'bg-leaf-600 text-white shadow-sm dark:bg-leaf-500', 'label' => 'current'],
        'today' => ['block' => 'bg-sun-200 text-sun-950 ring-1 ring-inset ring-sun-600/30 dark:bg-sun-400/25 dark:text-sun-100 dark:ring-sun-400/30', 'label' => 'covers today, not current'],
        'upcoming' => ['block' => 'bg-grape-100 text-grape-800 ring-1 ring-inset ring-grape-600/20 dark:bg-grape-400/15 dark:text-grape-200 dark:ring-grape-400/25', 'label' => 'upcoming'],
        'ended' => ['block' => 'bg-ink-200 text-ink-700 dark:bg-white/10 dark:text-ink-200', 'label' => 'ended'],
    ];

    $blocks = $schoolYear->terms->map(function (\App\Models\AcademicTerm $term) use ($yearStart, $totalDays, $today, $percent): array {
        $start = \Carbon\CarbonImmutable::parse($term->starts_on->format('Y-m-d'));
        $end = \Carbon\CarbonImmutable::parse($term->ends_on->format('Y-m-d'));
        $state = match (true) {
            $term->is_active => 'current',
            $start->greaterThan($today) => 'upcoming',
            $end->lessThan($today) => 'ended',
            default => 'today',
        };

        return [
            'term' => $term,
            'state' => $state,
            'left' => $percent($yearStart->diffInDays($start, false) / $totalDays * 100),
            'width' => $percent(((int) $start->diffInDays($end) + 1) / $totalDays * 100),
        ];
    });

    $inSession = $today->betweenIncluded($yearStart, $yearEnd);
    $todayLeft = $inSession ? $percent(((int) $yearStart->diffInDays($today) + 0.5) / $totalDays * 100) : null;
    $summary = $blocks->map(fn (array $block) => $block['term']->name.', '.$block['term']->starts_on->format('M j, Y').' to '.$block['term']->ends_on->format('M j, Y').', '.$states[$block['state']]['label'])->join('; ');
@endphp

<div {{ $attributes->class('min-w-0') }} data-term-timeline>
    <p class="sr-only">{{ $blocks->isEmpty() ? 'No quarters yet.' : 'Quarters: '.$summary.'.' }}</p>

    <div aria-hidden="true" @class(['pt-5' => $todayLeft !== null])>
        <div class="relative h-12 rounded-2xl bg-ink-50 ring-1 ring-ink-900/5 ring-inset dark:bg-white/3 dark:ring-white/8">
            @forelse ($blocks as $block)
                <div
                    @class(['absolute inset-y-1.5 flex min-w-0 items-center overflow-hidden rounded-xl px-2 text-xs font-semibold', $states[$block['state']]['block']])
                    style="left: calc({{ $block['left'] }}% + 2px); width: calc({{ $block['width'] }}% - 4px);"
                    title="{{ $block['term']->name }}: {{ $block['term']->starts_on->format('M j, Y') }} – {{ $block['term']->ends_on->format('M j, Y') }}"
                    data-term-block="{{ $block['state'] }}"
                >
                    <span class="truncate">Q{{ $block['term']->sequence }}</span>
                </div>
            @empty
                <div class="absolute inset-0 grid place-items-center text-xs font-medium text-ink-500 dark:text-ink-400">No quarters yet</div>
            @endforelse

            @if ($todayLeft !== null)
                <div class="absolute -inset-y-1.5 w-0.5 -translate-x-1/2 rounded-full bg-ink-900 dark:bg-white" style="left: {{ $todayLeft }}%;" data-term-today>
                    <span class="absolute -top-5 left-1/2 -translate-x-1/2 rounded-full bg-ink-900 px-1.5 py-px text-[0.625rem] leading-4 font-semibold whitespace-nowrap text-white dark:bg-white dark:text-ink-950">Today</span>
                </div>
            @endif
        </div>

        <div class="mt-2 flex items-center justify-between gap-3 text-xs text-ink-500 tabular-nums dark:text-ink-400">
            <span>{{ $schoolYear->starts_on->format('M j, Y') }}</span>
            <span>{{ $schoolYear->ends_on->format('M j, Y') }}</span>
        </div>

        @if ($blocks->isNotEmpty())
            <ul class="mt-3 flex flex-wrap gap-x-4 gap-y-1.5 text-xs text-ink-600 dark:text-ink-300">
                @foreach (['current' => 'Current quarter', 'today' => 'Covers today, not current', 'upcoming' => 'Upcoming', 'ended' => 'Ended'] as $state => $legend)
                    @if ($blocks->contains('state', $state))
                        <li class="flex items-center gap-1.5">
                            <span @class(['size-3 rounded', $states[$state]['block']])></span>
                            {{ $legend }}
                        </li>
                    @endif
                @endforeach
            </ul>
        @endif
    </div>
</div>
