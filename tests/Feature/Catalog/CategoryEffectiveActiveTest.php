<?php

declare(strict_types=1);

use App\Domain\Catalog\Models\Category;
use Database\Factories\CategoryFactory;

it('evaluates effective active scope correctly across hierarchies up to MAX_DEPTH', function (): void {
    $activeRoot = CategoryFactory::new()->root()->create(['is_active' => true]);
    $activeChild = CategoryFactory::new()->child($activeRoot)->create(['is_active' => true]);

    $inactiveRoot = CategoryFactory::new()->root()->create(['is_active' => false]);
    $activeChildOfInactive = CategoryFactory::new()->child($inactiveRoot)->create(['is_active' => true]);

    $activeRootWithInactiveChild = CategoryFactory::new()->root()->create(['is_active' => true]);
    $inactiveChild = CategoryFactory::new()->child($activeRootWithInactiveChild)->create(['is_active' => false]);

    $chainRoot = CategoryFactory::new()->root()->create(['is_active' => false]);
    $currentParent = $chainRoot;
    $chainDescendants = [];
    for ($depth = 2; $depth <= Category::MAX_DEPTH; $depth++) {
        $descendant = CategoryFactory::new()->child($currentParent)->create(['is_active' => true]);
        $chainDescendants[] = $descendant;
        $currentParent = $descendant;
    }

    $effectivePublicIds = Category::effectivelyActive()->pluck('public_id')->all();

    expect($effectivePublicIds)->toContain($activeRoot->public_id)
        ->toContain($activeChild->public_id)
        ->toContain($activeRootWithInactiveChild->public_id);

    expect($effectivePublicIds)->not->toContain($inactiveRoot->public_id);
    expect($effectivePublicIds)->not->toContain($activeChildOfInactive->public_id);
    expect($effectivePublicIds)->not->toContain($inactiveChild->public_id);
    expect($effectivePublicIds)->not->toContain($chainRoot->public_id);

    foreach ($chainDescendants as $descendant) {
        expect($effectivePublicIds)->not->toContain($descendant->public_id);
    }
});

it('guarantees parity between effectivelyActive filter and withEffectiveActive computed attribute', function (): void {
    $r1 = CategoryFactory::new()->root()->create(['is_active' => true]);
    CategoryFactory::new()->child($r1)->create(['is_active' => true]);

    $r2 = CategoryFactory::new()->root()->create(['is_active' => true]);
    CategoryFactory::new()->child($r2)->create(['is_active' => false]);

    $r3 = CategoryFactory::new()->root()->create(['is_active' => false]);
    CategoryFactory::new()->child($r3)->create(['is_active' => true]);

    $r4 = CategoryFactory::new()->root()->create(['is_active' => false]);
    CategoryFactory::new()->child($r4)->create(['is_active' => false]);

    CategoryFactory::new()->root()->create(['is_active' => true]);
    CategoryFactory::new()->root()->create(['is_active' => false]);

    $filteredIds = Category::effectivelyActive()->pluck('id')->sort()->values()->all();
    $computedIds = Category::withEffectiveActive()->get()
        ->filter(fn (Category $c): bool => (bool) $c->is_effectively_active)
        ->pluck('id')->sort()->values()->all();

    expect($filteredIds)->not->toBeEmpty()
        ->and($filteredIds)->toBe($computedIds);
});
