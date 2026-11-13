@php
    $isFiltered = $filters['subject'] !== null || $filters['classroom'] !== null || $filters['search'] !== '';
    $tabQuery = array_filter([
        'subject' => $filters['subject'],
        'classroom' => $filters['classroom'],
        'search' => $filters['search'] !== '' ? $filters['search'] : null,
    ]);
    $canCreate = auth()->user()->can('create', \App\Models\Quiz::class);
    $canUseBank = auth()->user()->can('viewAny', \App\Models\QuestionBankItem::class);
    $statusIcons = ['draft' => 'square-pen', 'published' => 'circle-check', 'closed' => 'lock'];
@endphp

<x-layouts.app title="Quizzes">
    <x-page-header
        eyebrow="Assessments"
        title="Quizzes"
        description="Build quizzes with images and videos, review them as learners see them, and publish them to your classes."
        :breadcrumbs="[['label' => 'Dashboard', 'url' => route('teacher.dashboard')], ['label' => 'Quizzes']]"
    >
        <x-slot:actions>
            @if ($canUseBank)
                <x-button variant="secondary" icon="library" :href="route('teacher.question-bank.index')">Question bank</x-button>
            @endif
            @if ($canCreate)
                <x-button icon="plus" :href="route('teacher.quizzes.create')">New quiz</x-button>
            @endif
        </x-slot:actions>
    </x-page-header>

    <x-quiz.status-errors />

    @if ($lockedCount > 0)
        <x-alert variant="warning" icon="lock" data-quiz-locked-notice>
            {{ trans_choice(':count quiz is locked because the administrator removed your access to its subject.|:count quizzes are locked because the administrator removed your access to their subject.', $lockedCount) }}
        </x-alert>
    @endif

    <div class="flex flex-col gap-4">
        <x-tabs label="Filter by status">
            <x-tab :href="route('teacher.quizzes.index', $tabQuery)" :active="$status === null" icon="layers" :count="$counts['all']">All</x-tab>
            @foreach (\App\Enums\QuizStatus::cases() as $case)
                <x-tab :href="route('teacher.quizzes.index', [...$tabQuery, 'status' => $case->value])" :active="$status === $case" :icon="$statusIcons[$case->value]" :count="$counts[$case->value]">
                    {{ $case->label() }}
                </x-tab>
            @endforeach
        </x-tabs>

        <x-filter-bar
            :action="route('teacher.quizzes.index')"
            search-label="Search quizzes"
            search-placeholder="Search quiz titles"
            :reset-url="route('teacher.quizzes.index', array_filter(['status' => $status?->value]))"
            :preserve="['status']"
        >
            <x-form.select name="subject" label="Subject" hide-label placeholder="All subjects" :options="$subjectOptions" :value="$filters['subject']" :old="false" class="sm:w-48" />
            <x-form.select name="classroom" label="Classroom" hide-label placeholder="All classrooms" :options="$classroomOptions" :value="$filters['classroom']" :old="false" class="sm:w-56" />
        </x-filter-bar>
    </div>

    @if ($quizzes->isEmpty())
        @if ($isFiltered)
            <x-empty-state variant="card" illustration="search" title="No quizzes match your filters" description="Try another title, subject or classroom, or clear the filters.">
                <x-slot:actions>
                    <x-button variant="secondary" icon="rotate-ccw" :href="route('teacher.quizzes.index', array_filter(['status' => $status?->value]))">Clear filters</x-button>
                </x-slot:actions>
            </x-empty-state>
        @elseif ($status !== null && $counts['all'] > 0)
            <x-empty-state variant="card" :icon="$statusIcons[$status->value]" :title="'No '.strtolower($status->label()).' quizzes'" description="Quizzes with this status appear here.">
                <x-slot:actions>
                    <x-button variant="secondary" icon="layers" :href="route('teacher.quizzes.index')">Show all quizzes</x-button>
                </x-slot:actions>
            </x-empty-state>
        @else
            <x-empty-state variant="card" illustration="trophy" title="No quizzes yet" description="Create a quiz, add multiple choice, true or false and identification questions with pictures or videos, then publish it to your classes.">
                @if ($canCreate)
                    <x-slot:actions>
                        <x-button icon="plus" :href="route('teacher.quizzes.create')">New quiz</x-button>
                        @if ($canUseBank)
                            <x-button variant="secondary" icon="library" :href="route('teacher.question-bank.index')">Browse the question bank</x-button>
                        @endif
                    </x-slot:actions>
                @endif
            </x-empty-state>
        @endif
    @else
        <x-table caption="Quizzes">
            <x-slot:head>
                <x-table.th>Quiz</x-table.th>
                <x-table.th>Subject</x-table.th>
                <x-table.th>Classrooms</x-table.th>
                <x-table.th align="right">Attempts</x-table.th>
                <x-table.th align="right">Average</x-table.th>
                <x-table.th>Status</x-table.th>
                <x-table.th>Next due</x-table.th>
                <x-table.th align="right"><span class="sr-only">Actions</span></x-table.th>
            </x-slot:head>

            @foreach ($quizzes as $quiz)
                @php
                    $activeClassrooms = $quiz->classrooms->whereNull('archived_at');
                    $isReady = $quiz->questions_count > 0 && $activeClassrooms->isNotEmpty() && $quiz->isReviewed();
                @endphp
                <x-table.row data-quiz-row="{{ $quiz->id }}">
                    <x-table.td primary class="min-w-56">
                        <a href="{{ route('teacher.quizzes.show', $quiz) }}" class="block font-semibold wrap-break-word hover:text-leaf-700 hover:underline dark:hover:text-leaf-300">{{ $quiz->title }}</a>
                        <p class="mt-0.5 text-xs font-normal text-ink-500 tabular-nums dark:text-ink-400">
                            {{ $quiz->grade_level->label() }}
                            · {{ trans_choice(':count question|:count questions', $quiz->questions_count) }}
                            · {{ trans_choice(':count point|:count points', (int) $quiz->questions_sum_points) }}
                        </p>
                    </x-table.td>
                    <x-table.td><x-subject-chip :subject="$quiz->subject" size="sm" /></x-table.td>
                    <x-table.td>
                        @if ($quiz->classrooms->isEmpty())
                            <span class="text-sm text-ink-400 dark:text-ink-500">Not assigned</span>
                        @else
                            <div class="flex max-w-64 flex-wrap gap-1.5">
                                @foreach ($quiz->classrooms->take(2) as $classroom)
                                    <x-badge :color="$classroom->isArchived() ? 'slate' : 'leaf'" size="sm" :icon="$classroom->isArchived() ? 'archive' : 'school'">{{ $classroom->name }}</x-badge>
                                @endforeach
                                @if ($quiz->classrooms->count() > 2)
                                    <x-badge size="sm" :title="$quiz->classrooms->skip(2)->pluck('name')->join(', ')">+{{ $quiz->classrooms->count() - 2 }}</x-badge>
                                @endif
                            </div>
                        @endif
                    </x-table.td>
                    <x-table.td align="right" numeric>{{ number_format($quiz->submitted_attempts_count) }}</x-table.td>
                    <x-table.td align="right"><x-score-pill :value="$quiz->average_percentage" size="sm" /></x-table.td>
                    <x-table.td><x-status-badge :status="$quiz->status" size="sm" /></x-table.td>
                    <x-table.td class="whitespace-nowrap">
                        @if ($quiz->next_due_at)
                            <time datetime="{{ $quiz->next_due_at->toIso8601String() }}" title="{{ $quiz->next_due_at->format('M j, Y g:i A') }}">{{ $quiz->next_due_at->format('M j, Y') }}</time>
                        @else
                            <span class="text-ink-400 dark:text-ink-500">—</span>
                        @endif
                    </x-table.td>
                    <x-table.td align="right">
                        <x-dropdown variant="icon" icon="ellipsis-vertical" :label="'Actions for '.$quiz->title" width="lg">
                            <x-dropdown.item :href="route('teacher.quizzes.show', $quiz)" icon="layout-grid">Open builder</x-dropdown.item>
                            <x-dropdown.item :href="route('teacher.quizzes.review.show', $quiz)" icon="eye">Preview as learner</x-dropdown.item>
                            @if (! $quiz->isDraft() || $quiz->attempts_exists)
                                <x-dropdown.item :href="route('teacher.quizzes.results.show', $quiz)" icon="chart-column">View results</x-dropdown.item>
                            @endif
                            @can('update', $quiz)
                                <x-dropdown.item :href="route('teacher.quizzes.edit', $quiz)" icon="pencil">Edit settings</x-dropdown.item>
                                <form method="POST" action="{{ route('teacher.quizzes.duplicate.store', $quiz) }}" data-submit-once>
                                    @csrf
                                    <x-dropdown.item type="submit" icon="copy">Duplicate</x-dropdown.item>
                                </form>
                                <x-dropdown.divider />
                                <x-quiz.status-actions :quiz="$quiz" :ready="$isReady" :has-attempts="$quiz->attempts_exists" menu />
                            @endcan
                            @can('delete', $quiz)
                                <x-dropdown.divider />
                                <x-confirm-dialog
                                    :action="route('teacher.quizzes.destroy', $quiz)"
                                    :title="'Delete “'.$quiz->title.'”?'"
                                    message="The quiz, its questions and their images and videos are removed permanently. This cannot be undone."
                                    confirm="Delete quiz"
                                    button="Delete"
                                    button-variant="menu"
                                />
                            @endcan
                        </x-dropdown>
                    </x-table.td>
                </x-table.row>
            @endforeach

            <x-slot:footer>{{ $quizzes->links() }}</x-slot:footer>
        </x-table>
    @endif
</x-layouts.app>
