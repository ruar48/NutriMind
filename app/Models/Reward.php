<?php

namespace App\Models;

use App\Enums\RewardType;
use Database\Factories\RewardFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable(['name', 'description', 'type', 'icon', 'color', 'criteria', 'points_required', 'is_active'])]
class Reward extends Model
{
    /** @use HasFactory<RewardFactory> */
    use HasFactory;

    /**
     * The model's default values for attributes, mirroring the database defaults.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'type' => 'badge',
        'icon' => 'award',
        'color' => 'sun',
        'is_active' => true,
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => RewardType::class,
            'points_required' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Get the learners who earned the reward.
     *
     * @return BelongsToMany<User, $this, RewardStudent>
     */
    public function students(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'reward_student', 'reward_id', 'student_id')
            ->using(RewardStudent::class)
            ->withPivot('awarded_at')
            ->withTimestamps();
    }

    /**
     * Scope the query to active rewards.
     *
     * @param  Builder<Reward>  $query
     */
    #[Scope]
    protected function active(Builder $query): void
    {
        $query->where($query->qualifyColumn('is_active'), true);
    }
}
