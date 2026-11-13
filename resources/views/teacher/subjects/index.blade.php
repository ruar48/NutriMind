@php
    $user = auth()->user();
    $canCreateClassroom = $user->can('create', \App\Models\Classroom::class);
    $canCreateQuiz = $user->can('create', \App\Models\Quiz::class);
    $canUseQuestionBank = $user->can('viewAny', \App\Models\QuestionBankItem::class);
    $bands = [
        'leaf' => 'bg-leaf-500',
        'carrot' => 'bg-carrot-500',
        'sun' => 'bg-sun-400',
        'berry' => 'bg-berry-500',
        'grape' => 'bg-grape-500',
        'sky' => 'bg-sky-500',
        'teal' => 'bg-teal-500',
        'lime' => 'bg-lime-500',
        'pink' => 'bg-pink-500',
        'indigo' => 'bg-indigo-500',
        'slate' => 'bg-slate-400',
    ];
@endphp

<x-layouts.app title="My subjects">
    <x-page-header
        eyebrow="Teaching"
        title="My subjects"
        description="The subjects your school administrator assigned to you. Classrooms, quizzes and lesson plans can only use these subjects."
        :breadcrumbs="[['label' => 'Dashboard', 'url' => route('teacher.dashboard')], ['label' => 'My subjects']]"
    >
        <x-slot:actions>
            <x-button variant="secondary" icon="school" :href="route('teacher.classrooms.index')">All classrooms</x-button>
        </x-slot:actions>
    </x-page-header>

    @if ($subjects->isEmpty())
        <x-empty-state
            variant="card"
            illustration="lock"
            :title="$inactiveSubjectsCount > 0 ? 'Your subjects are inactive' : 'No subjects assigned yet'"
            :description="$inactiveSubjectsCount > 0
                ? 'The school administrator has paused the subjects assigned to you. Ask them to reactivate a subject or assign another one to create classrooms and quizzes.'
                : 'Your school administrator assigns the subjects you teach. Once a subject is assigned, you can create classrooms, quizzes and lesson plans for it.'"
        >
            <x-slot:actions>
                <x-button variant="secondary" icon="layout-dashboard" :href="route('teacher.dashboard')">Back to dashboard</x-button>
            </x-slot:actions>
        </x-empty-state>
    @else
        @if ($inactiveSubjectsCount > 0)
            <x-alert variant="info" data-subjects-inactive="{{ $inactiveSubjectsCount }}">
                {{ trans_choice(':count more subject is assigned to you but inactive, so it is hidden here.|:count more subjects are assigned to you but inactive, so they are hidden here.', $inactiveSubjectsCount) }}
            </x-alert>
        @endif

        <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
            @foreach ($subjects as $subject)
                @php
                    $gradeText = $subject->grade_levels === []
                        ? 'Grades 5–6'
                        : collect($subject->grade_levels)->map(fn (\App\Enums\GradeLevel $grade): string => $grade->label())->implode(' & ');
                @endphp
                <x-card padding="none" data-subject-card="{{ $subject->id }}" data-animate style="--stagger: {{ min($loop->iteration, 6) }}">
                    <span aria-hidden="true" class="absolute inset-x-0 top-0 h-1.5 rounded-t-2xl {{ $bands[$subject->color] ?? $bands['leaf'] }}"></span>

                    <div class="flex flex-col gap-4 px-5 pt-6 pb-5">
                        <div class="flex items-start gap-3">
                            <x-classrooms.subject-tile :subject="$subject" />
                            <div class="min-w-0 flex-1">
                                <h2 class="font-display text-lg leading-snug font-semibold tracking-tight text-ink-950 dark:text-white">{{ $subject->name }}</h2>
                                <p class="mt-0.5 font-mono text-xs font-semibold tracking-wider text-ink-500 dark:text-ink-400">{{ $subject->code }}</p>
                            </div>
                        </div>

                        @if (filled($subject->description))
                            <p class="line-clamp-2 text-sm text-ink-600 dark:text-ink-300">{{ $subject->description }}</p>
                        @endif

                        <dl class="grid grid-cols-2 gap-2 rounded-xl bg-ink-50 p-3 ring-1 ring-ink-900/5 ring-inset dark:bg-white/4 dark:ring-white/8">
                            <div class="min-w-0">
                                <dt class="truncate text-xs text-ink-600 dark:text-ink-300">My classrooms</dt>
                                <dd class="mt-0.5 font-display text-lg font-bold text-ink-950 tabular-nums dark:text-white" data-subject-stat="classrooms">{{ number_format($subject->classrooms_count) }}</dd>
                            </div>
                            <div class="min-w-0">
                                <dt class="truncate text-xs text-ink-600 dark:text-ink-300">Learners</dt>
                                <dd class="mt-0.5 font-display text-lg font-bold text-ink-950 tabular-nums dark:text-white" data-subject-stat="learners">{{ number_format($subject->learners_count) }}</dd>
                            </div>
                            <div class="min-w-0">
                                <dt class="truncate text-xs text-ink-600 dark:text-ink-300">My quizzes</dt>
                                <dd class="mt-0.5 flex items-baseline gap-1.5">
                                    <span class="font-display text-lg font-bold text-ink-950 tabular-nums dark:text-white" data-subject-stat="quizzes">{{ number_format($subject->quizzes_count) }}</span>
                                    <span class="truncate text-xs text-ink-500 dark:text-ink-400">{{ number_format($subject->published_quizzes_count) }} published</span>
                                </dd>
                            </div>
                            <div class="min-w-0">
                                <dt class="truncate text-xs text-ink-600 dark:text-ink-300" title="Published game modules for {{ $gradeText }}">Game modules</dt>
                                <dd class="mt-0.5 flex items-baseline gap-1.5">
                                    <span class="font-display text-lg font-bold text-ink-950 tabular-nums dark:text-white" data-subject-stat="modules">{{ number_format($subject->modules_count) }}</span>
                                    <span class="truncate text-xs text-ink-500 dark:text-ink-400">{{ $gradeText }}</span>
                                </dd>
                            </div>
                        </dl>
                    </div>

                    <x-slot:footer>
                        <div class="flex flex-wrap items-center gap-2">
                            <x-button variant="ghost" size="sm" icon="school" :href="route('teacher.classrooms.index', ['subject' => $subject->id])">Classrooms</x-button>
                            @if ($canCreateClassroom)
                                <x-button variant="ghost" size="sm" icon="plus" :href="route('teacher.classrooms.create', ['subject_id' => $subject->id])">New classroom</x-button>
                            @endif
                            @if ($canCreateQuiz)
                                <x-button variant="ghost" size="sm" icon="clipboard-check" :href="route('teacher.quizzes.create', ['subject_id' => $subject->id])">New quiz</x-button>
                            @endif
                            @if ($canUseQuestionBank)
                                <x-button variant="ghost" size="sm" icon="library" :href="route('teacher.question-bank.index', ['subject' => $subject->id])">Question bank</x-button>
                            @endif
                        </div>
                    </x-slot:footer>
                </x-card>
            @endforeach
        </div>
    @endif
</x-layouts.app>
