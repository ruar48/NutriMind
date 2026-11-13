@php
    $pointsPerLevel = max(1, (int) $settings['points_per_level']);
    $maxLevel = max(1, (int) $settings['max_level']);
    $samplePoints = 1250;
    $sampleLevel = min($maxLevel, intdiv($samplePoints, $pointsPerLevel) + 1);
    $topLevelPoints = ($maxLevel - 1) * $pointsPerLevel;
@endphp

<x-layouts.app title="Settings" description="School profile, academic defaults, gamification, parent forum and announcement.">
    <x-page-header
        eyebrow="System"
        title="Settings"
        description="These settings apply to every portal. The school profile also appears on printed reports."
        :breadcrumbs="[['label' => 'Dashboard', 'url' => route('admin.dashboard')], ['label' => 'Settings']]"
    />

    <form method="POST" action="{{ route('admin.settings.update') }}" class="flex flex-col gap-8" data-submit-once data-setup-settings-form>
        @csrf
        @method('PUT')

        <x-form.section id="school-profile" title="School profile" description="Shown on sign-in pages, in the sidebar and on printed reports." columns="2">
            <x-form.input name="school_name" label="School name" :value="$settings['school_name']" maxlength="150" prefix-icon="school" autocomplete="organization" required class="col-span-full" />
            <x-form.input name="school_id" label="DepEd School ID" :value="$settings['school_id']" inputmode="numeric" maxlength="10" hint="6 to 10 digits." prefix-icon="hash" optional />
            <x-form.input name="school_head" label="School head" :value="$settings['school_head']" maxlength="150" hint="Signs printed reports as “Noted by”." prefix-icon="user-round" optional />
            <x-form.input name="school_address" label="School address" :value="$settings['school_address']" maxlength="255" prefix-icon="map" autocomplete="street-address" optional class="col-span-full" />
            <x-form.input name="school_email" type="email" label="School email" :value="$settings['school_email']" maxlength="255" prefix-icon="mail" autocomplete="email" optional />
            <x-form.input name="school_phone" type="tel" label="School phone" :value="$settings['school_phone']" maxlength="30" prefix-icon="phone" autocomplete="tel" optional />
        </x-form.section>

        <x-form.section id="academic-defaults" title="Academic defaults" description="Starting values for new quizzes and classrooms. Teachers can change them per quiz or classroom." columns="2">
            <x-form.input name="default_passing_score" type="number" label="Passing score" :value="$settings['default_passing_score']" min="50" max="100" suffix="%" hint="50 to 100. DepEd's passing mark is 75%." required />
            <x-form.input name="default_max_attempts" type="number" label="Attempts allowed" :value="$settings['default_max_attempts']" min="1" max="10" hint="1 to 10. Leave empty for unlimited attempts." optional />
            <x-form.radio-cards
                name="default_learning_mode"
                label="Learning mode"
                :options="\App\Enums\LearningMode::cases()"
                :value="$learningMode"
                columns="3"
                required
                class="col-span-full"
            />
        </x-form.section>

        <x-form.section id="gamification" title="Gamification" description="Learners level up as they earn points in the game. Levels in every portal are worked out from these values." columns="2">
            <x-form.input name="points_per_level" type="number" label="Points per level" :value="$settings['points_per_level']" min="50" max="10000" suffix="XP" hint="50 to 10,000." required data-setup-points-per-level />
            <x-form.input name="max_level" type="number" label="Highest level" :value="$settings['max_level']" min="5" max="200" hint="5 to 200." required data-setup-max-level />

            <x-slot:aside>
                <div class="rounded-2xl bg-white p-4 ring-1 ring-ink-900/5 dark:bg-white/3 dark:ring-white/8" data-setup-level-preview data-sample-points="{{ $samplePoints }}" aria-live="polite">
                    <p class="text-xs font-semibold tracking-wide text-ink-500 uppercase dark:text-ink-400">Preview</p>
                    <div class="mt-3 flex items-center gap-3">
                        <span class="grid size-10 shrink-0 place-items-center rounded-xl bg-sun-50 text-sun-700 ring-1 ring-sun-600/20 ring-inset dark:bg-sun-400/10 dark:text-sun-300 dark:ring-sun-400/20">
                            <x-icon name="sparkles" class="size-5" />
                        </span>
                        <p class="text-sm text-ink-700 dark:text-ink-200">
                            A learner with <span class="font-semibold tabular-nums">{{ number_format($samplePoints) }} XP</span> is
                            <span class="font-semibold" data-setup-preview-level>Level {{ $sampleLevel }}</span>.
                        </p>
                    </div>
                    <p class="mt-3 text-xs text-ink-500 dark:text-ink-400">
                        The highest level is reached at <span class="font-semibold tabular-nums" data-setup-preview-top>{{ number_format($topLevelPoints) }} XP</span>.
                    </p>
                </div>
            </x-slot:aside>
        </x-form.section>

        <x-form.section id="parent-forum" title="Parent forum" description="A moderated space where parents and teachers talk about healthy habits.">
            <div class="flex flex-col gap-5">
                <x-form.toggle
                    name="forum_enabled"
                    label="Turn on the parent forum"
                    description="When off, teachers and parents can't open the forum. Administrators can still moderate it."
                    :checked="(bool) $settings['forum_enabled']"
                    unchecked-value="0"
                />
                <x-form.toggle
                    name="forum_requires_approval"
                    label="Approve new threads before they appear"
                    description="New threads from parents and teachers wait in the moderation queue until an administrator approves them."
                    :checked="(bool) $settings['forum_requires_approval']"
                    unchecked-value="0"
                />
            </div>
        </x-form.section>

        <x-form.section id="announcement" title="Announcement" description="A short banner at the top of every portal page. Leave it empty to hide the banner.">
            <x-form.textarea
                name="announcement"
                label="Announcement"
                :value="$settings['announcement']"
                :max-length="500"
                rows="3"
                optional
                placeholder="Healthy Baon Week runs from November 9 to 20!"
                hint="Each person can dismiss it; a changed announcement shows again."
            />
        </x-form.section>

        <x-form.actions submit="Save settings">
            <x-icon name="info" class="size-4" />
            Changes apply right away to every portal.
        </x-form.actions>
    </form>
</x-layouts.app>
