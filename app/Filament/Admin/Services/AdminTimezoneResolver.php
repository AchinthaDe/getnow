<?php

declare(strict_types=1);

namespace App\Filament\Admin\Services;

use Illuminate\Support\Facades\Log;

final class AdminTimezoneResolver
{
    private static bool $hasLogged = false;

    public static function resolve(?string $timezone): string
    {
        if (empty($timezone)) {
            $timezone = 'UTC';
        }

        if (! in_array($timezone, timezone_identifiers_list(), true)) {
            if (! self::$hasLogged) {
                Log::warning("Invalid ADMIN_TIMEZONE '{$timezone}' provided. Falling back to UTC.");
                self::$hasLogged = true;
            }

            return 'UTC';
        }

        return $timezone;
    }

    /**
     * Resets the logged state. Useful for testing.
     */
    public static function resetLoggedState(): void
    {
        self::$hasLogged = false;
    }
}
