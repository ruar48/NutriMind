<?php

namespace App\Services\Forum;

use App\Models\ForumCategory;
use Illuminate\Support\Str;

/**
 * Creates and renames forum categories, deriving each category's unique URL slug from its name.
 */
class ForumCategories
{
    /**
     * The palette keys a category may use.
     *
     * @var list<string>
     */
    public const COLORS = ['leaf', 'carrot', 'sun', 'berry', 'grape', 'sky', 'teal', 'lime', 'pink', 'indigo', 'slate'];

    /**
     * The slug used when a name has no letters or digits that survive transliteration.
     */
    public const SLUG_FALLBACK = 'category';

    /**
     * The message shown when a category that still has threads would be deleted.
     */
    public const HAS_THREADS_MESSAGE = 'This category still has threads. Deactivate it instead.';

    /**
     * Create a category.
     *
     * @param  array{name: string, description?: string|null, color: string, icon: string, sort_order: int|string, is_active: bool|int|string}  $attributes
     */
    public function create(array $attributes): ForumCategory
    {
        return ForumCategory::query()->create([
            ...$this->fields($attributes),
            'slug' => $this->uniqueSlug($attributes['name']),
        ]);
    }

    /**
     * Update a category; a new name gives it a new slug.
     *
     * @param  array{name: string, description?: string|null, color: string, icon: string, sort_order: int|string, is_active: bool|int|string}  $attributes
     */
    public function update(ForumCategory $category, array $attributes): void
    {
        $category->fill($this->fields($attributes));

        if ($category->isDirty('name')) {
            $category->slug = $this->uniqueSlug($attributes['name'], $category);
        }

        $category->save();
    }

    /**
     * Get a slug for the name that no other category uses ("healthy-recipes", then "healthy-recipes-2", …).
     */
    public function uniqueSlug(string $name, ?ForumCategory $ignore = null): string
    {
        $base = Str::slug($name);
        $base = $base === '' ? self::SLUG_FALLBACK : $base;
        $taken = ForumCategory::query()
            ->when($ignore, fn ($query, ForumCategory $ignore) => $query->whereKeyNot($ignore->getKey()))
            ->where(fn ($query) => $query->where('slug', $base)->orWhereLike('slug', $base.'-%'))
            ->pluck('slug')
            ->all();

        $slug = $base;

        for ($suffix = 2; in_array($slug, $taken, true); $suffix++) {
            $slug = $base.'-'.$suffix;
        }

        return $slug;
    }

    /**
     * Pick the editable fields from validated input.
     *
     * @param  array<string, mixed>  $attributes
     * @return array{name: mixed, description: mixed, color: mixed, icon: mixed, sort_order: mixed, is_active: mixed}
     */
    private function fields(array $attributes): array
    {
        return [
            'name' => $attributes['name'],
            'description' => $attributes['description'] ?? null,
            'color' => $attributes['color'],
            'icon' => $attributes['icon'],
            'sort_order' => $attributes['sort_order'],
            'is_active' => $attributes['is_active'],
        ];
    }
}
