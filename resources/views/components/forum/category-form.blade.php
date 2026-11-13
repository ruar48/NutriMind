@props([
    'category',
    'action',
    'method' => 'POST',
    'cancel',
    'submit',
    'submitIcon' => 'check',
])

<form method="POST" action="{{ $action }}" {{ $attributes->class('flex flex-col gap-8') }} data-submit-once data-animate style="--stagger: 1">
    @csrf
    @if (strtoupper($method) !== 'POST')
        @method($method)
    @endif

    <x-form.section title="Details" description="Teachers and parents see the name and description on the forum home page.">
        <x-form.input name="name" label="Name" :value="$category->name" maxlength="60" placeholder="e.g. Healthy Recipes"
            :hint="$category->exists ? 'Renaming the category also changes its link.' : 'Up to 60 characters. The link is made from the name.'" required />
        <x-form.textarea name="description" label="Description" :value="$category->description" :max-length="200" rows="3" optional
            placeholder="What belongs in this category?" />
    </x-form.section>

    <x-form.section title="Look" description="The color and icon mark the category in tabs, thread lists and thread pages.">
        <x-form.color-picker name="color" :value="$category->color" required />
        <x-form.icon-picker name="icon" :value="$category->icon" required />
    </x-form.section>

    <x-form.section title="Visibility" description="Lower numbers come first in the category tabs.">
        <x-form.input name="sort_order" type="number" label="Display order" :value="$category->sort_order" min="0" max="9999" step="1" required />
        <x-form.toggle name="is_active" label="Active" :checked="(bool) $category->is_active" unchecked-value="0"
            description="Inactive categories are hidden from teachers and parents, and nobody can start threads in them. Their threads are kept." />
    </x-form.section>

    <x-form.actions :cancel="$cancel" :submit="$submit" :submit-icon="$submitIcon" />
</form>
