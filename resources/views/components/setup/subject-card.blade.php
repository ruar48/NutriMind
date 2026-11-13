{{--
    One subject in the admin subject grid: color band + icon tile, name, code, description, usage counts, status
    and an actions menu. Delete is offered only while nothing uses the subject.
    Props: subject (Subject loaded with teachers/classrooms/learningModules/missions/quizzes/questionBankItems/lessonPlans counts).
--}}
@props([
    'subject',
])

@php
    $bands = [
        'leaf' => 'from-leaf-500 to-leaf-400',
        'carrot' => 'from-carrot-500 to-carrot-400',
        'sun' => 'from-sun-400 to-sun-300',
        'berry' => 'from-berry-500 to-berry-400',
        'grape' => 'from-grape-500 to-grape-400',
        'sky' => 'from-sky-500 to-sky-400',
        'teal' => 'from-teal-500 to-teal-400',
        'lime' => 'from-lime-500 to-lime-400',
        'pink' => 'from-pink-500 to-pink-400',
        'indigo' => 'from-indigo-500 to-indigo-400',
        'slate' => 'from-slate-500 to-slate-400',
    ];
    $tiles = [
        'leaf' => 'bg-leaf-600 text-white',
        'carrot' => 'bg-carrot-500 text-white',
        'sun' => 'bg-sun-400 text-sun-950',
        'berry' => 'bg-berry-500 text-white',
        'grape' => 'bg-grape-500 text-white',
        'sky' => 'bg-sky-600 text-white',
        'teal' => 'bg-teal-600 text-white',
        'lime' => 'bg-lime-500 text-lime-950',
        'pink' => 'bg-pink-500 text-white',
        'indigo' => 'bg-indigo-500 text-white',
        'slate' => 'bg-slate-500 text-white',
    ];

    $color = array_key_exists($subject->color, $bands) ? $subject->color : 'leaf';
    $usageCount = $subject->classrooms_count + $subject->learning_modules_count + $subject->missions_count
        + $subject->quizzes_count + $subject->question_bank_items_count + $subject->lesson_plans_count;
    $stats = [
        ['label' => 'Teachers', 'value' => $subject->teachers_count, 'icon' => 'graduation-cap'],
        ['label' => 'Classrooms', 'value' => $subject->classrooms_count, 'icon' => 'school'],
        ['label' => 'Modules', 'value' => $subject->learning_modules_count, 'icon' => 'map'],
    ];
@endphp

<x-card padding="none" {{ $attributes }} data-subject-card="{{ $subject->id }}">
    <div @class(['h-2 rounded-t-2xl bg-linear-to-r', $bands[$color]]) aria-hidden="true"></div>

    <div class="flex flex-col gap-4 p-5">
        <div class="flex items-start gap-3">
            <span @class(['grid size-12 shrink-0 place-items-center rounded-2xl shadow-sm', $tiles[$color], 'opacity-60' => ! $subject->is_active])>
                <x-icon :name="$subject->icon" class="size-6" />
            </span>
            <div class="min-w-0 flex-1">
                <h3 class="truncate font-display text-base font-semibold tracking-tight text-ink-950 dark:text-white">
                    <a href="{{ route('admin.subjects.edit', $subject) }}" class="rounded hover:text-leaf-700 hover:underline focus-visible:outline-2 focus-visible:outline-offset-2 dark:hover:text-leaf-300">{{ $subject->name }}</a>
                </h3>
                <p class="mt-0.5 font-mono text-xs tracking-wide text-ink-500 dark:text-ink-400">{{ $subject->code }}</p>
            </div>
            <x-dropdown variant="icon" icon="ellipsis-vertical" :label="'Actions for '.$subject->name">
                <x-dropdown.item :href="route('admin.subjects.edit', $subject)" icon="pencil">Edit subject</x-dropdown.item>
                <x-dropdown.item :href="route('admin.subject-assignments.index', ['subject' => $subject->id])" icon="user-check">See assigned teachers</x-dropdown.item>
                <x-dropdown.divider />
                @if ($usageCount === 0)
                    <x-confirm-dialog
                        :action="route('admin.subjects.destroy', $subject)"
                        :title="'Delete '.$subject->name.'?'"
                        message="The subject and its teacher assignments are removed. This cannot be undone."
                        confirm="Delete subject"
                        button="Delete"
                        button-variant="menu"
                    />
                @else
                    <x-dropdown.item icon="trash-2" disabled>In use · deactivate instead</x-dropdown.item>
                @endif
            </x-dropdown>
        </div>

        <p class="line-clamp-3 min-h-15 text-sm text-ink-600 dark:text-ink-300">{{ $subject->description ?: 'No description yet.' }}</p>

        <dl class="grid grid-cols-3 gap-2">
            @foreach ($stats as $stat)
                <div class="rounded-xl bg-ink-50 px-3 py-2 ring-1 ring-ink-900/5 ring-inset dark:bg-white/3 dark:ring-white/8">
                    <dt class="flex items-center gap-1 text-[0.6875rem] font-medium text-ink-500 dark:text-ink-400">
                        <x-icon :name="$stat['icon']" class="size-3.5" />
                        <span class="truncate">{{ $stat['label'] }}</span>
                    </dt>
                    <dd class="font-display text-lg font-bold text-ink-950 tabular-nums dark:text-white" data-subject-stat="{{ \Illuminate\Support\Str::slug($stat['label']) }}">{{ number_format($stat['value']) }}</dd>
                </div>
            @endforeach
        </dl>
    </div>

    <x-slot:footer>
        <div class="flex flex-wrap items-center justify-between gap-2">
            <x-badge :color="$subject->is_active ? 'leaf' : 'slate'" :icon="$subject->is_active ? 'circle-check' : 'eye-off'">{{ $subject->is_active ? 'Active' : 'Inactive' }}</x-badge>
            <span class="text-xs text-ink-500 dark:text-ink-400">{{ trans_choice(':count quiz|:count quizzes', $subject->quizzes_count) }} · {{ trans_choice(':count mission|:count missions', $subject->missions_count) }}</span>
        </div>
    </x-slot:footer>
</x-card>
