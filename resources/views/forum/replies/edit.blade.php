<x-layouts.app title="Edit reply">
    <x-page-header
        eyebrow="Parent Forum"
        title="Edit your reply"
        :description="'In “'.\Illuminate\Support\Str::limit($reply->thread->title, 80).'”'"
        :breadcrumbs="[
            ['label' => 'Dashboard', 'url' => route('dashboard')],
            ['label' => 'Parent Forum', 'url' => route('forum.index')],
            ['label' => \Illuminate\Support\Str::limit($reply->thread->title, 40), 'url' => $replyUrl],
            ['label' => 'Edit reply'],
        ]"
    />

    <form method="POST" action="{{ route('forum.replies.update', $reply) }}" class="flex flex-col gap-8" data-submit-once data-animate style="--stagger: 1">
        @csrf
        @method('PUT')

        <x-form.section title="Reply" description="Fix a typo or add a detail. Your reply keeps its place in the thread.">
            <x-form.textarea name="body" label="Your reply" :value="$reply->body" :max-length="3000" rows="8" hint="At least 2 characters. Line breaks are kept." required />
        </x-form.section>

        <x-form.actions :cancel="$replyUrl" submit="Save reply">
            <x-icon name="info" class="size-4" />
            Readers see your changes right away.
        </x-form.actions>
    </form>
</x-layouts.app>
