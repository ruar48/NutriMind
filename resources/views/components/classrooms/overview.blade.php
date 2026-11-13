@props([
    'classroom',
    'stats',
    'canUpdate' => false,
])

@php
    $formatPercent = function (?float $value): string {
        if ($value === null) {
            return '—';
        }

        $text = number_format($value, 1);

        return (str_ends_with($text, '.0') ? substr($text, 0, -2) : $text).'%';
    };

    $averageHint = $stats['attempts'] === 0
        ? 'No submitted quiz attempts yet'
        : trans_choice(':count submitted attempt|:count submitted attempts', $stats['attempts']).' · '.$formatPercent($stats['passRate']).' passed';

    $completionHint = $stats['openQuizzes'] === 0
        ? 'No published quizzes in this class yet'
        : number_format($stats['completedPairs']).' of '.number_format($stats['expectedPairs']).' learner submissions across '.trans_choice(':count quiz|:count quizzes', $stats['openQuizzes']);

    $missionsHint = $stats['missionsAvailable'] === 0
        ? 'No active missions for this subject and grade'
        : 'of '.number_format($stats['missionsTotal']).' possible · '.trans_choice(':count active mission|:count active missions', $stats['missionsAvailable']);

    $segments = collect(\App\Enums\PerformanceBand::cases())
        ->map(fn (\App\Enums\PerformanceBand $band): array => [
            'label' => $band->label(),
            'value' => $stats['bandCounts'][$band->value] ?? 0,
            'color' => $band->color(),
        ])
        ->all();

    $modeTones = [
        'leaf' => 'bg-leaf-50 text-leaf-700 dark:bg-leaf-400/10 dark:text-leaf-300',
        'sky' => 'bg-sky-50 text-sky-700 dark:bg-sky-400/10 dark:text-sky-300',
        'grape' => 'bg-grape-50 text-grape-700 dark:bg-grape-400/10 dark:text-grape-300',
    ];
@endphp

<div {{ $attributes->class('flex flex-col gap-6') }}>
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <x-stat-card
            label="Learners"
            :value="number_format($stats['learners'])"
            icon="users"
            color="sky"
            :hint="number_format($stats['activeThisWeek']).' active in the last 7 days'"
            :count-up="$stats['learners']"
            data-overview-stat="learners"
            data-animate
            style="--stagger: 1"
        />
        <x-stat-card
            label="Average score"
            :value="$formatPercent($stats['averageScore'])"
            icon="target"
            color="leaf"
            :hint="$averageHint"
            data-overview-stat="average"
            data-animate
            style="--stagger: 2"
        />
        <x-stat-card
            label="Quiz completion"
            :value="$formatPercent($stats['completionRate'])"
            icon="clipboard-check"
            color="grape"
            :hint="$completionHint"
            data-overview-stat="completion"
            data-animate
            style="--stagger: 3"
        />
        <x-stat-card
            label="Missions completed"
            :value="number_format($stats['missionsCompleted'])"
            icon="flag"
            color="carrot"
            :hint="$missionsHint"
            data-overview-stat="missions"
            data-animate
            style="--stagger: 4"
        >
            @if ($stats['missionsTotal'] > 0)
                <x-progress :value="$stats['missionsCompleted']" :max="$stats['missionsTotal']" size="sm" color="carrot" label="Mission completion" />
            @endif
        </x-stat-card>
    </div>

    <x-section title="Performance bands" description="Each enrolled learner’s average in this class, by DepEd descriptor" icon="gauge" color="sky" data-animate>
        <x-chart.stacked-bar
            label="Learners by performance band"
            label-heading="Performance band"
            value-heading="Learners"
            :segments="$segments"
            empty-text="Bands appear once learners are enrolled."
        />
    </x-section>

    <x-section title="Learning mode" description="How the NutriMind game guides learners of this class." :icon="$classroom->learning_mode->icon()" :color="$classroom->learning_mode->color()" data-animate>
        @if ($canUpdate)
            <x-slot:actions>
                <x-button variant="ghost" size="sm" icon="pencil" :href="route('teacher.classrooms.edit', $classroom)">Change</x-button>
            </x-slot:actions>
        @endif

        <ul class="flex flex-col gap-3">
            @foreach (\App\Enums\LearningMode::cases() as $mode)
                @php
                    $isCurrent = $mode === $classroom->learning_mode;
                @endphp
                <li @class([
                    'flex items-start gap-3 rounded-xl p-3 ring-1 ring-inset',
                    'bg-leaf-50/60 ring-leaf-500/40 dark:bg-leaf-400/10 dark:ring-leaf-400/40' => $isCurrent,
                    'ring-ink-900/8 dark:ring-white/8' => ! $isCurrent,
                ]) @if ($isCurrent) aria-current="true" @endif data-learning-mode="{{ $mode->value }}">
                    <span @class(['grid size-10 shrink-0 place-items-center rounded-xl', $modeTones[$mode->color()] ?? $modeTones['leaf']])>
                        <x-icon :name="$mode->icon()" class="size-5" />
                    </span>
                    <div class="min-w-0">
                        <p class="flex flex-wrap items-center gap-2 text-sm font-semibold text-ink-900 dark:text-white">
                            {{ $mode->label() }}
                            @if ($isCurrent)
                                <x-badge color="leaf" size="sm" icon="circle-check">This class</x-badge>
                            @endif
                        </p>
                        <p @class(['mt-0.5 text-sm', 'text-ink-700 dark:text-ink-200' => $isCurrent, 'text-ink-500 dark:text-ink-400' => ! $isCurrent])>{{ $mode->description() }}</p>
                    </div>
                </li>
            @endforeach
        </ul>
    </x-section>
</div>
