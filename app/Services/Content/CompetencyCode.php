<?php

namespace App\Services\Content;

use Illuminate\Support\Str;

/**
 * DepEd learning-competency codes (e.g. "H5N-IIa-1") as stored and compared by the content area.
 *
 * Codes are saved trimmed and uppercased, and always compared case-insensitively, so codes typed in either
 * case (or saved before normalization) still match.
 */
final class CompetencyCode
{
    /**
     * The longest code the database column holds.
     */
    public const int MAX_LENGTH = 40;

    /**
     * Normalize a submitted code: squish the whitespace and uppercase it; a blank code becomes null.
     *
     * Values that are not strings are returned unchanged so validation can reject them.
     */
    public static function normalize(mixed $code): mixed
    {
        if (! is_string($code)) {
            return $code;
        }

        $normalized = Str::upper(Str::squish($code));

        return $normalized === '' ? null : $normalized;
    }

    /**
     * Get the key two codes are compared by (trimmed and uppercased, like the SQL expression
     * `UPPER(TRIM(competency_code))`), or null for a blank code.
     */
    public static function key(?string $code): ?string
    {
        $key = Str::upper(trim((string) $code));

        return $key === '' ? null : $key;
    }

    /**
     * Determine whether two codes name the same competency.
     */
    public static function matches(?string $first, ?string $second): bool
    {
        $firstKey = self::key($first);

        return $firstKey !== null && $firstKey === self::key($second);
    }
}
