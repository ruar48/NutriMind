@php
    $isEditing = $reward->exists;
@endphp

<x-layouts.app :title="$isEditing ? 'Edit '.$reward->name : 'New reward'">
    <x-page-header
        eyebrow="Game content"
        :title="$isEditing ? 'Edit reward' : 'New reward'"
        :description="$isEditing ? trans_choice('{0} No learner has earned it yet.|{1} Earned by :count learner.|[2,*] Earned by :count learners.', $reward->students_count) : 'Rewards celebrate milestones: a badge, a trophy or an item for the learner’s avatar.'"
        :breadcrumbs="[
            ['label' => 'Rewards', 'url' => route('admin.rewards.index')],
            ['label' => $isEditing ? $reward->name : 'New reward'],
        ]"
    >
        @if ($isEditing)
            <x-slot:leading>
                <x-reward-badge :reward="$reward" size="sm" />
            </x-slot:leading>
        @endif
    </x-page-header>

    <form method="POST" action="{{ $isEditing ? route('admin.rewards.update', $reward) : route('admin.rewards.store') }}" class="flex flex-col gap-8" data-submit-once data-animate style="--stagger: 1">
        @csrf
        @if ($isEditing)
            @method('PUT')
        @endif

        <x-form.section title="Reward" description="Learners see the name and description on their trophy shelf.">
            <x-form.input name="name" label="Name" :value="$reward->name" maxlength="100" placeholder="e.g. Veggie Champion" required />
            <x-form.radio-cards name="type" label="Reward type" :options="\App\Enums\RewardType::cases()" :value="$reward->type" columns="3" required />
            <x-form.textarea name="description" label="Description" :value="$reward->description" :max-length="1000" rows="3" optional />
        </x-form.section>

        <x-form.section title="Look" description="The icon and color of the badge in the game.">
            <x-form.icon-picker name="icon" label="Icon" :icons="$icons" :value="$reward->icon" required />
            <x-form.color-picker name="color" label="Color" :colors="\App\Services\Content\RewardAppearance::COLORS" :value="$reward->color" required />
        </x-form.section>

        <x-form.section title="How to earn it" description="Describe the goal in words learners understand. Add a points threshold for rewards earned by XP." columns="2">
            <x-form.input name="criteria" label="How to earn it" :value="$reward->criteria" maxlength="255" prefix-icon="target" placeholder="e.g. Complete every mission in Veggie Valley." optional class="col-span-full" />
            <x-form.input name="points_required" type="number" label="Points required" :value="$reward->points_required" min="1" max="100000" suffix="XP" hint="Leave empty when the reward is not earned by points." optional />
            <div class="col-span-full">
                <x-form.toggle name="is_active" label="Active" description="Learners can still earn the reward. Turn this off to retire it; learners keep the ones they earned." :checked="(bool) $reward->is_active" unchecked-value="0" />
            </div>
        </x-form.section>

        <x-form.actions
            :cancel="route('admin.rewards.index')"
            :submit="$isEditing ? 'Save changes' : 'Create reward'"
            :submit-icon="$isEditing ? 'check' : 'plus'"
        />
    </form>
</x-layouts.app>
