<?php

namespace App\Http\Requests\Admin\Concerns;

use App\Models\User;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Shared normalization and uniqueness rules for the sign-in identifiers of accounts (email address and username).
 */
trait ValidatesAccountIdentifiers
{
    /**
     * Build a rule that fails when another account already uses the value, compared case-insensitively.
     *
     * SQLite compares text case-sensitively while MySQL's default collation does not, so a plain unique rule would
     * let "JSantos" through next to "jsantos" locally and then fail on the hosting database's unique index.
     */
    protected function uniqueAccountIdentifier(string $column, ?User $ignore = null): Closure
    {
        if (! in_array($column, ['email', 'username'], true)) {
            throw new InvalidArgumentException("[{$column}] is not an account identifier column.");
        }

        return function (string $attribute, mixed $value, Closure $fail) use ($column, $ignore): void {
            if (! is_string($value) || $value === '') {
                return;
            }

            $isTaken = User::query()
                ->whereRaw("LOWER({$column}) = ?", [Str::lower($value)])
                ->when($ignore, fn (Builder $query, User $user): Builder => $query->whereKeyNot($user->getKey()))
                ->exists();

            if ($isTaken) {
                $fail('validation.unique')->translate();
            }
        };
    }

    /**
     * Squish the whitespace of a free-text name field before validation (tampered non-string input is left alone).
     */
    protected function squishInput(string $key): void
    {
        $value = $this->input($key);

        if (is_string($value)) {
            $this->merge([$key => Str::squish($value)]);
        }
    }
}
