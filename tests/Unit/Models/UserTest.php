<?php

declare(strict_types=1);

use App\Models\User;
use Filament\Panel;

it('returns true for isAdmin when is_platform_admin is true', function () {
    $user = new User;
    $user->is_platform_admin = true;
    expect($user->isAdmin())->toBeTrue();
});

it('returns false for isAdmin when is_platform_admin is false', function () {
    $user = new User;
    $user->is_platform_admin = false;
    expect($user->isAdmin())->toBeFalse();
});

it('delegates canAccessPanel to isAdmin', function () {
    /** @var Panel $panel */
    $panel = Mockery::mock(Panel::class);

    $admin = new User;
    $admin->is_platform_admin = true;
    expect($admin->canAccessPanel($panel))->toBeTrue();

    $user = new User;
    $user->is_platform_admin = false;
    expect($user->canAccessPanel($panel))->toBeFalse();
});
