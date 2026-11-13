@props([
    'thread',
    'categoryOptions',
    'action',
    'method' => 'POST',
    'cancel',
    'submit',
    'submitIcon' => 'send',
    'requiresApproval' => false,
])

<form method="POST" action="{{ $action }}" {{ $attributes->class('flex flex-col gap-8') }} data-submit-once data-animate style="--stagger: 1">
    @csrf
    @if (strtoupper($method) !== 'POST')
        @method($method)
    @endif

    <x-form.section title="Your thread" description="Pick the topic that fits best and give your thread a clear title so other families can find it.">
        <x-slot:aside>
            <div class="rounded-2xl bg-leaf-50/70 p-4 text-sm text-leaf-900 ring-1 ring-leaf-600/10 ring-inset dark:bg-leaf-400/5 dark:text-leaf-100 dark:ring-leaf-400/15">
                <p class="flex items-center gap-2 font-semibold">
                    <x-icon name="hand-heart" class="size-4" />
                    Community guidelines
                </p>
                <ul class="mt-2 list-disc space-y-1 pl-5 text-leaf-900/85 dark:text-leaf-100/80">
                    <li>Be kind and respectful.</li>
                    <li>Never share addresses, phone numbers or passwords.</li>
                    <li>No selling or advertising.</li>
                    <li>Share health tips from trusted sources.</li>
                </ul>
            </div>
        </x-slot:aside>

        <x-form.select name="forum_category_id" label="Category" :options="$categoryOptions" :value="$thread->forum_category_id"
            placeholder="Choose a category" required />
        <x-form.input name="title" label="Title" :value="$thread->title" minlength="5" maxlength="150"
            placeholder="e.g. Quick and healthy breakfast ideas" hint="5 to 150 characters." required />
        <x-form.textarea name="body" label="Message" :value="$thread->body" :max-length="5000" rows="10"
            hint="At least 10 characters. Line breaks are kept; links and formatting show as plain text." required />
    </x-form.section>

    <x-form.actions :cancel="$cancel" :submit="$submit" :submit-icon="$submitIcon">
        @if ($requiresApproval)
            <x-icon name="clock" class="size-4" />
            A moderator reviews new threads before they appear in the forum.
        @else
            <x-icon name="info" class="size-4" />
            Teachers, parents and administrators can read and reply to your thread.
        @endif
    </x-form.actions>
</form>
