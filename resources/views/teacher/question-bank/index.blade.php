@php
    $teacher = auth()->user();
    $canCreate = $teacher->can('create', \App\Models\QuestionBankItem::class);
    $isFiltered = $filters['subject'] !== null || $filters['grade'] !== null || $filters['type'] !== null
        || $filters['difficulty'] !== null || $filters['reviewed'] !== null || $filters['search'] !== '';
    $filterQuery = array_filter([
        'subject' => $filters['subject'],
        'grade' => $filters['grade']?->value,
        'type' => $filters['type']?->value,
        'difficulty' => $filters['difficulty']?->value,
        'reviewed' => $filters['reviewed'],
        'search' => $filters['search'] !== '' ? $filters['search'] : null,
    ]);
@endphp

<x-layouts.app title="Question bank">
    <x-page-header
        eyebrow="Assessments"
        title="Question bank"
        description="Reusable questions for your subjects. Share good questions with the other teachers of your subjects and add any of them to a quiz."
        :breadcrumbs="[['label' => 'Dashboard', 'url' => route('teacher.dashboard')], ['label' => 'Question bank']]"
    >
        <x-slot:actions>
            <x-button variant="secondary" icon="clipboard-check" :href="route('teacher.quizzes.index')">Quizzes</x-button>
            @if ($canCreate)
                <x-dropdown label="New question" icon="plus" variant="secondary" width="lg">
                    <x-dropdown.label>Question type</x-dropdown.label>
                    @foreach (\App\Enums\QuestionType::cases() as $type)
                        <x-dropdown.item :href="route('teacher.question-bank.create', array_filter(['type' => $type->value, 'subject' => $filters['subject'], 'grade' => $filters['grade']?->value]))" :icon="$type->icon()">{{ $type->label() }}</x-dropdown.item>
                    @endforeach
                </x-dropdown>
            @endif
        </x-slot:actions>
    </x-page-header>

    <div class="flex flex-col gap-4">
        <x-tabs label="Question bank sections">
            <x-tab :href="route('teacher.question-bank.index', $filterQuery)" :active="$tab === 'mine'" icon="user" :count="$counts['mine']">My questions</x-tab>
            <x-tab :href="route('teacher.question-bank.index', [...$filterQuery, 'tab' => 'shared'])" :active="$tab === 'shared'" icon="users" :count="$counts['shared']">Shared with me</x-tab>
        </x-tabs>

        <x-filter-bar
            :action="route('teacher.question-bank.index')"
            search-label="Search the question bank"
            search-placeholder="Search question, topic or competency code"
            :reset-url="route('teacher.question-bank.index', array_filter(['tab' => $tab === 'shared' ? 'shared' : null]))"
            :preserve="['tab']"
        >
            <x-form.select name="subject" label="Subject" hide-label placeholder="All subjects" :options="$subjectOptions" :value="$filters['subject']" :old="false" class="sm:w-44" />
            <x-form.select name="grade" label="Grade" hide-label placeholder="All grades" :options="\App\Enums\GradeLevel::options()" :value="$filters['grade']" :old="false" class="sm:w-36" />
            <x-form.select name="type" label="Question type" hide-label placeholder="All types" :options="\App\Enums\QuestionType::options()" :value="$filters['type']" :old="false" class="sm:w-44" />
            <x-form.select name="difficulty" label="Difficulty" hide-label placeholder="Any difficulty" :options="\App\Enums\Difficulty::options()" :value="$filters['difficulty']" :old="false" class="sm:w-40" />
            <x-form.select name="reviewed" label="Review status" hide-label placeholder="Reviewed or not" :options="['yes' => 'Reviewed', 'no' => 'Not reviewed']" :value="$filters['reviewed']" :old="false" class="sm:w-44" />
        </x-filter-bar>
    </div>

    @if ($items->isEmpty())
        @if ($isFiltered)
            <x-empty-state variant="card" illustration="search" title="No questions match your filters" description="Try another word, subject or type, or clear the filters.">
                <x-slot:actions>
                    <x-button variant="secondary" icon="rotate-ccw" :href="route('teacher.question-bank.index', array_filter(['tab' => $tab === 'shared' ? 'shared' : null]))">Clear filters</x-button>
                </x-slot:actions>
            </x-empty-state>
        @elseif ($tab === 'shared')
            <x-empty-state variant="card" icon="users" title="Nothing shared with you yet" description="When other teachers of your subjects share their questions, they appear here so you can add them to your quizzes or copy them into your bank." />
        @else
            <x-empty-state variant="card" icon="library" title="Your question bank is empty" description="Write questions once and reuse them in every quiz. You can also save questions from your quizzes to the bank.">
                @if ($canCreate)
                    <x-slot:actions>
                        <x-button icon="plus" :href="route('teacher.question-bank.create')">New question</x-button>
                        @if ($counts['shared'] > 0)
                            <x-button variant="secondary" icon="users" :href="route('teacher.question-bank.index', ['tab' => 'shared'])">Browse shared questions</x-button>
                        @endif
                    </x-slot:actions>
                @endif
            </x-empty-state>
        @endif
    @else
        <div class="grid grid-cols-1 gap-4 md:grid-cols-2 2xl:grid-cols-3">
            @foreach ($items as $item)
                <x-card padding="sm" class="flex flex-col" data-bank-item="{{ $item->id }}">
                    <x-quiz.bank-item-summary :item="$item" :show-author="$tab === 'shared'" />
                    <x-slot:footer>
                        <div class="flex flex-wrap items-center justify-end gap-2">
                            <x-button variant="ghost" size="sm" icon="eye" :href="route('teacher.question-bank.show', $item)">View<span class="sr-only"> question</span></x-button>
                            @can('update', $item)
                                <x-button variant="ghost" size="sm" icon="pencil" :href="route('teacher.question-bank.edit', $item)">Edit<span class="sr-only"> question</span></x-button>
                            @endcan
                            @if ($canCreate && $tab === 'shared')
                                <form method="POST" action="{{ route('teacher.question-bank.duplicate.store', $item) }}" data-submit-once>
                                    @csrf
                                    <x-button type="submit" variant="soft" size="sm" icon="copy">Copy to my bank</x-button>
                                </form>
                            @endif
                            @can('delete', $item)
                                <x-confirm-dialog
                                    :action="route('teacher.question-bank.destroy', $item)"
                                    title="Delete this question?"
                                    message="It is removed from your question bank with its picture or video. Quizzes that already use it keep their own copy."
                                    confirm="Delete question"
                                    button="Delete"
                                    button-variant="ghost"
                                />
                            @endcan
                        </div>
                    </x-slot:footer>
                </x-card>
            @endforeach
        </div>

        @if ($items->hasPages())
            <div>{{ $items->links() }}</div>
        @endif
    @endif
</x-layouts.app>
