<?php

declare(strict_types=1);

use App\Domain\Catalog\Actions\ProductModel\CreateProductModel;
use App\Domain\Catalog\Data\ProductModel\CreateProductModelData;
use App\Domain\Catalog\Exceptions\ProductModelRuleViolation;
use App\Domain\Catalog\Models\ProductModel;
use Database\Factories\BrandFactory;
use Database\Factories\CategoryFactory;
use Database\Factories\ProductModelFactory;
use Database\Factories\UserFactory;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Spatie\Activitylog\Models\Activity;

use function Pest\Laravel\assertDatabaseCount;
use function Pest\Laravel\assertDatabaseHas;

it('creates an active product model with normalized name, derived slug, and audit causer', function (): void {
    $user = UserFactory::new()->create();
    $brand = BrandFactory::new()->create(['name' => 'Samsung']);
    $category = CategoryFactory::new()->root()->create(['name' => 'Smartphones']);

    $action = new CreateProductModel;
    $dto = CreateProductModelData::from([
        'brand_public_id' => $brand->public_id,
        'category_public_id' => $category->public_id,
        'name' => '  Galaxy   S24  ',
    ]);

    $model = $action($dto, $user);

    expect($model->public_id)->toMatch('/^[0-9a-hjkmnp-tv-z]{26}$/i')
        ->and($model->name)->toBe('Galaxy S24')
        ->and($model->slug)->toBe('galaxy-s24')
        ->and($model->is_active)->toBeTrue()
        ->and($model->brand_id)->toBe($brand->id)
        ->and($model->category_id)->toBe($category->id)
        ->and($model->created_by)->toBe($user->id)
        ->and($model->updated_by)->toBe($user->id);

    assertDatabaseHas('product_models', [
        'id' => $model->id,
        'name' => 'Galaxy S24',
        'slug' => 'galaxy-s24',
        'is_active' => true,
    ]);
});

it('resolves slug collision with numeric suffix', function (): void {
    $brand1 = BrandFactory::new()->create(['name' => 'Brand A']);
    $brand2 = BrandFactory::new()->create(['name' => 'Brand B']);
    $category = CategoryFactory::new()->root()->create(['name' => 'Tablets']);

    ProductModelFactory::new()->forBrand($brand1)->forCategory($category)->create([
        'name' => 'Galaxy Tab',
        'slug' => 'galaxy-tab',
    ]);

    $action = new CreateProductModel;
    $model = $action(new CreateProductModelData(
        brand_public_id: $brand2->public_id,
        category_public_id: $category->public_id,
        name: 'Galaxy Tab'
    ));

    expect($model->name)->toBe('Galaxy Tab')
        ->and($model->slug)->toBe('galaxy-tab-2');
});

it('truncates base slug to 100 characters so suffix fits 120 character column', function (): void {
    $brand1 = BrandFactory::new()->create(['name' => 'Brand Alpha']);
    $brand2 = BrandFactory::new()->create(['name' => 'Brand Beta']);
    $category = CategoryFactory::new()->root()->create(['name' => 'Long Category']);

    // 80 characters of letters and words
    $longName = str_repeat('Long Model Name Part ', 4); // 84 chars, truncated to 80 by data validation if trimmed
    $longName = substr($longName, 0, 80);

    $action = new CreateProductModel;
    $model1 = $action(new CreateProductModelData(
        brand_public_id: $brand1->public_id,
        category_public_id: $category->public_id,
        name: $longName
    ));

    $model2 = $action(new CreateProductModelData(
        brand_public_id: $brand2->public_id,
        category_public_id: $category->public_id,
        name: $longName
    ));

    expect(strlen($model1->slug))->toBeLessThanOrEqual(100)
        ->and(strlen($model2->slug))->toBeLessThanOrEqual(120)
        ->and($model2->slug)->toEndWith('-2');
});

it('throws when brand public_id is unknown', function (): void {
    $category = CategoryFactory::new()->root()->create();
    $randomUlid = (string) Str::ulid();

    $action = new CreateProductModel;
    expect(fn () => $action(new CreateProductModelData(
        brand_public_id: $randomUlid,
        category_public_id: $category->public_id,
        name: 'Pixel 9'
    )))->toThrow(ProductModelRuleViolation::class, "Brand [{$randomUlid}] does not exist.");

    assertDatabaseCount('product_models', 0);
});

it('throws when category public_id is unknown', function (): void {
    $brand = BrandFactory::new()->create();
    $randomUlid = (string) Str::ulid();

    $action = new CreateProductModel;
    expect(fn () => $action(new CreateProductModelData(
        brand_public_id: $brand->public_id,
        category_public_id: $randomUlid,
        name: 'Pixel 9'
    )))->toThrow(ProductModelRuleViolation::class, "Category [{$randomUlid}] does not exist.");

    assertDatabaseCount('product_models', 0);
});

it('throws when brand is inactive', function (): void {
    $brand = BrandFactory::new()->inactive()->create();
    $category = CategoryFactory::new()->root()->create();

    $action = new CreateProductModel;
    expect(fn () => $action(new CreateProductModelData(
        brand_public_id: $brand->public_id,
        category_public_id: $category->public_id,
        name: 'Pixel 9'
    )))->toThrow(ProductModelRuleViolation::class, "Brand [{$brand->public_id}] is inactive.");

    assertDatabaseCount('product_models', 0);
});

it('throws when category has children and is not a leaf', function (): void {
    $brand = BrandFactory::new()->create();
    $parent = CategoryFactory::new()->root()->create(['name' => 'Computers']);
    CategoryFactory::new()->child($parent)->create(['name' => 'Laptops']);

    $action = new CreateProductModel;
    expect(fn () => $action(new CreateProductModelData(
        brand_public_id: $brand->public_id,
        category_public_id: $parent->public_id,
        name: 'MacBook Pro'
    )))->toThrow(ProductModelRuleViolation::class, "Category [{$parent->public_id}] has children and cannot receive product models.");

    assertDatabaseCount('product_models', 0);
});

it('throws when category is not effectively active', function (): void {
    $brand = BrandFactory::new()->create();
    $parent = CategoryFactory::new()->root()->create(['is_active' => false]);
    $child = CategoryFactory::new()->child($parent)->create(['is_active' => true]);

    $action = new CreateProductModel;
    expect(fn () => $action(new CreateProductModelData(
        brand_public_id: $brand->public_id,
        category_public_id: $child->public_id,
        name: 'iPhone 16'
    )))->toThrow(ProductModelRuleViolation::class, "Category [{$child->public_id}] is not effectively active.");

    assertDatabaseCount('product_models', 0);
});

it('throws when name duplicates another model of same brand ignoring case', function (): void {
    $brand = BrandFactory::new()->create(['name' => 'Apple']);
    $category = CategoryFactory::new()->root()->create(['name' => 'Phones']);

    ProductModelFactory::new()->forBrand($brand)->forCategory($category)->create([
        'name' => 'iPhone 15',
        'slug' => 'iphone-15',
    ]);

    $action = new CreateProductModel;
    expect(fn () => $action(new CreateProductModelData(
        brand_public_id: $brand->public_id,
        category_public_id: $category->public_id,
        name: 'IPHONE 15'
    )))->toThrow(ProductModelRuleViolation::class, "A product model named [IPHONE 15] already exists for brand [{$brand->public_id}].");

    assertDatabaseCount('product_models', 1);
});

it('allows same model name under different brands', function (): void {
    $brand1 = BrandFactory::new()->create(['name' => 'Apple']);
    $brand2 = BrandFactory::new()->create(['name' => 'Samsung']);
    $category = CategoryFactory::new()->root()->create(['name' => 'Wearables']);

    $action = new CreateProductModel;
    $model1 = $action(new CreateProductModelData(
        brand_public_id: $brand1->public_id,
        category_public_id: $category->public_id,
        name: 'Ultra'
    ));

    $model2 = $action(new CreateProductModelData(
        brand_public_id: $brand2->public_id,
        category_public_id: $category->public_id,
        name: 'Ultra'
    ));

    expect($model1->id)->not->toBe($model2->id)
        ->and($model1->name)->toBe('Ultra')
        ->and($model2->name)->toBe('Ultra');

    assertDatabaseCount('product_models', 2);
});

it('throws when name generates empty slug', function (): void {
    $brand = BrandFactory::new()->create();
    $category = CategoryFactory::new()->root()->create();

    $action = new CreateProductModel;
    expect(fn () => $action(new CreateProductModelData(
        brand_public_id: $brand->public_id,
        category_public_id: $category->public_id,
        name: '???'
    )))->toThrow(ProductModelRuleViolation::class, 'Product model name [???] generates an empty slug.');

    assertDatabaseCount('product_models', 0);
});

it('rejects invalid names via data validation', function (): void {
    $brand = BrandFactory::new()->create();
    $category = CategoryFactory::new()->root()->create();

    $invalidInputs = [
        ['brand_public_id' => $brand->public_id, 'category_public_id' => $category->public_id, 'name' => ''],
        ['brand_public_id' => $brand->public_id, 'category_public_id' => $category->public_id, 'name' => str_repeat('a', 81)],
        ['brand_public_id' => $brand->public_id, 'category_public_id' => $category->public_id, 'name' => "Bad\x07Model"],
        ['brand_public_id' => $brand->public_id, 'category_public_id' => $category->public_id, 'name' => '<script>alert(1)</script>'],
        ['brand_public_id' => 'invalid-ulid', 'category_public_id' => $category->public_id, 'name' => 'Valid Model'],
        ['brand_public_id' => $brand->public_id, 'category_public_id' => 'invalid-ulid', 'name' => 'Valid Model'],
    ];

    foreach ($invalidInputs as $input) {
        expect(fn () => CreateProductModelData::validateAndCreate($input))
            ->toThrow(ValidationException::class);
    }

    assertDatabaseCount('product_models', 0);
});

it('maps concurrent duplicate exception to ProductModelRuleViolation', function (): void {
    $brand = BrandFactory::new()->create();
    $category = CategoryFactory::new()->root()->create();

    ProductModel::saving(function (): void {
        throw new UniqueConstraintViolationException(
            'pgsql',
            'insert into product_models...',
            [],
            new Exception('duplicate')
        );
    });

    try {
        $action = new CreateProductModel;
        expect(fn () => $action(new CreateProductModelData(
            brand_public_id: $brand->public_id,
            category_public_id: $category->public_id,
            name: 'Pixel 9'
        )))->toThrow(ProductModelRuleViolation::class, "A product model named [Pixel 9] already exists for brand [{$brand->public_id}].");
    } finally {
        ProductModel::flushEventListeners();
    }
});

it('records product_model.created activity log with public IDs', function (): void {
    $user = UserFactory::new()->create();
    $brand = BrandFactory::new()->create(['name' => 'Google']);
    $category = CategoryFactory::new()->root()->create(['name' => 'Phones']);

    $action = new CreateProductModel;
    $model = $action(new CreateProductModelData(
        brand_public_id: $brand->public_id,
        category_public_id: $category->public_id,
        name: 'Pixel 9 Pro'
    ), $user);

    $activity = Activity::where('log_name', 'catalog')
        ->where('event', 'product_model.created')
        ->where('subject_type', ProductModel::class)
        ->where('subject_id', $model->id)
        ->first();

    expect($activity)->not->toBeNull()
        ->and($activity->description)->toBe('product_model.created')
        ->and($activity->causer_id)->toBe($user->id)
        ->and($activity->properties->toArray())->toBe([
            'public_id' => $model->public_id,
            'brand_public_id' => $brand->public_id,
            'category_public_id' => $category->public_id,
            'name' => 'Pixel 9 Pro',
            'slug' => 'pixel-9-pro',
        ]);
});
