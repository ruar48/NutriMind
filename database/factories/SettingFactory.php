<?php

namespace Database\Factories;

use App\Models\Setting;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Setting>
 */
class SettingFactory extends Factory
{
    /**
     * Define the model's default state: a uniquely keyed setting holding a string.
     *
     * Application code reads and writes settings through App\Services\Settings, which also keeps its cache current.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'key' => Str::snake(fake()->unique()->words(3, true)),
            'value' => fake()->word(),
        ];
    }
}
