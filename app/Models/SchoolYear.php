<?php

namespace App\Models;

use Database\Factories\SchoolYearFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'starts_on', 'ends_on', 'is_active'])]
class SchoolYear extends Model
{
    /** @use HasFactory<SchoolYearFactory> */
    use HasFactory;

    /**
     * The model's default values for attributes, mirroring the database defaults.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_active' => false,
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'starts_on' => 'date',
            'ends_on' => 'date',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Get the academic terms of the school year in sequence order, each linked back to this school year.
     *
     * @return HasMany<AcademicTerm, $this>
     */
    public function terms(): HasMany
    {
        return $this->hasMany(AcademicTerm::class)->orderBy('sequence')->orderBy('id')->chaperone('schoolYear');
    }

    /**
     * Get the classrooms opened in the school year.
     *
     * @return HasMany<Classroom, $this>
     */
    public function classrooms(): HasMany
    {
        return $this->hasMany(Classroom::class);
    }

    /**
     * Get the active school year, or null when none is active.
     */
    public static function current(): ?self
    {
        return static::query()->active()->orderByDesc('starts_on')->orderByDesc('id')->first();
    }

    /**
     * Scope the query to the active school year.
     *
     * @param  Builder<SchoolYear>  $query
     */
    #[Scope]
    protected function active(Builder $query): void
    {
        $query->where($query->qualifyColumn('is_active'), true);
    }
}
