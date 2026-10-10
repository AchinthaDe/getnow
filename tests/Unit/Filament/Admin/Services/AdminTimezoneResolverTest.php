<?php

declare(strict_types=1);

use App\Filament\Admin\Services\AdminTimezoneResolver;
use Illuminate\Support\Facades\Log;

beforeEach(function () {
    app(AdminTimezoneResolver::class)->resetLoggedState();
});

it('resolves a valid timezone', function () {
    expect(app(AdminTimezoneResolver::class)->resolve('Asia/Colombo'))->toBe('Asia/Colombo');
});

it('falls back to UTC for empty or null timezones without logging', function () {
    Log::shouldReceive('warning')->never();

    expect(app(AdminTimezoneResolver::class)->resolve(''))->toBe('UTC');
    expect(app(AdminTimezoneResolver::class)->resolve(null))->toBe('UTC');
});

it('falls back to UTC for invalid timezones and logs a warning only once', function () {
    Log::shouldReceive('warning')
        ->once()
        ->with("Invalid ADMIN_TIMEZONE 'Invalid/Zone' provided. Falling back to UTC.");

    expect(app(AdminTimezoneResolver::class)->resolve('Invalid/Zone'))->toBe('UTC');

    // Second call should not log again
    expect(app(AdminTimezoneResolver::class)->resolve('Invalid/Zone'))->toBe('UTC');
});
