@php
    $isFiltered = $filters['difficulty'] !== null || $filters['type'] !== null || $filters['reviewed'] || $filters['search'] !== '';
    $selectableCount = $items->getCollection()->reject(fn ($item) => in_array($item->id, $addedItemIds, true))->count();
@endphp

<x-layouts.app :title="'Add from question bank · '.$quiz->title">
    <x-page-header
        eyebrow="Quiz builder"
        title="Add from question bank"
        :description="$quiz->subject->name.' · '.$quiz->grade_level->label().' questions you wrote or that other teachers share. Each one is copied into “'.$quiz->title.'” with its picture or video.'"
        :breadcrumbs="[
            ['label' => 'Quizzes', 'url' => route('teacher.quizzes.index')],
            ['label' => $quiz->title, 'url' => route('teacher.quizzes.show', $quiz)],
            ['label' => 'Add from question bank'],
        ]"
    >
        <x-slot:actions>
            <x-button variant="secondary" icon="plus" :href="route('teacher.quizzes.questions.create', $quiz)">Write a new question</x-button>
        </x-slot:actions>
    </x-page-header>

    <x-filter-bar
        :action="route('teacher.quizzes.bank-questions.create', $quiz)"
        search-label="Search questions"
        search-placeholder="Search question, topic or competency code"
        :reset-url="route('teacher.quizzes.bank-questions.create', $quiz)"
    >
        <x-form.select name="type" label="Question type" hide-label placeholder="All types" :options="\App\Enums\QuestionType::options()" :value="$filters['type']" :old="false" class="sm:w-48" />
        <x-form.select name="difficulty" label="Difficulty" hide-label placeholder="Any difficulty" :options="\App\Enums\Difficulty::options()" :value="$filters['difficulty']" :old="false" class="sm:w-44" />
        <x-form.checkbox name="reviewed" value="1" label="Reviewed only" :checked="$filters['reviewed']" :old="false" class="sm:self-center" />
    </x-filter-bar>

    @if ($items->isEmpty())
        @if ($isFiltered)
            <x-empty-state variant="card" illustration="search" title="No questions match your filters" description="Try another word, type or difficulty, or clear the filters.">
                <x-slot:actions>
                    <x-button variant="secondary" icon="rotate-ccw" :href="route('teacher.quizzes.bank-questions.create', $quiz)">Clear filters</x-button>
                </x-slot:actions>
            </x-empty-state>
        @else
            <x-empty-state variant="card" icon="library" :title="'No '.$quiz->subject->name.' · '.$quiz->grade_level->label().' questions in the bank yet'" description="Save questions from your quizzes to the bank, or write bank questions to reuse them in every quiz.">
                <x-slot:actions>
                    <x-button icon="plus" :href="route('teacher.quizzes.questions.create', $quiz)">Write a question for this quiz</x-button>
                    @can('create', \App\Models\QuestionBankItem::class)
                        <x-button variant="secondary" icon="library" :href="route('teacher.question-bank.create', ['subject' => $quiz->subject_id, 'grade' => $quiz->grade_level->value])">New bank question</x-button>
                    @endcan
                </x-slot:actions>
            </x-empty-state>
        @endif
    @else
        <form method="POST" action="{{ route('teacher.quizzes.bank-questions.store', $quiz) }}" class="flex flex-col gap-4" data-submit-once>
            @csrf

            <div class="flex flex-wrap items-center justify-between gap-3">
                @if ($selectableCount > 0)
                    <x-form.checkbox name="select_all" label="Select every question on this page" data-check-all="bank-items" :old="false" data-js-only />
                @endif
                <p class="text-sm text-ink-600 dark:text-ink-300">{{ trans_choice(':count question found|:count questions found', $items->total()) }}. Selections apply to this page.</p>
            </div>

            <x-form.error for="item_ids" />

            <ul role="list" class="grid grid-cols-1 gap-3 lg:grid-cols-2">
                @foreach ($items as $item)
                    @php $isAdded = in_array($item->id, $addedItemIds, true); @endphp
                    <li data-bank-item="{{ $item->id }}">
                        <label @class([
                            'flex h-full min-w-0 cursor-pointer gap-3 rounded-2xl bg-white p-4 shadow-card ring-1 transition ring-inset has-checked:bg-leaf-50/60 has-checked:ring-2 has-checked:ring-leaf-500 has-focus-visible:outline-2 has-focus-visible:outline-offset-2 has-focus-visible:outline-leaf-500 dark:bg-ink-900 dark:shadow-none dark:has-checked:bg-leaf-400/10 dark:has-checked:ring-leaf-400',
                            'ring-ink-900/5 hover:ring-ink-900/15 dark:ring-white/8 dark:hover:ring-white/20' => ! $isAdded,
                            'cursor-not-allowed opacity-60 ring-ink-900/5 dark:ring-white/8' => $isAdded,
                        ])>
                            <input
                                type="checkbox"
                                name="item_ids[]"
                                value="{{ $item->id }}"
                                class="mt-0.5 size-5 shrink-0 cursor-pointer rounded-md accent-leaf-600 disabled:cursor-not-allowed"
                                data-check-group="bank-items"
                                aria-describedby="bank-item-{{ $item->id }}-summary"
                                @checked(! $isAdded && in_array($item->id, array_map('intval', (array) old('item_ids', [])), true))
                                @disabled($isAdded)
                            >
                            <span class="min-w-0 flex-1">
                                <span class="sr-only">Add this question</span>
                                <x-quiz.bank-item-summary :item="$item" :show-author="$item->teacher_id !== auth()->id()" :show-subject="false" id="bank-item-{{ $item->id }}-summary" />
                                @if ($isAdded)
                                    <span class="mt-3 inline-flex"><x-badge color="slate" icon="check" size="sm">Already in this quiz</x-badge></span>
                                @endif
                            </span>
                        </label>
                    </li>
                @endforeach
            </ul>

            @if ($items->hasPages())
                <div>{{ $items->links() }}</div>
            @endif

            <x-form.actions :cancel="route('teacher.quizzes.show', $quiz)" submit="Add selected questions" submit-icon="list-plus">
                <span data-js-only class="inline-flex items-center gap-2">
                    <x-icon name="check-check" class="size-4" />
                    <span><span data-check-count="bank-items" class="font-semibold text-ink-900 tabular-nums dark:text-white">0</span> selected</span>
                </span>
            </x-form.actions>
        </form>
    @endif
</x-layouts.app>
