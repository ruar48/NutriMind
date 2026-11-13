<?php

namespace App\Enums\Concerns;

trait HasOptions
{
    /**
     * Get the human-readable label of the case.
     */
    abstract public function label(): string;

    /**
     * Get every case as a select-friendly map of value to label, in declaration order.
     *
     * @return array<int|string, string>
     */
    public static function options(): array
    {
        $options = [];

        foreach (self::cases() as $case) {
            $options[$case->value] = $case->label();
        }

        return $options;
    }
}
