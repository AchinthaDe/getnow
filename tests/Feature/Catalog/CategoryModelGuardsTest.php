<?php

declare(strict_types=1);

use App\Domain\Catalog\Actions\Category\CreateCategory;
use App\Domain\Catalog\Actions\Category\DeleteCategory;
use App\Domain\Catalog\Actions\Category\MoveCategory;
use App\Domain\Catalog\Data\Category\CreateCategoryData;
use App\Domain\Catalog\Data\Category\MoveCategoryData;
use App\Domain\Catalog\Exceptions\CategoryRuleViolation;
use App\Domain\Catalog\Models\Category;
use Database\Factories\CategoryFactory;
use Database\Factories\ProductModelFactory;

it('refuses deletion of category referenced by product model and surfaces inUse violation', function (): void {
    $category = CategoryFactory::new()->root()->create(['name' => 'Model Leaf']);
    ProductModelFactory::new()->forCategory($category)->create();

    expect(fn () => (new DeleteCategory)($category->public_id))
        ->toThrow(CategoryRuleViolation::class, "Cannot delete category [{$category->public_id}] because it is in use. Deactivate it instead.");

    expect(Category::whereKey($category->id)->exists())->toBeTrue();
});

it('allows deletion of childless category not referenced by product models', function (): void {
    $category = CategoryFactory::new()->root()->create(['name' => 'Unreferenced Leaf']);

    (new DeleteCategory)($category->public_id);

    expect(Category::whereKey($category->id)->exists())->toBeFalse();
});

it('refuses creating child category under parent category referenced by product model', function (): void {
    $parent = CategoryFactory::new()->root()->create(['name' => 'Parent With Model']);
    ProductModelFactory::new()->forCategory($parent)->create();

    $data = new CreateCategoryData('New Child', $parent->public_id);

    expect(fn () => (new CreateCategory)($data))
        ->toThrow(CategoryRuleViolation::class, "Cannot create child category under parent [{$parent->public_id}] because it has product models assigned.");
});

it('allows creating child category under parent not referenced by product models', function (): void {
    $parent = CategoryFactory::new()->root()->create(['name' => 'Parent Without Models']);

    $data = new CreateCategoryData('Valid Child', $parent->public_id);
    $child = (new CreateCategory)($data);

    expect($child->parent_id)->toBe($parent->id);
});

it('refuses moving category under destination parent referenced by product model', function (): void {
    $parentWithModel = CategoryFactory::new()->root()->create(['name' => 'Destination With Model']);
    ProductModelFactory::new()->forCategory($parentWithModel)->create();

    $child = CategoryFactory::new()->root()->create(['name' => 'Mover']);
    $data = new MoveCategoryData($parentWithModel->public_id);

    expect(fn () => (new MoveCategory)($child->public_id, $data))
        ->toThrow(CategoryRuleViolation::class, "Cannot move category under parent [{$parentWithModel->public_id}] because it has product models assigned.");
});

it('allows moving category under destination parent not referenced by product models', function (): void {
    $emptyParent = CategoryFactory::new()->root()->create(['name' => 'Empty Destination']);
    $child = CategoryFactory::new()->root()->create(['name' => 'Valid Mover']);

    $data = new MoveCategoryData($emptyParent->public_id);
    (new MoveCategory)($child->public_id, $data);

    expect($child->fresh()->parent_id)->toBe($emptyParent->id);
});

it('allows moving a model-bearing leaf category under an unreferenced parent', function (): void {
    $leafWithModel = CategoryFactory::new()->root()->create(['name' => 'Leaf Bearing Model']);
    ProductModelFactory::new()->forCategory($leafWithModel)->create();

    $destParent = CategoryFactory::new()->root()->create(['name' => 'Unreferenced Dest Parent']);
    $data = new MoveCategoryData($destParent->public_id);

    (new MoveCategory)($leafWithModel->public_id, $data);

    expect($leafWithModel->fresh()->parent_id)->toBe($destParent->id);
});
