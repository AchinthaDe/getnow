<?php

declare(strict_types=1);

use App\Domain\Content\Models\Menu;
use App\Models\User;

it('allows platform admin on viewAny, view, update', function () {
    $admin = User::factory()->platformAdmin()->create();
    $menu = Menu::firstOrFail();

    expect($admin->can('viewAny', Menu::class))->toBeTrue()
        ->and($admin->can('view', $menu))->toBeTrue()
        ->and($admin->can('update', $menu))->toBeTrue();
});

it('denies non-admin on all abilities', function () {
    $user = User::factory()->create(['is_platform_admin' => false]);
    $menu = Menu::firstOrFail();

    expect($user->can('viewAny', Menu::class))->toBeFalse()
        ->and($user->can('view', $menu))->toBeFalse()
        ->and($user->can('update', $menu))->toBeFalse()
        ->and($user->can('create', Menu::class))->toBeFalse()
        ->and($user->can('delete', $menu))->toBeFalse()
        ->and($user->can('deleteAny', Menu::class))->toBeFalse()
        ->and($user->can('forceDelete', $menu))->toBeFalse();
});

it('denies create, delete, deleteAny, forceDelete for platform admin', function () {
    $admin = User::factory()->platformAdmin()->create();
    $menu = Menu::firstOrFail();

    expect($admin->can('create', Menu::class))->toBeFalse()
        ->and($admin->can('delete', $menu))->toBeFalse()
        ->and($admin->can('deleteAny', Menu::class))->toBeFalse()
        ->and($admin->can('restore', $menu))->toBeFalse()
        ->and($admin->can('restoreAny', Menu::class))->toBeFalse()
        ->and($admin->can('forceDelete', $menu))->toBeFalse()
        ->and($admin->can('forceDeleteAny', Menu::class))->toBeFalse();
});
