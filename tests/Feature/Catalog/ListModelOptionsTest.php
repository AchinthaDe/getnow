<?php

declare(strict_types=1);

use App\Domain\Catalog\Actions\ProductModel\ListModelOptions;
use App\Domain\Catalog\Data\ProductModel\ProductModelOptionData;
use Database\Factories\BrandFactory;
use Database\Factories\CategoryFactory;
use Database\Factories\ProductModelFactory;
use Illuminate\Support\Str;
use Tests\Support\AssertsQueryCounts;

it('returns only active models for specified active brand and effectively active category', function (): void {
    $brand = BrandFactory::new()->create();
    $category = CategoryFactory::new()->root()->create();

    ProductModelFactory::new()->forBrand($brand)->forCategory($category)->count(3)->create();

    $action = new ListModelOptions;
    $options = $action($brand->public_id, $category->public_id);

    expect($options)->toHaveCount(3);
    foreach ($options as $option) {
        expect($option)->toBeInstanceOf(ProductModelOptionData::class)
            ->and($option->public_id)->toBeString()
            ->and($option->name)->toBeString();
    }
});

it('excludes inactive models', function (): void {
    $brand = BrandFactory::new()->create();
    $category = CategoryFactory::new()->root()->create();

    $active = ProductModelFactory::new()->forBrand($brand)->forCategory($category)->create(['name' => 'Active Model']);
    ProductModelFactory::new()->forBrand($brand)->forCategory($category)->inactive()->create(['name' => 'Inactive Model']);

    $action = new ListModelOptions;
    $options = $action($brand->public_id, $category->public_id);

    expect($options)->toHaveCount(1)
        ->and($options[0]->name)->toBe('Active Model')
        ->and($options[0]->public_id)->toBe($active->public_id);
});

it('excludes models belonging to inactive brand', function (): void {
    $inactiveBrand = BrandFactory::new()->inactive()->create();
    $category = CategoryFactory::new()->root()->create();

    ProductModelFactory::new()->forBrand($inactiveBrand)->forCategory($category)->create();

    $action = new ListModelOptions;
    $options = $action($inactiveBrand->public_id, $category->public_id);

    expect($options)->toBeEmpty();
});

it('excludes models belonging to inactive category or category with inactive ancestor', function (): void {
    $brand = BrandFactory::new()->create();

    $inactiveRoot = CategoryFactory::new()->root()->create(['is_active' => false]);
    $activeChild = CategoryFactory::new()->child($inactiveRoot)->create(['is_active' => true]);

    ProductModelFactory::new()->forBrand($brand)->forCategory($activeChild)->create();

    $action = new ListModelOptions;
    $options = $action($brand->public_id, $activeChild->public_id);

    expect($options)->toBeEmpty();
});

it('excludes models belonging to different brand or different category', function (): void {
    $brandA = BrandFactory::new()->create();
    $brandB = BrandFactory::new()->create();
    $categoryA = CategoryFactory::new()->root()->create();
    $categoryB = CategoryFactory::new()->root()->create();

    ProductModelFactory::new()->forBrand($brandA)->forCategory($categoryA)->create(['name' => 'Target Model']);
    ProductModelFactory::new()->forBrand($brandB)->forCategory($categoryA)->create(['name' => 'Brand B Model']);
    ProductModelFactory::new()->forBrand($brandA)->forCategory($categoryB)->create(['name' => 'Category B Model']);

    $action = new ListModelOptions;
    $options = $action($brandA->public_id, $categoryA->public_id);

    expect($options)->toHaveCount(1)
        ->and($options[0]->name)->toBe('Target Model');
});

it('matches search case-insensitively', function (): void {
    $brand = BrandFactory::new()->create();
    $category = CategoryFactory::new()->root()->create();

    ProductModelFactory::new()->forBrand($brand)->forCategory($category)->create(['name' => 'Galaxy S24']);
    ProductModelFactory::new()->forBrand($brand)->forCategory($category)->create(['name' => 'Galaxy Z Flip']);
    ProductModelFactory::new()->forBrand($brand)->forCategory($category)->create(['name' => 'Pixel 8']);

    $action = new ListModelOptions;
    $options = $action($brand->public_id, $category->public_id, search: 'galaxy');

    $names = array_map(fn (ProductModelOptionData $o) => $o->name, $options);
    expect($names)->toBe(['Galaxy S24', 'Galaxy Z Flip']);
});

it('orders options by lower name ascending then id', function (): void {
    $brand = BrandFactory::new()->create();
    $category = CategoryFactory::new()->root()->create();

    ProductModelFactory::new()->forBrand($brand)->forCategory($category)->create(['name' => 'Zeta Model']);
    ProductModelFactory::new()->forBrand($brand)->forCategory($category)->create(['name' => 'alpha Model']);
    ProductModelFactory::new()->forBrand($brand)->forCategory($category)->create(['name' => 'Beta Model']);

    $action = new ListModelOptions;
    $options = $action($brand->public_id, $category->public_id);

    $names = array_map(fn (ProductModelOptionData $o) => $o->name, $options);
    expect($names)->toBe(['alpha Model', 'Beta Model', 'Zeta Model']);
});

it('returns empty array when brand or category public_id is unknown', function (): void {
    $brand = BrandFactory::new()->create();
    $category = CategoryFactory::new()->root()->create();
    $randomUlid = (string) Str::ulid();

    $action = new ListModelOptions;
    expect($action($randomUlid, $category->public_id))->toBeEmpty()
        ->and($action($brand->public_id, $randomUlid))->toBeEmpty();
});

it('clamps limit between 1 and 50', function (): void {
    $brand = BrandFactory::new()->create();
    $category = CategoryFactory::new()->root()->create();

    ProductModelFactory::new()->forBrand($brand)->forCategory($category)->count(60)->create();

    $action = new ListModelOptions;

    expect($action($brand->public_id, $category->public_id))->toHaveCount(20)
        ->and($action($brand->public_id, $category->public_id, limit: 10))->toHaveCount(10)
        ->and($action($brand->public_id, $category->public_id, limit: 1000))->toHaveCount(50)
        ->and($action($brand->public_id, $category->public_id, limit: 0))->toHaveCount(1);
});

it('escapes LIKE metacharacters percent underscore and backslash', function (): void {
    $brand = BrandFactory::new()->create();
    $category = CategoryFactory::new()->root()->create();

    ProductModelFactory::new()->forBrand($brand)->forCategory($category)->create(['name' => 'Phone 100%']);
    ProductModelFactory::new()->forBrand($brand)->forCategory($category)->create(['name' => 'Phone 1000']);
    ProductModelFactory::new()->forBrand($brand)->forCategory($category)->create(['name' => 'Phone_A']);
    ProductModelFactory::new()->forBrand($brand)->forCategory($category)->create(['name' => 'PhoneXA']);
    ProductModelFactory::new()->forBrand($brand)->forCategory($category)->create(['name' => 'Phone C\\D']);
    ProductModelFactory::new()->forBrand($brand)->forCategory($category)->create(['name' => 'Phone CD']);

    $action = new ListModelOptions;

    $percent = $action($brand->public_id, $category->public_id, search: '%');
    expect(array_map(fn (ProductModelOptionData $o) => $o->name, $percent))->toBe(['Phone 100%']);

    $underscore = $action($brand->public_id, $category->public_id, search: '_');
    expect(array_map(fn (ProductModelOptionData $o) => $o->name, $underscore))->toBe(['Phone_A']);

    $backslash = $action($brand->public_id, $category->public_id, search: '\\');
    expect(array_map(fn (ProductModelOptionData $o) => $o->name, $backslash))->toBe(['Phone C\\D']);
});

it('executes in exactly one query for small and large datasets', function (): void {
    $brand = BrandFactory::new()->create();
    $category = CategoryFactory::new()->root()->create();

    // Small dataset (5 models)
    ProductModelFactory::new()->forBrand($brand)->forCategory($category)->count(5)->create();

    $smallWithoutSearch = AssertsQueryCounts::assertCount(1, fn () => (new ListModelOptions)($brand->public_id, $category->public_id));
    $smallWithSearch = AssertsQueryCounts::assertCount(1, fn () => (new ListModelOptions)($brand->public_id, $category->public_id, search: 'Model'));

    // Large dataset (200 models total)
    ProductModelFactory::new()->forBrand($brand)->forCategory($category)->count(195)->create();

    $largeWithoutSearch = AssertsQueryCounts::assertCount(1, fn () => (new ListModelOptions)($brand->public_id, $category->public_id));
    $largeWithSearch = AssertsQueryCounts::assertCount(1, fn () => (new ListModelOptions)($brand->public_id, $category->public_id, search: 'Model'));

    expect($smallWithoutSearch)->toBeArray()
        ->and($smallWithSearch)->toBeArray()
        ->and($largeWithoutSearch)->toBeArray()
        ->and($largeWithSearch)->toBeArray();
});
