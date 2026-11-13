<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Relations\Pivot;

#[Table(incrementing: true)]
class ClassroomStudent extends Pivot
{
    /**
     * Bootstrap the model's event listeners.
     */
    protected static function booted(): void
    {
        static::creating(function (ClassroomStudent $enrollment): void {
            $enrollment->enrolled_at ??= now();
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
            'enrolled_at' => 'datetime',
        ];
    }
}
