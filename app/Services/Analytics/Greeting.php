<?php

namespace App\Services\Analytics;

use Carbon\CarbonInterface;

/**
 * The time-of-day greeting of the dashboard heroes.
 */
final class Greeting
{
    /**
     * Greet the person by name for the given moment (now in the app timezone by default): "Good morning" before
     * noon, "Good afternoon" until 6 PM, "Good evening" after.
     */
    public static function for(string $name, ?CarbonInterface $at = null): string
    {
        $hour = ($at ?? now())->hour;

        $salutation = match (true) {
            $hour < 12 => 'Good morning',
            $hour < 18 => 'Good afternoon',
            default => 'Good evening',
        };

        return trim($name) === '' ? $salutation : $salutation.', '.trim($name);
    }
}
