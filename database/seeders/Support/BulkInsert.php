<?php

namespace Database\Seeders\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Inserts large sets of demo rows with a few multi-row statements instead of one query per record.
 *
 * Rows are written as given: no casts, mutators or automatic timestamps apply, so every row must carry raw column
 * values (enum values, JSON strings, formatted datetimes) and its own created_at and updated_at.
 */
final class BulkInsert
{
    /**
     * The number of rows written by one INSERT statement (well below the bound-parameter limits of SQLite and MySQL).
     */
    private const ROWS_PER_STATEMENT = 400;

    /**
     * Insert rows into the table of the given model.
     *
     * @param  class-string<Model>  $model
     * @param  list<array<string, mixed>>  $rows
     */
    public static function models(string $model, array $rows): void
    {
        foreach (array_chunk($rows, self::ROWS_PER_STATEMENT) as $chunk) {
            $model::query()->insert($chunk);
        }
    }

    /**
     * Insert rows into the pivot table of the given relationship.
     *
     * @param  BelongsToMany<Model, Model>  $relation
     * @param  list<array<string, mixed>>  $rows
     */
    public static function pivot(BelongsToMany $relation, array $rows): void
    {
        foreach (array_chunk($rows, self::ROWS_PER_STATEMENT) as $chunk) {
            $relation->newPivotStatement()->insert($chunk);
        }
    }
}
