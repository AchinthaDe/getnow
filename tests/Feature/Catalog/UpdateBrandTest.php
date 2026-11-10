<?php

declare(strict_types=1);

use App\Domain\Catalog\Actions\Brand\CreateBrand;
use App\Domain\Catalog\Actions\Brand\UpdateBrand;
use App\Domain\Catalog\Data\Brand\BrandData;
use App\Domain\Catalog\Data\Brand\CreateBrandData;
use App\Domain\Catalog\Data\Brand\UpdateBrandData;
use App\Domain\Catalog\Exceptions\BrandRuleViolation;
use App\Domain\Catalog\Models\Brand;
use Database\Factories\BrandFactory;
use Database\Factories\UserFactory;
use Illuminate\Support\Facades\DB;
use Spatie\Activitylog\Models\Activity;

use function Pest\Laravel\assertDatabaseHas;

it('updates brand name and show_in_nav while preserving slug', function (): void {
    $user = UserFactory::new()->create();
    $brand = BrandFactory::new()->create([
        'name' => 'Old Brand',
        'slug' => 'old-brand',
        'show_in_nav' => false,
    ]);

    $action = new UpdateBrand;
    $updated = $action($brand, new UpdateBrandData(name: 'New Brand', show_in_nav: true), $user);

    expect($updated->name)->toBe('New Brand')
        ->and($updated->slug)->toBe('old-brand')
        ->and($updated->show_in_nav)->toBeTrue()
        ->and($updated->updated_by)->toBe($user->id);

    assertDatabaseHas('brands', [
        'id' => $brand->id,
        'name' => 'New Brand',
        'slug' => 'old-brand',
        'show_in_nav' => true,
        'updated_by' => $user->id,
    ]);
});

it('updates brand on active and inactive records', function (): void {
    $brand = BrandFactory::new()->inactive()->create([
        'name' => 'Inactive Old',
        'slug' => 'inactive-old',
    ]);

    $action = new UpdateBrand;
    $updated = $action($brand->public_id, new UpdateBrandData(name: 'Inactive New'));

    expect($updated->name)->toBe('Inactive New')
        ->and($updated->is_active)->toBeFalse();
});

it('allows renaming brand to different case of its own name', function (): void {
    $brand = BrandFactory::new()->create([
        'name' => 'lg',
        'slug' => 'lg',
    ]);

    $action = new UpdateBrand;
    $updated = $action($brand, new UpdateBrandData(name: 'LG'));

    expect($updated->name)->toBe('LG');

    assertDatabaseHas('brands', [
        'id' => $brand->id,
        'name' => 'LG',
    ]);
});

it('rejects rename that collides case-insensitively with another brand', function (): void {
    BrandFactory::new()->create(['name' => 'Sony', 'slug' => 'sony']);
    $brandB = BrandFactory::new()->create(['name' => 'Panasonic', 'slug' => 'panasonic']);

    $action = new UpdateBrand;

    expect(fn () => $action($brandB, new UpdateBrandData(name: 'sony')))
        ->toThrow(BrandRuleViolation::class, 'A brand named [sony] already exists.');

    assertDatabaseHas('brands', [
        'id' => $brandB->id,
        'name' => 'Panasonic',
    ]);
});

it('returns record without saving or logging when unchanged', function (): void {
    $brand = BrandFactory::new()->create([
        'name' => 'Unchanged Brand',
        'show_in_nav' => false,
    ]);

    DB::enableQueryLog();

    $action = new UpdateBrand;
    $result = $action($brand, new UpdateBrandData(name: 'Unchanged Brand', show_in_nav: false));

    $queries = DB::getQueryLog();
    $updateQueries = array_filter($queries, fn (array $q) => str_starts_with(strtoupper((string) $q['query']), 'UPDATE'));

    expect($result->id)->toBe($brand->id)
        ->and($updateQueries)->toBeEmpty()
        ->and(Activity::count())->toBe(0);
});

it('returns record without saving or logging when both name and show_in_nav are null', function (): void {
    $brand = BrandFactory::new()->create([
        'name' => 'Null Test Brand',
        'show_in_nav' => false,
    ]);

    DB::enableQueryLog();

    $action = new UpdateBrand;
    $result = $action($brand, new UpdateBrandData(name: null, show_in_nav: null));

    $queries = DB::getQueryLog();
    $updateQueries = array_filter($queries, fn (array $q) => str_starts_with(strtoupper((string) $q['query']), 'UPDATE'));

    expect($result->id)->toBe($brand->id)
        ->and($updateQueries)->toBeEmpty()
        ->and(Activity::count())->toBe(0);
});

it('records brand.updated activity log with old and new values', function (): void {
    $user = UserFactory::new()->create();
    $brand = BrandFactory::new()->create([
        'name' => 'Initial Brand',
        'show_in_nav' => false,
    ]);

    $action = new UpdateBrand;
    $action($brand, new UpdateBrandData(name: 'Modified Brand', show_in_nav: true), $user);

    $activity = Activity::where('log_name', 'catalog')
        ->where('event', 'brand.updated')
        ->where('subject_type', Brand::class)
        ->where('subject_id', $brand->id)
        ->first();

    expect($activity)->not->toBeNull()
        ->and($activity->description)->toBe('brand.updated')
        ->and($activity->causer_id)->toBe($user->id)
        ->and($activity->properties->toArray())->toBe([
            'public_id' => $brand->public_id,
            'old_name' => 'Initial Brand',
            'new_name' => 'Modified Brand',
            'old_show_in_nav' => false,
            'new_show_in_nav' => true,
        ]);
});

it('records brand.updated activity log containing only show_in_nav properties when name is not modified', function (): void {
    $user = UserFactory::new()->create();
    $brand = BrandFactory::new()->create([
        'name' => 'Steady Brand',
        'show_in_nav' => false,
    ]);

    $action = new UpdateBrand;
    $action($brand, new UpdateBrandData(show_in_nav: true), $user);

    $activity = Activity::where('log_name', 'catalog')
        ->where('event', 'brand.updated')
        ->where('subject_type', Brand::class)
        ->where('subject_id', $brand->id)
        ->first();

    expect($activity)->not->toBeNull()
        ->and($activity->description)->toBe('brand.updated')
        ->and($activity->causer_id)->toBe($user->id);

    $properties = $activity->properties->toArray();
    expect($properties)->toBe([
        'public_id' => $brand->public_id,
        'old_show_in_nav' => false,
        'new_show_in_nav' => true,
    ])
        ->and($properties)->not->toHaveKey('old_name')
        ->and($properties)->not->toHaveKey('new_name');
});

it('persists show_in_nav through creation and update and exposes in BrandData', function (): void {
    $action = new CreateBrand;
    $brand = $action(new CreateBrandData(name: 'Nav Brand', show_in_nav: false));
    expect($brand->show_in_nav)->toBeFalse();

    $data = BrandData::fromModel($brand);
    expect($data->show_in_nav)->toBeFalse();

    $updateAction = new UpdateBrand;
    $updated = $updateAction($brand, new UpdateBrandData(show_in_nav: true));
    expect($updated->show_in_nav)->toBeTrue();

    $updatedData = BrandData::fromModel($updated);
    expect($updatedData->show_in_nav)->toBeTrue();
});
