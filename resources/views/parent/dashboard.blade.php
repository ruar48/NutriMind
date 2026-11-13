@php
    $firstNames = $children->map(fn ($child) => \Illuminate\Support\Str::before(trim($child->name).' ', ' '));
    $heroDescription = $children->isEmpty()
        ? 'Welcome to the NutriMind Parent Portal. Follow your child’s healthy-eating adventure here.'
        : 'Here is how '.$firstNames->join(', ', ' and ').' '.($children->count() === 1 ? 'is' : 'are').' doing in NutriMind.';
    $breakdownFor = function (\Illuminate\Support\Collection $counts) use ($children, $firstNames): string {
        if ($counts->sum() === 0) {
            return 'Nothing yet this week';
        }

        if ($children->count() === 1) {
            return 'Since Monday';
        }

        return $children->values()
            ->map(fn ($child, $index) => $firstNames[$index].' '.number_format($counts->get($child->getKey(), 0)))
            ->join(' · ');
    };
    $singleChild = $children->count() === 1 ? $children->first() : null;
@endphp

<x-layouts.app title="Dashboard" description="Your children’s progress in NutriMind, teacher feedback and the parent forum.">
    <x-hero-banner eyebrow="Parent Portal" :title="$greeting" color="carrot" :description="$heroDescription">
        <div class="flex flex-col gap-4">
            @if ($canViewFeedback && $unreadFeedbackCount > 0)
                <div class="flex flex-wrap gap-2">
                    <x-badge color="sun" icon="message-circle" size="lg">
                        {{ trans_choice(':count new message from teachers|:count new messages from teachers', $unreadFeedbackCount) }}
                    </x-badge>
                </div>
            @endif

            @if ($schoolAnnouncement)
                <div class="flex max-w-xl items-start gap-3 rounded-2xl bg-white/10 p-3.5 ring-1 ring-white/15 ring-inset" data-dashboard-announcement>
                    <span class="grid size-8 shrink-0 place-items-center rounded-xl bg-sun-400 text-sun-950">
                        <x-icon name="megaphone" class="size-4" stroke-width="2" />
                    </span>
                    <p class="min-w-0 text-sm text-white/90">
                        <span class="block font-semibold text-white">School announcement</span>
                        {{ $schoolAnnouncement }}
                    </p>
                </div>
            @endif
        </div>

        <x-slot:actions>
            @if ($canViewProgress && $children->isNotEmpty())
                <x-button variant="secondary" icon="users-round" :href="route('parent.children.index')">My children</x-button>
            @endif
            @if ($canViewForum)
                <x-button variant="ghost" icon="messages-square" :href="route('forum.index')" class="text-white hover:bg-white/10 hover:text-white">Parent forum</x-button>
            @endif
        </x-slot:actions>
    </x-hero-banner>

    @if ($children->isEmpty())
        <x-empty-state
            variant="card"
            illustration="classroom"
            title="Ask the school to link your child"
            :description="'Your parent account is not linked to a learner yet. Ask your child’s teacher or the school administrator to link your child’s NutriMind account to '.($parent->email ?? 'this parent account').'. Their progress appears here right after.'"
            data-animate
        >
            <x-slot:actions>
                <x-button variant="secondary" icon="user-cog" :href="route('account.edit')">Check my account details</x-button>
                @if ($canViewForum)
                    <x-button variant="ghost" icon="messages-square" :href="route('forum.index')">Visit the parent forum</x-button>
                @endif
            </x-slot:actions>
        </x-empty-state>
    @else
        @unless ($canViewProgress)
            <x-alert variant="info" title="Progress details are turned off">
                The school has not shared learning progress with parent accounts. Contact your child’s teacher or the school administrator if you need it.
            </x-alert>
        @endunless

        <section class="flex flex-col gap-4" aria-labelledby="children-heading" data-animate style="--stagger: 1">
            <div class="flex flex-wrap items-end justify-between gap-3">
                <div class="min-w-0">
                    <h2 id="children-heading" class="font-display text-lg font-semibold tracking-tight text-ink-950 dark:text-white">
                        {{ $children->count() === 1 ? 'Your child' : 'Your children' }}
                    </h2>
                    <p class="text-sm text-ink-600 dark:text-ink-300">Level, quiz average and missions from the NutriMind game.</p>
                </div>
                @if ($canViewProgress)
                    <x-button variant="ghost" size="sm" icon-trailing="arrow-right" :href="route('parent.children.index')">All children</x-button>
                @endif
            </div>

            <div class="grid grid-cols-1 gap-6 md:grid-cols-2 xl:grid-cols-3">
                @foreach ($cards as $card)
                    <x-parent.child-card
                        :student="$card['student']"
                        :relationship="$card['student']->pivot->relationship"
                        :classrooms="$card['student']->enrolledClassrooms"
                        :stats="$card['stats']"
                        :feedback="$card['feedback']"
                        :href="$canViewProgress ? route('parent.children.show', $card['student']) : null"
                    />
                @endforeach
            </div>
        </section>

        @if ($highlights !== null)
            <section class="flex flex-col gap-4" aria-labelledby="week-heading" data-animate style="--stagger: 2">
                <div class="min-w-0">
                    <h2 id="week-heading" class="font-display text-lg font-semibold tracking-tight text-ink-950 dark:text-white">This week</h2>
                    <p class="text-sm text-ink-600 dark:text-ink-300">
                        Highlights since Monday, <time datetime="{{ $weekStart->toDateString() }}">{{ $weekStart->format('M j') }}</time>.
                    </p>
                </div>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-3" data-week-highlights>
                    <x-stat-card
                        label="Quizzes completed"
                        :value="number_format($highlights['quizzes']->sum())"
                        icon="clipboard-check"
                        color="sky"
                        :hint="$breakdownFor($highlights['quizzes'])"
                        :count-up="$highlights['quizzes']->sum()"
                        data-highlight="quizzes"
                    />
                    <x-stat-card
                        label="Rewards earned"
                        :value="number_format($highlights['rewards']->sum())"
                        icon="trophy"
                        color="sun"
                        :hint="$breakdownFor($highlights['rewards'])"
                        :count-up="$highlights['rewards']->sum()"
                        data-highlight="rewards"
                    />
                    <x-stat-card
                        label="Missions completed"
                        :value="number_format($highlights['missions']->sum())"
                        icon="flag"
                        color="carrot"
                        :hint="$breakdownFor($highlights['missions'])"
                        :count-up="$highlights['missions']->sum()"
                        data-highlight="missions"
                    />
                </div>
            </section>
        @endif
    @endif

    @if (($canViewFeedback && $children->isNotEmpty()) || $canViewForum)
        <div class="grid grid-cols-1 gap-6 xl:grid-cols-2" data-animate style="--stagger: 3">
            @if ($canViewFeedback && $children->isNotEmpty())
                <x-section title="From the teachers" description="The latest notes teachers shared about your children" icon="message-circle" color="grape" data-dashboard-feedback>
                    @if ($singleChild && $canViewProgress)
                        <x-slot:actions>
                            <x-button variant="ghost" size="sm" icon-trailing="arrow-right" :href="route('parent.children.show', [$singleChild, 'tab' => 'feedback'])">All messages</x-button>
                        </x-slot:actions>
                    @endif

                    @if ($recentFeedback->isEmpty())
                        <x-empty-state compact icon="message-circle" title="No messages yet" description="Notes from your child’s teachers appear here when they share one with you." />
                    @else
                        <ul class="divide-y divide-ink-900/5 dark:divide-white/8">
                            @foreach ($recentFeedback as $message)
                                <li class="py-4 first:pt-0 last:pb-0">
                                    <x-parent.feedback-card
                                        compact
                                        :feedback="$message"
                                        :is-new="$message->read_at === null"
                                        :show-student="$children->count() > 1"
                                        :href="$canViewProgress ? route('parent.children.show', [$message->student, 'tab' => 'feedback']) : null"
                                    />
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </x-section>
            @endif

            @if ($canViewForum)
                <x-section title="Parent forum" description="The newest conversations among parents and teachers" icon="messages-square" color="carrot" padding="none" data-dashboard-forum>
                    <x-slot:actions>
                        @if ($canStartThread)
                            <x-button variant="soft" size="sm" icon="plus" :href="route('forum.threads.create')">New thread</x-button>
                        @endif
                        <x-button variant="ghost" size="sm" icon-trailing="arrow-right" :href="route('forum.index')">Open forum</x-button>
                    </x-slot:actions>

                    @if ($threads->isEmpty())
                        <x-empty-state compact illustration="forum" title="No conversations yet" description="Ask a question or share a healthy recipe with other parents.">
                            @if ($canStartThread)
                                <x-slot:actions>
                                    <x-button size="sm" icon="plus" :href="route('forum.threads.create')">Start a thread</x-button>
                                </x-slot:actions>
                            @endif
                        </x-empty-state>
                    @else
                        <ul class="divide-y divide-ink-900/5 dark:divide-white/8">
                            @foreach ($threads as $thread)
                                @php
                                    $activityAt = $thread->last_activity_at ?? $thread->created_at;
                                @endphp
                                <li class="flex gap-3 px-5 py-4 sm:px-6" data-dashboard-thread="{{ $thread->getKey() }}">
                                    <x-avatar :name="$thread->author->name" size="md" />
                                    <div class="min-w-0 flex-1">
                                        <p class="text-sm font-semibold wrap-break-word text-ink-900 dark:text-ink-100">
                                            <a href="{{ route('forum.threads.show', $thread) }}" class="rounded hover:text-carrot-700 hover:underline dark:hover:text-carrot-300">{{ $thread->title }}</a>
                                        </p>
                                        <div class="mt-1.5 flex flex-wrap items-center gap-2">
                                            <x-subject-chip :name="$thread->category->name" :color="$thread->category->color" :icon="$thread->category->icon" size="sm" />
                                            @if ($thread->is_pinned)
                                                <x-badge color="sun" icon="pin" size="sm">Pinned</x-badge>
                                            @endif
                                            @if ($thread->is_locked)
                                                <x-badge color="slate" icon="lock" size="sm">Locked</x-badge>
                                            @endif
                                            @if ($thread->status !== \App\Enums\ForumContentStatus::Published)
                                                <x-status-badge :status="$thread->status" size="sm" />
                                            @endif
                                        </div>
                                        <p class="mt-1.5 flex flex-wrap items-center gap-x-1.5 gap-y-1 text-xs text-ink-500 dark:text-ink-400">
                                            <span>{{ $thread->author->name }}</span>
                                            @if ($thread->author->isTeacher() || $thread->author->isAdmin())
                                                <x-status-badge :status="$thread->author->role" size="sm" />
                                            @endif
                                            <span aria-hidden="true">·</span>
                                            <span>{{ trans_choice(':count reply|:count replies', $thread->replies_count) }}</span>
                                            <span aria-hidden="true">·</span>
                                            <time datetime="{{ $activityAt->toIso8601String() }}" title="{{ $activityAt->format('M j, Y g:i A') }}">Active {{ $activityAt->diffForHumans() }}</time>
                                        </p>
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </x-section>
            @endif
        </div>
    @endif
</x-layouts.app>
