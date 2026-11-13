@props([
    'result' => null,
    'credentials' => [],
    'downloadUrl' => null,
])

@php
    $summary = is_array($result) ? $result : null;
    $tiles = $summary === null ? [] : [
        ['label' => 'New learner accounts', 'value' => (int) ($summary['created'] ?? 0), 'icon' => 'user-plus', 'color' => 'leaf'],
        ['label' => 'Existing learners enrolled', 'value' => (int) ($summary['enrolled'] ?? 0), 'icon' => 'user-check', 'color' => 'sky'],
        ['label' => 'New parent accounts', 'value' => (int) ($summary['parentsCreated'] ?? 0), 'icon' => 'house-heart', 'color' => 'carrot'],
        ['label' => 'Rows not imported', 'value' => (int) ($summary['skipped'] ?? 0) + (int) ($summary['failed'] ?? 0), 'icon' => 'circle-dashed', 'color' => 'slate'],
    ];
    $tones = [
        'leaf' => 'bg-leaf-50 text-leaf-700 ring-leaf-600/15 dark:bg-leaf-400/10 dark:text-leaf-300 dark:ring-leaf-400/20',
        'sky' => 'bg-sky-50 text-sky-700 ring-sky-600/15 dark:bg-sky-400/10 dark:text-sky-300 dark:ring-sky-400/20',
        'carrot' => 'bg-carrot-50 text-carrot-700 ring-carrot-600/15 dark:bg-carrot-400/10 dark:text-carrot-300 dark:ring-carrot-400/20',
        'slate' => 'bg-slate-100 text-slate-700 ring-slate-500/15 dark:bg-slate-400/10 dark:text-slate-300 dark:ring-slate-400/20',
    ];
    $accounts = (int) ($summary['accounts'] ?? 0);
    $linked = (int) ($summary['guardiansLinked'] ?? 0);
@endphp

@if ($summary !== null)
    <x-section
        title="Import complete"
        :description="filled($summary['classroom'] ?? null) ? 'Learners were added to '.$summary['classroom'].'.' : 'The learner accounts are ready.'"
        icon="party-popper"
        color="leaf"
        data-import-result
        data-animate
        {{ $attributes }}
    >
        @isset($actions)
            <x-slot:actions>{{ $actions }}</x-slot:actions>
        @endisset

        <div class="flex flex-col gap-5">
            <dl class="grid grid-cols-2 gap-3 lg:grid-cols-4">
                @foreach ($tiles as $tile)
                    <div class="flex items-center gap-3 rounded-2xl bg-ink-50/70 p-3 ring-1 ring-ink-900/5 dark:bg-white/3 dark:ring-white/8">
                        <span @class(['grid size-10 shrink-0 place-items-center rounded-xl ring-1 ring-inset', $tones[$tile['color']]])>
                            <x-icon :name="$tile['icon']" class="size-5" />
                        </span>
                        <div class="min-w-0">
                            <dt class="text-xs text-ink-600 dark:text-ink-300">{{ $tile['label'] }}</dt>
                            <dd class="font-display text-xl font-bold text-ink-950 tabular-nums dark:text-white">{{ number_format($tile['value']) }}</dd>
                        </div>
                    </div>
                @endforeach
            </dl>

            @if ($linked > 0)
                <p class="flex items-center gap-2 text-sm text-ink-600 dark:text-ink-300">
                    <x-icon name="link" class="size-4 text-ink-400 dark:text-ink-500" />
                    {{ trans_choice(':count learner was linked to a parent account.|:count learners were linked to parent accounts.', $linked) }}
                </p>
            @endif

            @if ($accounts > 0)
                <x-credentials-alert :credentials="$credentials" :download-url="$downloadUrl" title="Save the new sign-in details" />
            @else
                <p class="text-sm text-ink-600 dark:text-ink-300">No new accounts were created, so there are no sign-in details to save.</p>
            @endif
        </div>
    </x-section>
@endif
