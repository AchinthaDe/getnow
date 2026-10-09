<?php

declare(strict_types=1);

use App\Filament\Admin\Services\AdminTimezoneResolver;
use Illuminate\Support\Facades\Log;

beforeEach(function () {
    AdminTimezoneResolver::resetLoggedState();
});

it('resolves a valid timezone', function () {
    expect(AdminTimezoneResolver::resolve('Asia/Colombo'))->toBe('Asia/Colombo');
});

it('falls back to UTC for empty or null timezones without logging', function () {
    Log::shouldReceive('warning')->never();

    expect(AdminTimezoneResolver::resolve(''))->toBe('UTC');
    expect(AdminTimezoneResolver::resolve(null))->toBe('UTC');
});

it('falls back to UTC for invalid timezones and logs a warning only once', function () {
    Log::shouldReceive('warning')
        ->once()
        ->with("Invalid ADMIN_TIMEZONE 'Invalid/Zone' provided. Falling back to UTC.");

    expect(AdminTimezoneResolver::resolve('Invalid/Zone'))->toBe('UTC');

    // Second call should not log again
    expect(AdminTimezoneResolver::resolve('Invalid/Zone'))->toBe('UTC');
});
