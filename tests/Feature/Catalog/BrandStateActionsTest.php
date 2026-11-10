<?php

declare(strict_types=1);

use App\Domain\Catalog\Actions\Brand\ActivateBrand;
use App\Domain\Catalog\Actions\Brand\DeactivateBrand;
use App\Domain\Catalog\Models\Brand;
use Database\Factories\BrandFactory;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Activity;

use function Pest\Laravel\assertDatabaseHas;

it('activates an inactive brand, stamps updater, and logs brand.activated', function (): void {
    $user = UserFactory::new()->create();
    $brand = BrandFactory::new()->inactive()->create();

    $action = new ActivateBrand;
    $action($brand->public_id, $user);

    $fresh = $brand->fresh();
    expect($fresh)->not->toBeNull()
        ->and($fresh->is_active)->toBeTrue()
        ->and($fresh->updated_by)->toBe($user->id);

    assertDatabaseHas('brands', [
        'id' => $brand->id,
        'is_active' => true,
        'updated_by' => $user->id,
    ]);

    $activity = Activity::where('log_name', 'catalog')
        ->where('event', 'brand.activated')
        ->where('subject_type', Brand::class)
        ->where('subject_id', $brand->id)
        ->first();

    expect($activity)->not->toBeNull()
        ->and($activity->description)->toBe('brand.activated')
        ->and($activity->causer_id)->toBe($user->id)
        ->and($activity->properties->toArray())->toBe([
            'public_id' => $brand->public_id,
            'old_is_active' => false,
            'new_is_active' => true,
        ]);
});

it('deactivates an active brand, stamps updater, and logs brand.deactivated', function (): void {
    $user = UserFactory::new()->create();
    $brand = BrandFactory::new()->create();

    $action = new DeactivateBrand;
    $action($brand->public_id, $user);

    $fresh = $brand->fresh();
    expect($fresh)->not->toBeNull()
        ->and($fresh->is_active)->toBeFalse()
        ->and($fresh->updated_by)->toBe($user->id);

    assertDatabaseHas('brands', [
        'id' => $brand->id,
        'is_active' => false,
        'updated_by' => $user->id,
    ]);

    $activity = Activity::where('log_name', 'catalog')
        ->where('event', 'brand.deactivated')
        ->where('subject_type', Brand::class)
        ->where('subject_id', $brand->id)
        ->first();

    expect($activity)->not->toBeNull()
        ->and($activity->description)->toBe('brand.deactivated')
        ->and($activity->causer_id)->toBe($user->id)
        ->and($activity->properties->toArray())->toBe([
            'public_id' => $brand->public_id,
            'old_is_active' => true,
            'new_is_active' => false,
        ]);
});

it('treats activation of active brand and deactivation of inactive brand as no-ops', function (): void {
    $activeBrand = BrandFactory::new()->create();
    $inactiveBrand = BrandFactory::new()->inactive()->create();

    DB::enableQueryLog();

    $activateAction = new ActivateBrand;
    $activateAction($activeBrand->public_id);

    $deactivateAction = new DeactivateBrand;
    $deactivateAction($inactiveBrand->public_id);

    $queries = DB::getQueryLog();
    $updateQueries = array_filter($queries, fn (array $q) => str_starts_with(strtoupper((string) $q['query']), 'UPDATE'));

    expect($updateQueries)->toBeEmpty()
        ->and(Activity::count())->toBe(0);
});

it('throws model not found exception for non-existent public ID', function (): void {
    $randomUlid = (string) Str::ulid();

    $activateAction = new ActivateBrand;
    expect(fn () => $activateAction($randomUlid))->toThrow(ModelNotFoundException::class);

    $deactivateAction = new DeactivateBrand;
    expect(fn () => $deactivateAction($randomUlid))->toThrow(ModelNotFoundException::class);
});
