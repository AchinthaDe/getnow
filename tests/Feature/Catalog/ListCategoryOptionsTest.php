<?php

declare(strict_types=1);

use App\Domain\Catalog\Actions\Category\ListCategoryOptions;
use App\Domain\Catalog\Data\Category\CategoryOptionData;
use Database\Factories\CategoryFactory;
use Tests\Support\AssertsQueryCounts;

it('returns depth-first list of effectively active structural leaf categories with breadcrumb labels in 1 query', function (): void {
    // 1. Childless active root (is a structural leaf)
    $childlessRoot = CategoryFactory::new()->root()->create([
        'name' => 'Standalone',
        'sort_order' => 0,
        'is_active' => true,
    ]);

    // 2. Active root with two inactive children (not a structural leaf, and children are inactive)
    $rootInactiveChildren = CategoryFactory::new()->root()->create([
        'name' => 'All Inactive Parent',
        'sort_order' => 1,
        'is_active' => true,
    ]);
    CategoryFactory::new()->child($rootInactiveChildren)->create([
        'name' => 'Inactive Child 1',
        'is_active' => false,
    ]);
    CategoryFactory::new()->child($rootInactiveChildren)->create([
        'name' => 'Inactive Child 2',
        'is_active' => false,
    ]);

    // 3. Active root with one active child and one inactive child
    $rootMixed = CategoryFactory::new()->root()->create([
        'name' => 'Electronics',
        'sort_order' => 2,
        'is_active' => true,
    ]);
    $activeChild = CategoryFactory::new()->child($rootMixed)->create([
        'name' => 'Phones',
        'sort_order' => 0,
        'is_active' => true,
    ]);
    CategoryFactory::new()->child($rootMixed)->create([
        'name' => 'Old Tech',
        'sort_order' => 1,
        'is_active' => false,
    ]);

    // 4. Inactive root with active child (child is inactive effectively)
    $inactiveRoot = CategoryFactory::new()->root()->create([
        'name' => 'Inactive Parent',
        'sort_order' => 3,
        'is_active' => false,
    ]);
    CategoryFactory::new()->child($inactiveRoot)->create([
        'name' => 'Active Child of Inactive',
        'is_active' => true,
    ]);

    /** @var array<int, CategoryOptionData> $options */
    $options = AssertsQueryCounts::assertCount(1, fn (): array => (new ListCategoryOptions)());

    expect($options)->toHaveCount(2);

    expect($options[0]->public_id)->toBe($childlessRoot->public_id)
        ->and($options[0]->name)->toBe('Standalone')
        ->and($options[0]->label)->toBe('Standalone');

    expect($options[1]->public_id)->toBe($activeChild->public_id)
        ->and($options[1]->name)->toBe('Phones')
        ->and($options[1]->label)->toBe('Electronics > Phones');
});
