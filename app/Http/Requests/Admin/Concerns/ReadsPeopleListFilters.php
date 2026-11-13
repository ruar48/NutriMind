<?php

namespace App\Http\Requests\Admin\Concerns;

use Illuminate\Support\Str;

/**
 * Reads the GET filters of the admin people lists leniently: a missing, blank, tampered (e.g. an array) or unknown
 * value is ignored, so a list page always renders instead of bouncing back with validation errors.
 */
trait ReadsPeopleListFilters
{
    /**
     * Get the search term: trimmed, inner whitespace squished, cut to 100 characters, or null when blank.
     */
    public function search(): ?string
    {
        $value = $this->query('search');

        if (! is_string($value)) {
            return null;
        }

        $term = Str::substr(Str::squish($value), 0, 100);

        return $term === '' ? null : $term;
    }

    /**
     * Get the account status filter: "active", "inactive" or null.
     */
    public function status(): ?string
    {
        return $this->queryChoice('status', ['active', 'inactive']);
    }

    /**
     * Get the sort direction ("asc" or "desc"), falling back to the given default.
     */
    public function direction(string $default = 'asc'): string
    {
        return $this->queryChoice('direction', ['asc', 'desc']) ?? $default;
    }

    /**
     * Get a query value when it is one of the allowed strings.
     *
     * @param  list<string>  $choices
     */
    protected function queryChoice(string $key, array $choices): ?string
    {
        $value = $this->query($key);

        return is_string($value) && in_array($value, $choices, true) ? $value : null;
    }

    /**
     * Get a query value as a positive integer, or null when it is not one.
     */
    protected function queryPositiveInteger(string $key): ?int
    {
        $value = $this->query($key);

        if (! is_string($value) || preg_match('/^[1-9]\d{0,9}$/', $value) !== 1) {
            return null;
        }

        return (int) $value;
    }
}
