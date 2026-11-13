@php
    $descriptions = [
        \App\Enums\UserRole::Admin->value => 'Manages the school setup, people, game content and reports.',
        \App\Enums\UserRole::Teacher->value => 'Runs classrooms, quizzes and lesson plans in the subjects you assign.',
        \App\Enums\UserRole::Parent->value => 'Follows linked learners’ progress and joins the parent forum.',
    ];
    $roleCards = collect($roles)->map(fn (\App\Enums\UserRole $option): array => [
        'value' => $option->value,
        'label' => $option->label(),
        'description' => $descriptions[$option->value],
        'icon' => $option->icon(),
        'color' => $option->color(),
    ])->all();
    $cancelUrl = match ($role) {
        \App\Enums\UserRole::Teacher => route('admin.teachers.index'),
        \App\Enums\UserRole::Parent => route('admin.parents.index'),
        default => route('admin.users.index'),
    };
@endphp

<x-layouts.app title="New user">
    <x-page-header
        title="New user"
        description="Create an administrator, teacher or parent account. They sign in with their email address."
        :breadcrumbs="[['label' => 'All users', 'url' => route('admin.users.index')], ['label' => 'New user']]"
    />

    <form method="POST" action="{{ route('admin.users.store') }}" class="flex flex-col gap-8" data-submit-once>
        @csrf

        <x-form.section title="Account type" description="What the person can do in NutriMind. The type cannot be changed later.">
            <x-form.radio-cards name="role" label="Account type" :options="$roleCards" :value="$role?->value" columns="3" required class="col-span-full" />
            <p class="col-span-full flex items-center gap-2 text-sm text-ink-500 dark:text-ink-400">
                <x-icon name="backpack" class="size-4 shrink-0" />
                <span>Adding a learner? Use the <a href="{{ route('admin.students.create') }}" class="font-semibold text-leaf-700 underline underline-offset-2 dark:text-leaf-300">learner form</a> instead.</span>
            </p>
        </x-form.section>

        <x-form.section title="Profile" description="Shown to other staff, in reports and on the parent forum." columns="2">
            <x-form.input name="name" label="Full name" autocomplete="name" maxlength="255" required class="col-span-full" />
            <x-form.input name="email" type="email" label="Email address" autocomplete="email" prefix-icon="mail" maxlength="255" hint="They sign in with this address." required />
            <x-form.input name="phone" type="tel" label="Mobile number" autocomplete="tel" inputmode="tel" prefix-icon="phone" placeholder="09XX XXX XXXX" maxlength="30" optional />
            <x-form.input name="username" label="Username" autocomplete="off" maxlength="30" hint="Letters, numbers, dashes and underscores." optional />
        </x-form.section>

        <x-form.section title="Password" description="Leave both fields empty to generate a password. A generated password is shown to you once, ready to hand over." columns="2">
            <x-form.password name="password" label="Password" autocomplete="new-password" hint="Optional. At least 8 characters with letters and numbers." />
            <x-form.password name="password_confirmation" label="Confirm password" autocomplete="new-password" />
        </x-form.section>

        <x-form.actions :cancel="$cancelUrl" submit="Create account" submit-icon="user-plus">
            <x-icon name="info" class="size-4" />
            New accounts are active right away.
        </x-form.actions>
    </form>
</x-layouts.app>
