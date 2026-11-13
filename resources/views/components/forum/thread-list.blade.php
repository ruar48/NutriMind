@props([
    'threads',
    'showCategory' => true,
])

<ul role="list" {{ $attributes->class('divide-y divide-ink-900/5 dark:divide-white/8') }}>
    @foreach ($threads as $thread)
        @php
            $lastActivity = $thread->last_activity_at ?? $thread->created_at;
        @endphp
        <li @class([
            'relative flex gap-4 px-5 py-4 transition-colors hover:bg-ink-50/70 focus-within:bg-ink-50/70 sm:px-6 dark:hover:bg-white/3 dark:focus-within:bg-white/3',
            'bg-grape-50/40 dark:bg-grape-400/5' => $thread->is_pinned,
        ]) data-forum-thread="{{ $thread->id }}">
            <span class="mt-0.5 hidden shrink-0 sm:block">
                <x-avatar :name="$thread->author->name" size="md" />
            </span>

            <div class="min-w-0 flex-1">
                @if ($thread->is_pinned || $thread->is_locked || $thread->status !== \App\Enums\ForumContentStatus::Published)
                    <div class="mb-1.5 flex flex-wrap items-center gap-1.5">
                        <x-forum.thread-badges :thread="$thread" />
                    </div>
                @endif

                <h3 class="font-display text-base leading-snug font-semibold tracking-tight text-ink-950 dark:text-white">
                    <a href="{{ route('forum.threads.show', $thread) }}" class="rounded-sm after:absolute after:inset-0 hover:text-leaf-700 focus-visible:outline-2 focus-visible:outline-offset-2 dark:hover:text-leaf-300">{{ $thread->title }}</a>
                </h3>
                <p class="mt-1 line-clamp-2 text-sm text-ink-600 dark:text-ink-300">{{ \Illuminate\Support\Str::limit(\Illuminate\Support\Str::squish($thread->body), 220) }}</p>

                <div class="mt-2.5 flex flex-wrap items-center gap-x-3 gap-y-2 text-xs text-ink-500 dark:text-ink-400">
                    @if ($showCategory)
                        <x-subject-chip :name="$thread->category->name" :color="$thread->category->color" :icon="$thread->category->icon" size="sm" />
                    @endif
                    <span class="inline-flex min-w-0 items-center gap-1.5">
                        <span class="flex sm:hidden">
                            <x-avatar :name="$thread->author->name" size="xs" />
                        </span>
                        <span class="truncate font-medium text-ink-700 dark:text-ink-200">{{ $thread->author->name }}</span>
                        <x-forum.role-badge :user="$thread->author" />
                    </span>
                    <span class="inline-flex items-center gap-1">
                        <x-icon name="clock" class="size-3.5" />
                        <span class="sr-only">Last activity</span>
                        <time datetime="{{ $lastActivity->toIso8601String() }}" title="{{ $lastActivity->format('M j, Y g:i A') }}">{{ $lastActivity->diffForHumans() }}</time>
                    </span>
                    <span class="inline-flex items-center gap-1 sm:hidden">
                        <x-icon name="message-square" class="size-3.5" />
                        {{ trans_choice(':count reply|:count replies', $thread->replies_count) }}
                    </span>
                </div>
            </div>

            <div class="hidden w-20 shrink-0 flex-col items-end justify-center text-right sm:flex" data-forum-replies-count="{{ $thread->replies_count }}">
                <span class="font-display text-xl font-bold text-ink-950 tabular-nums dark:text-white">{{ number_format($thread->replies_count) }}</span>
                <span class="text-xs text-ink-500 dark:text-ink-400">{{ $thread->replies_count === 1 ? 'reply' : 'replies' }}</span>
            </div>
        </li>
    @endforeach
</ul>
