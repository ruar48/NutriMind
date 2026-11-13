<?php

namespace Database\Seeders\Support;

use Database\Seeders\Support\Catalog\PeopleCatalog;
use Illuminate\Support\Facades\Hash;

/**
 * The hash of the shared demo password, computed once and reused for every demo account.
 */
final class DemoPassword
{
    /**
     * The hash of the demo password, once computed.
     */
    private static ?string $hash = null;

    /**
     * Get the hash of the demo password.
     */
    public static function hash(): string
    {
        return self::$hash ??= Hash::make(PeopleCatalog::PASSWORD);
    }
}
