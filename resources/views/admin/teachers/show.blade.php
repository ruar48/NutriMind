@php
    $activeClassrooms = $classrooms->filter(fn (\App\Models\Classroom $classroom): bool => ! $classroom->isArchived());
    $totalQuizzes = array_sum($quizCounts);
    $ownsRecords = $classrooms->isNotEmpty() || $totalQuizzes > 0;
@endphp

<x-layouts.app :title="$teacher->name">
    <x-page-header
        :title="$teacher->name"
        :description="$teacher->email"
        :breadcrumbs="[['label' => 'Teachers', 'url' => route('admin.teachers.index')], ['label' => $teacher->name]]"
    >
        <x-slot:leading>
            <x-avatar :name="$teacher->name" size="xl" class="shadow-card ring-4 ring-white dark:ring-ink-900" />
        </x-slot:leading>
        <x-slot:meta>
            <x-status-badge :status="$teacher->role" />
            <x-people.account-status :user="$teacher" />
            <span class="inline-flex items-center gap-1.5 text-xs text-ink-600 dark:text-ink-300">
                <x-icon name="clock" class="size-3.5" />
                Last sign-in: <x-people.relative-time :at="$teacher->last_login_at" />
            </span>
        </x-slot:meta>
        <x-slot:actions>
            <x-button variant="secondary" icon="user-check" :href="route('admin.subject-assignments.index')">Manage assignments</x-button>
            <x-button icon="pencil" :href="route('admin.users.edit', $teacher)">Edit teacher</x-button>
        </x-slot:actions>
    </x-page-header>

    <x-credentials-alert :credentials="session('credentials', [])" />

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-stat-card label="Active classrooms" :value="number_format($activeClassrooms->count())" icon="school" color="leaf"
            :hint="trans_choice(':count archived|:count archived', $classrooms->count() - $activeClassrooms->count())" data-animate style="--stagger: 1" />
        <x-stat-card label="Learners" :value="number_format($learnersCount)" icon="users" color="sky" hint="In active classrooms" data-animate style="--stagger: 2" />
        <x-stat-card label="Published quizzes" :value="number_format($quizCounts[\App\Enums\QuizStatus::Published->value])" icon="clipboard-check" color="grape"
            :hint="trans_choice(':count quiz in all|:count quizzes in all', $totalQuizzes)" data-animate style="--stagger: 3" />
        <x-stat-card label="Lesson plans" :value="number_format($teacher->lesson_plans_count)" icon="notebook-pen" color="carrot"
            :hint="trans_choice(':count feedback note written|:count feedback notes written', $teacher->feedback_given_count)" data-animate style="--stagger: 4" />
    </div>

    <div class="grid grid-cols-1 gap-6 xl:grid-cols-3">
        <div class="flex min-w-0 flex-col gap-6 xl:col-span-2">
            <x-section title="Classrooms" :description="trans_choice(':count classroom|:count classrooms', $classrooms->count()).' · created by the teacher in the Teacher Portal'" icon="school" padding="none">
                @if ($classrooms->isEmpty())
                    <x-empty-state illustration="classroom" title="No classrooms yet"
                        :description="$teacher->assignedSubjects->isEmpty()
                            ? 'Assign a subject first; the teacher then creates classrooms in the Teacher Portal.'
                            : 'The teacher creates classrooms for their subjects in the Teacher Portal.'" />
                @else
                    <x-table flush :caption="'Classrooms of '.$teacher->name">
                        <x-slot:head>
                            <x-table.th>Classroom</x-table.th>
                            <x-table.th>Subject</x-table.th>
                            <x-table.th>School year</x-table.th>
                            <x-table.th align="right">Learners</x-table.th>
                            <x-table.th>Status</x-table.th>
                        </x-slot:head>
                        @foreach ($classrooms as $classroom)
                            <x-table.row data-classroom-row="{{ $classroom->id }}">
                                <x-table.td primary>
                                    <p class="max-w-56 truncate">{{ $classroom->name }}</p>
                                    <p class="text-xs font-normal text-ink-500 dark:text-ink-400">{{ $classroom->grade_level->label() }} · Section {{ $classroom->section }}</p>
                                </x-table.td>
                                <x-table.td><x-subject-chip :subject="$classroom->subject" size="sm" /></x-table.td>
                                <x-table.td class="whitespace-nowrap">{{ $classroom->schoolYear->name }}</x-table.td>
                                <x-table.td align="right" numeric>{{ number_format($classroom->students_count) }}</x-table.td>
                                <x-table.td>
                                    @if ($classroom->isArchived())
                                        <x-badge color="slate" icon="archive" size="sm">Archived</x-badge>
                                    @else
                                        <x-badge color="leaf" dot size="sm">Active</x-badge>
                                    @endif
                                </x-table.td>
                            </x-table.row>
                        @endforeach
                    </x-table>
                @endif
            </x-section>

            <x-section title="Quizzes" description="Every quiz the teacher created, by status." icon="clipboard-check" color="grape">
                @if ($totalQuizzes === 0)
                    <x-empty-state compact icon="clipboard-check" title="No quizzes yet" description="Quizzes the teacher builds in the Teacher Portal appear here." />
                @else
                    <x-chart.stacked-bar
                        label="Quizzes by status"
                        :segments="collect(\App\Enums\QuizStatus::cases())->map(fn (\App\Enums\QuizStatus $status): array => [
                            'label' => $status->label(),
                            'value' => $quizCounts[$status->value],
                            'color' => $status->color(),
                        ])->all()"
                        label-heading="Status"
                        value-heading="Quizzes"
                    />
                @endif
                <dl class="mt-5 grid grid-cols-1 gap-3 sm:grid-cols-3">
                    @foreach (\App\Enums\QuizStatus::cases() as $status)
                        <div class="flex items-center justify-between gap-3 rounded-xl bg-ink-50 px-4 py-3 ring-1 ring-ink-900/5 ring-inset dark:bg-white/3 dark:ring-white/8" data-quiz-status="{{ $status->value }}">
                            <dt><x-status-badge :status="$status" size="sm" /></dt>
                            <dd class="font-display text-xl font-bold text-ink-950 tabular-nums dark:text-white">{{ number_format($quizCounts[$status->value]) }}</dd>
                        </div>
                    @endforeach
                </dl>
                <p class="mt-4 flex items-center gap-2 text-sm text-ink-600 dark:text-ink-300">
                    <x-icon name="library" class="size-4 shrink-0" />
                    {{ trans_choice(':count question in the question bank|:count questions in the question bank', $teacher->question_bank_items_count) }}
                </p>
            </x-section>
        </div>

        <aside class="flex min-w-0 flex-col gap-6">
            <x-section title="Subjects" description="The teacher only sees classrooms, quizzes and learners of these subjects." icon="book-open">
                <x-slot:actions>
                    <x-button variant="ghost" size="sm" icon-trailing="arrow-right" :href="route('admin.subject-assignments.index')">Manage</x-button>
                </x-slot:actions>
                @if ($teacher->assignedSubjects->isEmpty())
                    <x-empty-state compact icon="book-open" title="No subjects yet" description="Assign at least one subject so the teacher can create classrooms and quizzes.">
                        <x-slot:actions>
                            <x-button size="sm" variant="soft" icon="user-check" :href="route('admin.subject-assignments.index')">Assign subjects</x-button>
                        </x-slot:actions>
                    </x-empty-state>
                @else
                    <div class="flex flex-wrap gap-2">
                        @foreach ($teacher->assignedSubjects as $subject)
                            <x-subject-chip :subject="$subject" size="lg" />
                        @endforeach
                    </div>
                @endif
            </x-section>

            <x-people.account-panel
                :user="$teacher"
                :delete-blocked-reason="$ownsRecords ? 'Teachers who own classrooms or quizzes cannot be deleted. Deactivate the account instead; their classes and quizzes stay in reports.' : null"
            />
        </aside>
    </div>
</x-layouts.app>
