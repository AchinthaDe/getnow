<?php

declare(strict_types=1);

use App\Domain\Catalog\Models\Brand;
use Database\Factories\BrandFactory;
use Database\Factories\UserFactory;

it('authorizes platform admin to access brand resource and forbids non-admin users', function (): void {
    $admin = UserFactory::new()->platformAdmin()->create();
    $nonAdmin = UserFactory::new()->create(['is_platform_admin' => false]);
    $brand = BrandFactory::new()->create();

    // 1. Direct policy assertions
    expect($admin->can('viewAny', Brand::class))->toBeTrue()
        ->and($admin->can('view', $brand))->toBeTrue()
        ->and($admin->can('create', Brand::class))->toBeTrue()
        ->and($admin->can('update', $brand))->toBeTrue()
        ->and($admin->can('delete', $brand))->toBeFalse()
        ->and($admin->can('deleteAny', Brand::class))->toBeFalse()
        ->and($nonAdmin->can('viewAny', Brand::class))->toBeFalse()
        ->and($nonAdmin->can('view', $brand))->toBeFalse()
        ->and($nonAdmin->can('create', Brand::class))->toBeFalse()
        ->and($nonAdmin->can('update', $brand))->toBeFalse()
        ->and($nonAdmin->can('delete', $brand))->toBeFalse()
        ->and($nonAdmin->can('deleteAny', Brand::class))->toBeFalse();
});

