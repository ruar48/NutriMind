<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Container\Attributes\Scoped;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Administrator-editable system settings.
 *
 * Values saved in the "settings" table override the defaults in config('nutrimind.settings').
 * Values are stored as JSON, so booleans, integers, arrays and null keep their type. The stored
 * values are cached forever under one key that every write clears, and one instance serves the
 * whole request (or queued job) lifecycle.
 */
#[Scoped]
class Settings
{
    /**
     * The cache key that holds the stored setting values.
     */
    public const CACHE_KEY = 'nutrimind.settings';

    /**
     * The defaults merged with the stored values, resolved once per instance.
     *
     * @var array<string, mixed>|null
     */
    private ?array $resolved = null;

    /**
     * Get a setting value, or the given default when the key is neither stored nor configured.
     */
    public function get(string $key, mixed $default = null): mixed
    {
        $settings = $this->all();

        return array_key_exists($key, $settings) ? $settings[$key] : $default;
    }

    /**
     * Store a setting value.
     */
    public function set(string $key, mixed $value): void
    {
        $this->setMany([$key => $value]);
    }

    /**
     * Store several setting values at once.
     *
     * @param  array<string, mixed>  $values
     */
    public function setMany(array $values): void
    {
        if ($values === []) {
            return;
        }

        DB::transaction(function () use ($values): void {
            foreach ($values as $key => $value) {
                Setting::query()->updateOrCreate(['key' => $key], ['value' => $value]);
            }
        });

        Cache::forget(self::CACHE_KEY);

        $this->resolved = null;
    }

    /**
     * Get every setting: the configured defaults overridden by the stored values.
     *
     * @return array<string, mixed>
     */
    public function all(): array
    {
        return $this->resolved ??= array_replace(config('nutrimind.settings', []), $this->stored());
    }

    /**
     * Get the stored setting values keyed by setting key.
     *
     * @return array<string, mixed>
     */
    private function stored(): array
    {
        return Cache::rememberForever(
            self::CACHE_KEY,
            fn (): array => Setting::query()->pluck('value', 'key')->all(),
        );
    }
}
