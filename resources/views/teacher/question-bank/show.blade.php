@php
    $teacher = auth()->user();
    $isOwner = $item->teacher_id !== null && (int) $item->teacher_id === (int) $teacher->id;
    $canUpdate = $teacher->can('update', $item);
    $canDelete = $teacher->can('delete', $item);
    $canCopy = $teacher->can('create', \App\Models\QuestionBankItem::class);
    $excerpt = \Illuminate\Support\Str::limit(\Illuminate\Support\Str::squish($item->prompt), 60);
@endphp

<x-layouts.app :title="'Bank question · '.$excerpt">
    <x-page-header
        eyebrow="Question bank"
        :title="$excerpt"
        :description="$item->subject->name.' · '.$item->grade_level->label().($item->topic ? ' · '.$item->topic : '')"
        :breadcrumbs="[['label' => 'Question bank', 'url' => route('teacher.question-bank.index', $isOwner ? [] : ['tab' => 'shared'])], ['label' => $excerpt]]"
    >
        <x-slot:meta>
            <x-status-badge :status="$item->type" />
            <x-status-badge :status="$item->difficulty" />
            <x-subject-chip :subject="$item->subject" />
            @if ($item->is_shared)
                <x-badge color="sky" icon="users">Shared</x-badge>
            @else
                <x-badge color="slate" icon="lock">Private</x-badge>
            @endif
            @if ($item->reviewed_at !== null)
                <x-badge color="leaf" icon="badge-check" :title="$item->reviewed_at->format('M j, Y g:i A')">Reviewed</x-badge>
            @endif
        </x-slot:meta>
        <x-slot:actions>
            @if ($canCopy && ! $isOwner)
                <form method="POST" action="{{ route('teacher.question-bank.duplicate.store', $item) }}" data-submit-once>
                    @csrf
                    <x-button type="submit" icon="copy">Copy to my bank</x-button>
                </form>
            @endif
            @if ($canUpdate)
                <x-button variant="secondary" icon="pencil" :href="route('teacher.question-bank.edit', $item)">Edit</x-button>
            @endif
            @if ($isOwner && ($canCopy || $canDelete))
                <x-dropdown label="More" icon="ellipsis" variant="secondary">
                    @if ($canCopy)
                        <form method="POST" action="{{ route('teacher.question-bank.duplicate.store', $item) }}" data-submit-once>
                            @csrf
                            <x-dropdown.item type="submit" icon="copy">Duplicate</x-dropdown.item>
                        </form>
                    @endif
                    @if ($canDelete)
                        <x-dropdown.divider />
                        <x-confirm-dialog
                            :action="route('teacher.question-bank.destroy', $item)"
                            title="Delete this question?"
                            message="It is removed from your question bank with its picture or video. Quizzes that already use it keep their own copy."
                            confirm="Delete question"
                            button="Delete question"
                            button-variant="menu"
                        />
                    @endif
                </x-dropdown>
            @endif
        </x-slot:actions>
    </x-page-header>

    <div class="grid grid-cols-1 gap-6 xl:grid-cols-3">
        <div class="flex min-w-0 flex-col gap-6 xl:col-span-2">
            <x-card data-animate>
                <div class="@container flex min-w-0 flex-col gap-4">
                    <p class="text-base font-medium wrap-break-word whitespace-pre-line text-ink-900 dark:text-ink-50">{{ $item->prompt }}</p>
                    <x-question.media :question="$item" />
                    <x-quiz.answer-key :question="$item" />
                    @if (filled($item->explanation))
                        <div class="flex gap-3 rounded-xl bg-sky-50 px-3.5 py-3 ring-1 ring-sky-600/15 ring-inset dark:bg-sky-400/10 dark:ring-sky-400/20">
                            <x-icon name="lightbulb" class="mt-0.5 size-4 shrink-0 text-sky-600 dark:text-sky-300" />
                            <div class="min-w-0 text-sm">
                                <p class="font-medium text-sky-900 dark:text-sky-100">Explanation</p>
                                <p class="mt-0.5 wrap-break-word whitespace-pre-line text-sky-900/80 dark:text-sky-100/80">{{ $item->explanation }}</p>
                            </div>
                        </div>
                    @endif
                </div>
            </x-card>

            <x-section title="Used in your quizzes" :description="trans_choice('Added to quizzes :count time in total|Added to quizzes :count times in total', $item->times_used)" icon="clipboard-check" color="grape" padding="none">
                @if ($quizzes->isEmpty())
                    <x-empty-state compact icon="clipboard-check" title="Not in any of your quizzes yet" description="Open a draft quiz of the same subject and grade and choose “Add from bank”." />
                @else
                    <ul role="list" class="divide-y divide-ink-900/5 dark:divide-white/8">
                        @foreach ($quizzes as $quiz)
                            <li class="flex items-center justify-between gap-3 px-5 py-3 sm:px-6">
                                <a href="{{ route('teacher.quizzes.show', $quiz) }}" class="min-w-0 truncate text-sm font-medium text-ink-900 hover:text-leaf-700 hover:underline dark:text-ink-50 dark:hover:text-leaf-300">{{ $quiz->title }}</a>
                                <x-status-badge :status="$quiz->status" size="sm" />
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-section>
        </div>

        <aside class="flex min-w-0 flex-col gap-6">
            <x-section title="Details" icon="info" color="slate">
                <x-definition-list>
                    <x-detail-row label="Subject"><x-subject-chip :subject="$item->subject" size="sm" /></x-detail-row>
                    <x-detail-row label="Grade level" :value="$item->grade_level->label()" />
                    <x-detail-row label="Learning module" :value="$item->module?->title" />
                    <x-detail-row label="Topic" :value="$item->topic" />
                    <x-detail-row label="Competency code"><span class="font-mono">{{ $item->competency_code ?? '—' }}</span></x-detail-row>
                    <x-detail-row label="Written by" :value="$isOwner ? 'You' : ($item->teacher?->name ?? 'A former teacher')" />
                    <x-detail-row label="Times used" :value="number_format($item->times_used)" />
                    <x-detail-row label="Reviewed" :value="$item->reviewed_at?->format('M j, Y') ?? 'Not yet'" />
                    <x-detail-row label="Last updated" :value="$item->updated_at?->format('M j, Y g:i A')" />
                </x-definition-list>
            </x-section>
        </aside>
    </div>
</x-layouts.app>
