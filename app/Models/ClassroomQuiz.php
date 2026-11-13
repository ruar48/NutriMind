<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Relations\Pivot;

#[Table(incrementing: true)]
class ClassroomQuiz extends Pivot
{
    /**
     * Bootstrap the model's event listeners.
     */
    protected static function booted(): void
    {
        static::creating(function (ClassroomQuiz $assignment): void {
            $assignment->assigned_at ??= now();
        });
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'available_from' => 'datetime',
            'due_at' => 'datetime',
            'assigned_at' => 'datetime',
        ];
    }
}
