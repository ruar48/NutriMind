@props([
    'categories',
    'active' => null,
])

@php
    $carried = collect(request()->only(['search', 'show']))->filter(fn ($value) => is_string($value) && $value !== '')->all();
@endphp

<x-tabs label="Forum categories" {{ $attributes }}>
    <x-tab :href="route('forum.index', $carried)" :active="$active === null" icon="layout-grid" :count="$categories->sum('threads_count')">All topics</x-tab>
    @foreach ($categories as $category)
        <x-tab :href="route('forum.categories.show', ['category' => $category, ...$carried])" :active="$active?->is($category) ?? false" :icon="$category->icon" :count="$category->threads_count" data-forum-category-tab="{{ $category->slug }}">
            {{ $category->name }}
            @unless ($category->is_active)
                <x-badge color="slate" size="sm">Inactive</x-badge>
            @endunless
        </x-tab>
    @endforeach
</x-tabs>
