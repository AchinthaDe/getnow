<?php

declare(strict_types=1);

use App\Domain\Catalog\Actions\ProductModel\UpdateProductModel;
use App\Domain\Catalog\Data\ProductModel\UpdateProductModelData;
use App\Domain\Catalog\Exceptions\ProductModelRuleViolation;
use App\Domain\Catalog\Models\ProductModel;
use Database\Factories\BrandFactory;
use Database\Factories\CategoryFactory;
use Database\Factories\ProductModelFactory;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Activity;

use function Pest\Laravel\assertDatabaseHas;

it('updates model name while preserving slug, brand, and category', function (): void {
    $user = UserFactory::new()->create();
    $brand = BrandFactory::new()->create(['name' => 'Google']);
    $category = CategoryFactory::new()->root()->create(['name' => 'Phones']);
    $model = ProductModelFactory::new()->forBrand($brand)->forCategory($category)->create([
        'name' => 'Pixel 8',
        'slug' => 'pixel-8',
    ]);

    $action = new UpdateProductModel;
    $updated = $action($model, new UpdateProductModelData(name: 'Pixel 8 Pro'), $user);

    expect($updated->name)->toBe('Pixel 8 Pro')
        ->and($updated->slug)->toBe('pixel-8')
        ->and($updated->brand_id)->toBe($brand->id)
        ->and($updated->category_id)->toBe($category->id)
        ->and($updated->updated_by)->toBe($user->id);

    assertDatabaseHas('product_models', [
        'id' => $model->id,
        'name' => 'Pixel 8 Pro',
        'slug' => 'pixel-8',
        'updated_by' => $user->id,
    ]);
});

it('allows renaming model to different case of its own name', function (): void {
    $brand = BrandFactory::new()->create();
    $category = CategoryFactory::new()->root()->create();
    $model = ProductModelFactory::new()->forBrand($brand)->forCategory($category)->create([
        'name' => 'pixel 8',
        'slug' => 'pixel-8',
    ]);

    $action = new UpdateProductModel;
    $updated = $action($model->public_id, new UpdateProductModelData(name: 'Pixel 8'));

    expect($updated->name)->toBe('Pixel 8');

    assertDatabaseHas('product_models', [
        'id' => $model->id,
        'name' => 'Pixel 8',
    ]);
});

it('rejects rename that collides case-insensitively with another model of same brand', function (): void {
    $brand = BrandFactory::new()->create(['name' => 'Google']);
    $category = CategoryFactory::new()->root()->create();
    ProductModelFactory::new()->forBrand($brand)->forCategory($category)->create([
        'name' => 'Pixel 8',
        'slug' => 'pixel-8',
    ]);
    $model2 = ProductModelFactory::new()->forBrand($brand)->forCategory($category)->create([
        'name' => 'Pixel 8a',
        'slug' => 'pixel-8a',
    ]);

    $action = new UpdateProductModel;
    expect(fn () => $action($model2, new UpdateProductModelData(name: 'pixel 8')))
        ->toThrow(ProductModelRuleViolation::class, "A product model named [pixel 8] already exists for brand [{$brand->public_id}].");

    assertDatabaseHas('product_models', [
        'id' => $model2->id,
        'name' => 'Pixel 8a',
    ]);
});

it('allows updating model to same name as a model in another brand', function (): void {
    $brand1 = BrandFactory::new()->create();
    $brand2 = BrandFactory::new()->create();
    $category = CategoryFactory::new()->root()->create();

    ProductModelFactory::new()->forBrand($brand1)->forCategory($category)->create([
        'name' => 'Edge',
        'slug' => 'edge-1',
    ]);
    $model2 = ProductModelFactory::new()->forBrand($brand2)->forCategory($category)->create([
        'name' => 'Old Model',
        'slug' => 'old-model',
    ]);

    $action = new UpdateProductModel;
    $updated = $action($model2, new UpdateProductModelData(name: 'Edge'));

    expect($updated->name)->toBe('Edge');

    assertDatabaseHas('product_models', [
        'id' => $model2->id,
        'name' => 'Edge',
    ]);
});

it('returns record without saving or logging when name is unchanged or null', function (): void {
    $brand = BrandFactory::new()->create();
    $category = CategoryFactory::new()->root()->create();
    $model = ProductModelFactory::new()->forBrand($brand)->forCategory($category)->create([
        'name' => 'Unchanged Model',
    ]);

    DB::enableQueryLog();

    $action = new UpdateProductModel;
    $result1 = $action($model, new UpdateProductModelData(name: 'Unchanged Model'));
    $result2 = $action($model, new UpdateProductModelData(name: null));

    $queries = DB::getQueryLog();
    DB::disableQueryLog();

    $updates = array_filter($queries, fn (array $q) => str_starts_with(strtolower((string) $q['query']), 'update'));
    expect($updates)->toBeEmpty()
        ->and($result1->id)->toBe($model->id)
        ->and($result2->id)->toBe($model->id);

    expect(Activity::where('subject_type', ProductModel::class)->count())->toBe(0);
});

it('records product_model.updated activity log with old and new name', function (): void {
    $user = UserFactory::new()->create();
    $brand = BrandFactory::new()->create();
    $category = CategoryFactory::new()->root()->create();
    $model = ProductModelFactory::new()->forBrand($brand)->forCategory($category)->create([
        'name' => 'Old Name',
    ]);

    $action = new UpdateProductModel;
    $action($model, new UpdateProductModelData(name: 'New Name'), $user);

    $activity = Activity::where('log_name', 'catalog')
        ->where('event', 'product_model.updated')
        ->where('subject_type', ProductModel::class)
        ->where('subject_id', $model->id)
        ->first();

    expect($activity)->not->toBeNull()
        ->and($activity->description)->toBe('product_model.updated')
        ->and($activity->causer_id)->toBe($user->id)
        ->and($activity->properties->toArray())->toBe([
            'public_id' => $model->public_id,
            'old_name' => 'Old Name',
            'new_name' => 'New Name',
        ]);
});

it('throws model not found exception when updating unknown public_id', function (): void {
    $randomUlid = (string) Str::ulid();

    $action = new UpdateProductModel;
    expect(fn () => $action($randomUlid, new UpdateProductModelData(name: 'Ghost Model')))
        ->toThrow(ModelNotFoundException::class);
});
