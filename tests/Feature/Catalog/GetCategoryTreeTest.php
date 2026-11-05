<?php

declare(strict_types=1);

use App\Domain\Catalog\Actions\Category\GetCategoryTree;
use App\Domain\Catalog\Data\Category\CategoryTreeNodeData;
use App\Domain\Catalog\Enums\CategoryTreeAudience;
use Database\Factories\CategoryFactory;
use Tests\Support\AssertsQueryCounts;

it('builds full nested admin category tree in a single database query under strict mode', function (): void {
    // Roots with tied sort_order
    $root1 = CategoryFactory::new()->root()->create([
        'name' => 'Root 1',
        'sort_order' => 0,
        'is_active' => true,
        'show_in_nav' => true,
    ]);
    $root2 = CategoryFactory::new()->root()->create([
        'name' => 'Root 2',
        'sort_order' => 0,
        'is_active' => false,
        'show_in_nav' => false,
    ]);
    $root3 = CategoryFactory::new()->root()->create([
        'name' => 'Root 3',
        'sort_order' => 1,
        'is_active' => true,
        'show_in_nav' => true,
    ]);

    // Children under root1 with tied sort_order
    $child1a = CategoryFactory::new()->child($root1)->create([
        'name' => 'Child 1A',
        'sort_order' => 0,
        'is_active' => true,
    ]);
    $child1b = CategoryFactory::new()->child($root1)->create([
        'name' => 'Child 1B',
        'sort_order' => 0,
        'is_active' => false,
    ]);

    // Active child under inactive root2 (should have is_effectively_active = false)
    $child2 = CategoryFactory::new()->child($root2)->create([
        'name' => 'Child 2',
        'sort_order' => 0,
        'is_active' => true,
    ]);

    /** @var array<int, CategoryTreeNodeData> $tree */
    $tree = AssertsQueryCounts::assertCount(1, fn (): array => (new GetCategoryTree)(CategoryTreeAudience::Admin));

    expect($tree)->toHaveCount(3);

    // Verify ordering by sort_order ASC, then id ASC (root1 id < root2 id)
    expect($tree[0]->public_id)->toBe($root1->public_id)
        ->and($tree[0]->sort_order)->toBe(0)
        ->and($tree[0]->is_active)->toBeTrue()
        ->and($tree[0]->is_effectively_active)->toBeTrue();

    expect($tree[1]->public_id)->toBe($root2->public_id)
        ->and($tree[1]->sort_order)->toBe(0)
        ->and($tree[1]->is_active)->toBeFalse()
        ->and($tree[1]->is_effectively_active)->toBeFalse();

    expect($tree[2]->public_id)->toBe($root3->public_id)
        ->and($tree[2]->sort_order)->toBe(1);

    // Check nested children under root1: child1a id < child1b id
    expect($tree[0]->children)->toHaveCount(2)
        ->and($tree[0]->children[0]->public_id)->toBe($child1a->public_id)
        ->and($tree[0]->children[0]->is_active)->toBeTrue()
        ->and($tree[0]->children[0]->is_effectively_active)->toBeTrue()
        ->and($tree[0]->children[1]->public_id)->toBe($child1b->public_id)
        ->and($tree[0]->children[1]->is_active)->toBeFalse()
        ->and($tree[0]->children[1]->is_effectively_active)->toBeFalse();

    // Check child under inactive root2: is_active = true, is_effectively_active = false
    expect($tree[1]->children)->toHaveCount(1)
        ->and($tree[1]->children[0]->public_id)->toBe($child2->public_id)
        ->and($tree[1]->children[0]->is_active)->toBeTrue()
        ->and($tree[1]->children[0]->is_effectively_active)->toBeFalse();
});

it('builds storefront category tree excluding inactive and hidden categories while pruning orphaned subtrees', function (): void {
    // Active root A with show_in_nav = false and active child A1 (show_in_nav = true)
    $rootA = CategoryFactory::new()->root()->create([
        'name' => 'Root A',
        'is_active' => true,
        'show_in_nav' => false,
    ]);
    CategoryFactory::new()->child($rootA)->create([
        'name' => 'Child A1',
        'is_active' => true,
        'show_in_nav' => true,
    ]);

    // Inactive root B with active child B1
    $rootB = CategoryFactory::new()->root()->create([
        'name' => 'Root B',
        'is_active' => false,
        'show_in_nav' => true,
    ]);
    CategoryFactory::new()->child($rootB)->create([
        'name' => 'Child B1',
        'is_active' => true,
        'show_in_nav' => true,
    ]);

    // Active root C with show_in_nav = true
    $rootC = CategoryFactory::new()->root()->create([
        'name' => 'Root C',
        'is_active' => true,
        'show_in_nav' => true,
    ]);
    // Inactive child C1
    CategoryFactory::new()->child($rootC)->create([
        'name' => 'Child C1',
        'is_active' => false,
        'show_in_nav' => true,
    ]);
    // Hidden child C2 with active grandchild C2a
    $childC2 = CategoryFactory::new()->child($rootC)->create([
        'name' => 'Child C2',
        'is_active' => true,
        'show_in_nav' => false,
    ]);
    CategoryFactory::new()->child($childC2)->create([
        'name' => 'Grandchild C2a',
        'is_active' => true,
        'show_in_nav' => true,
    ]);
    // Visible active child C3 with active grandchild C3a
    $childC3 = CategoryFactory::new()->child($rootC)->create([
        'name' => 'Child C3',
        'is_active' => true,
        'show_in_nav' => true,
    ]);
    $grandchildC3a = CategoryFactory::new()->child($childC3)->create([
        'name' => 'Grandchild C3a',
        'is_active' => true,
        'show_in_nav' => true,
    ]);

    /** @var array<int, CategoryTreeNodeData> $tree */
    $tree = AssertsQueryCounts::assertCount(1, fn (): array => (new GetCategoryTree)(CategoryTreeAudience::Storefront));

    // Only Root C should be present at root level
    expect($tree)->toHaveCount(1)
        ->and($tree[0]->public_id)->toBe($rootC->public_id);

    // Root C should have only visible child C3
    expect($tree[0]->children)->toHaveCount(1)
        ->and($tree[0]->children[0]->public_id)->toBe($childC3->public_id);

    // Child C3 should have grandchild C3a
    expect($tree[0]->children[0]->children)->toHaveCount(1)
        ->and($tree[0]->children[0]->children[0]->public_id)->toBe($grandchildC3a->public_id);
});
