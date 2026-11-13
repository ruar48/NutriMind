<?php

namespace App\Services\Content;

use BackedEnum;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use ReflectionEnum;

/**
 * Reads the GET filters of the content lists. Unknown or malformed values are ignored (the list stays
 * unfiltered) instead of failing the page.
 */
final class ListFilter
{
    /**
     * The longest search term a list accepts.
     */
    public const int SEARCH_MAX_LENGTH = 100;

    /**
     * Get a trimmed search term, or null when the parameter is blank or not a string.
     */
    public static function search(Request $request, string $key = 'search'): ?string
    {
        $value = $request->query($key);

        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        return Str::limit(trim($value), self::SEARCH_MAX_LENGTH, '');
    }

    /**
     * Get a positive whole-number identifier, or null.
     */
    public static function id(Request $request, string $key): ?int
    {
        $value = $request->query($key);

        if (! is_string($value) || ! ctype_digit($value) || (int) $value < 1) {
            return null;
        }

        return (int) $value;
    }

    /**
     * Get the value when it is one of the allowed strings, or null.
     *
     * @param  list<string>  $allowed
     */
    public static function choice(Request $request, string $key, array $allowed): ?string
    {
        $value = $request->query($key);

        return is_string($value) && in_array($value, $allowed, true) ? $value : null;
    }

    /**
     * Get the enum case named by the parameter's value, or null.
     *
     * @template TEnum of BackedEnum
     *
     * @param  class-string<TEnum>  $enum
     * @return TEnum|null
     */
    public static function enum(Request $request, string $key, string $enum): ?BackedEnum
    {
        $value = $request->query($key);

        if (! is_string($value) || $value === '') {
            return null;
        }

        $backing = (string) (new ReflectionEnum($enum))->getBackingType();

        if ($backing === 'int') {
            return ctype_digit($value) ? $enum::tryFrom((int) $value) : null;
        }

        return $enum::tryFrom($value);
    }
}
