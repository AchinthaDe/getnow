<?php

declare(strict_types=1);

use App\Domain\Catalog\Actions\Brand\CreateBrand;
use App\Domain\Catalog\Data\Brand\CreateBrandData;
use App\Domain\Catalog\Exceptions\BrandRuleViolation;
use App\Domain\Catalog\Models\Brand;
use Database\Factories\BrandFactory;
use Database\Factories\UserFactory;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Validation\ValidationException;
use Spatie\Activitylog\Models\Activity;

use function Pest\Laravel\assertDatabaseCount;
use function Pest\Laravel\assertDatabaseHas;

it('creates an active brand with normalized name and derived slug', function (): void {
    $user = UserFactory::new()->create();
    $action = new CreateBrand;

    $dto = CreateBrandData::from(['name' => '  Sony  ']);
    $brand = $action($dto, $user);

    expect($brand->public_id)->toMatch('/^[0-9a-hjkmnp-tv-z]{26}$/i')
        ->and($brand->name)->toBe('Sony')
        ->and($brand->slug)->toBe('sony')
        ->and($brand->is_active)->toBeTrue()
        ->and($brand->show_in_nav)->toBeFalse()
        ->and($brand->created_by)->toBe($user->id)
        ->and($brand->updated_by)->toBe($user->id);

    assertDatabaseHas('brands', [
        'id' => $brand->id,
        'name' => 'Sony',
        'slug' => 'sony',
        'is_active' => true,
        'show_in_nav' => false,
    ]);
});

it('resolves slug collision with numeric suffix', function (): void {
    BrandFactory::new()->create(['name' => 'Apple Inc', 'slug' => 'apple']);

    $action = new CreateBrand;
    $brand = $action(new CreateBrandData(name: 'Apple'));

    expect($brand->name)->toBe('Apple')
        ->and($brand->slug)->toBe('apple-2');
});

it('rejects name generating empty slug', function (): void {
    $action = new CreateBrand;

    expect(fn () => $action(new CreateBrandData(name: '???')))
        ->toThrow(BrandRuleViolation::class, 'Brand name [???] generates an empty slug.');

    assertDatabaseCount('brands', 0);
});

it('rejects duplicate brand name ignoring case', function (): void {
    BrandFactory::new()->create(['name' => 'Nokia', 'slug' => 'nokia']);

    $action = new CreateBrand;

    expect(fn () => $action(new CreateBrandData(name: 'NOKIA')))
        ->toThrow(BrandRuleViolation::class, 'A brand named [NOKIA] already exists.');

    assertDatabaseCount('brands', 1);
});

it('rejects names violating validation rules', function (): void {
    $invalidInputs = [
        ['name' => ''],
        ['name' => str_repeat('a', 81)],
        ['name' => "Bad\x07Name"],
        ['name' => '<script>alert(1)</script>'],
    ];

    foreach ($invalidInputs as $input) {
        expect(fn () => CreateBrandData::validateAndCreate($input))
            ->toThrow(ValidationException::class);
    }

    assertDatabaseCount('brands', 0);
});

it('collapses internal whitespace and trims names', function (): void {
    $dto = CreateBrandData::from(['name' => "  Google   Pixel\tElectronics  "]);

    expect($dto->name)->toBe('Google Pixel Electronics');
});

it('maps concurrent duplicate exception to BrandRuleViolation', function (): void {
    Brand::saving(function (): void {
        throw new UniqueConstraintViolationException(
            'pgsql',
            'insert into brands...',
            [],
            new Exception('duplicate')
        );
    });

    try {
        $action = new CreateBrand;
        expect(fn () => $action(new CreateBrandData(name: 'Sony')))
            ->toThrow(BrandRuleViolation::class, 'A brand named [Sony] already exists.');
    } finally {
        Brand::flushEventListeners();
    }
});

it('records brand.created activity log', function (): void {
    $user = UserFactory::new()->create();
    $action = new CreateBrand;

    $brand = $action(new CreateBrandData(name: 'Xiaomi', show_in_nav: true), $user);

    $activity = Activity::where('log_name', 'catalog')
        ->where('event', 'brand.created')
        ->where('subject_type', Brand::class)
        ->where('subject_id', $brand->id)
        ->first();

    expect($activity)->not->toBeNull()
        ->and($activity->description)->toBe('brand.created')
        ->and($activity->causer_id)->toBe($user->id)
        ->and($activity->properties->toArray())->toBe([
            'public_id' => $brand->public_id,
            'name' => 'Xiaomi',
            'slug' => 'xiaomi',
            'show_in_nav' => true,
        ]);
});
