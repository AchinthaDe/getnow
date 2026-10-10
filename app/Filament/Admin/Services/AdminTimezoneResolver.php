<?php

declare(strict_types=1);

namespace App\Filament\Admin\Services;

use Illuminate\Support\Facades\Log;

final class AdminTimezoneResolver
{
    private bool $hasLogged = false;

    public function resolve(?string $timezone): string
    {
        if (empty($timezone)) {
            $timezone = 'UTC';
        }

        if (! in_array($timezone, timezone_identifiers_list(), true)) {
            if (! $this->hasLogged) {
                Log::warning("Invalid ADMIN_TIMEZONE '{$timezone}' provided. Falling back to UTC.");
                $this->hasLogged = true;
            }

            return 'UTC';
        }

        return $timezone;
    }

    /**
     * Resets the logged state. Useful for testing.
     */
    public function resetLoggedState(): void
    {
        $this->hasLogged = false;
    }
}
