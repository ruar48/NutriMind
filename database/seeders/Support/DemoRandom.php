<?php

namespace Database\Seeders\Support;

use Random\Engine\Mt19937;
use Random\Randomizer;

/**
 * A seeded random number generator for the demo data.
 *
 * Every named stream starts from the same seed on every run, so the demo school is identical each time it is
 * generated. Streams are independent of each other and of PHP's global generator: changing what one seeder
 * draws never shifts the data another seeder generates.
 */
final class DemoRandom
{
    /**
     * The seed of the demo data; every stream is derived from it.
     */
    public const SEED = 2026;

    /**
     * The seeded randomizer of this stream.
     */
    private readonly Randomizer $randomizer;

    /**
     * Create a stream from a seed.
     */
    private function __construct(int $seed)
    {
        $this->randomizer = new Randomizer(new Mt19937($seed));
    }

    /**
     * Create the stream with the given name.
     */
    public static function stream(string $name): self
    {
        return new self(self::SEED ^ crc32($name));
    }

    /**
     * Get a random integer between the bounds (inclusive).
     */
    public function int(int $min, int $max): int
    {
        return $this->randomizer->getInt($min, $max);
    }

    /**
     * Get a random float in the interval [0, 1).
     */
    public function float(): float
    {
        return $this->randomizer->nextFloat();
    }

    /**
     * Get a random float between the bounds.
     */
    public function between(float $min, float $max): float
    {
        return $min + ($max - $min) * $this->randomizer->nextFloat();
    }

    /**
     * Determine whether an event with the given probability happens.
     */
    public function chance(float $probability): bool
    {
        return $this->randomizer->nextFloat() < $probability;
    }

    /**
     * Pick one item of a non-empty array.
     *
     * @template TValue
     *
     * @param  array<array-key, TValue>  $items
     * @return TValue
     */
    public function pick(array $items): mixed
    {
        return $items[$this->randomizer->pickArrayKeys($items, 1)[0]];
    }

    /**
     * Pick a key of a non-empty array with a probability proportional to its weight.
     *
     * @template TKey of array-key
     *
     * @param  array<TKey, int|float>  $weights
     * @return TKey
     */
    public function weighted(array $weights): int|string
    {
        $target = $this->float() * array_sum($weights);

        foreach ($weights as $key => $weight) {
            $target -= $weight;

            if ($target < 0) {
                return $key;
            }
        }

        return array_key_last($weights);
    }

    /**
     * Shuffle the items into a new list.
     *
     * @template TValue
     *
     * @param  array<array-key, TValue>  $items
     * @return list<TValue>
     */
    public function shuffle(array $items): array
    {
        return $this->randomizer->shuffleArray(array_values($items));
    }

    /**
     * Get a number drawn from a normal distribution (Box–Muller transform).
     */
    public function normal(float $mean, float $deviation): float
    {
        $radius = sqrt(-2.0 * log(1.0 - $this->float()));

        return $mean + $deviation * $radius * cos(2.0 * M_PI * $this->float());
    }
}
