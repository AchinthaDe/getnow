<?php

declare(strict_types=1);

use App\Domain\Catalog\Actions\Category\ActivateCategory;
use App\Domain\Catalog\Actions\Category\DeactivateCategory;
use App\Domain\Catalog\Data\Category\CreateCategoryData;
use App\Domain\Catalog\Data\Category\UpdateCategoryData;
use App\Domain\Catalog\Models\Category;
use Database\Factories\CategoryFactory;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Activity;

afterEach(function (): void {
    Carbon::setTestNow();
});

it('deactivates an active category, stamps updater, and logs category.deactivated', function (): void {
    $user = UserFactory::new()->create();
    $category = CategoryFactory::new()->root()->create(['is_active' => true]);

    (new DeactivateCategory)($category->public_id, $user);

    $category->refresh();
    expect($category->is_active)->toBeFalse()
        ->and($category->updated_by)->toBe($user->id)
        ->and(Activity::count())->toBe(1);

    $log = Activity::latest('id')->firstOrFail();
    expect($log->event)->toBe('category.deactivated')
        ->and($log->log_name)->toBe('catalog')
        ->and($log->causer_id)->toBe($user->id)
        ->and($log->properties->toArray())->toBe([
            'public_id' => $category->public_id,
            'old_is_active' => true,
            'new_is_active' => false,
        ]);
});

it('activates an inactive category, stamps updater, and logs category.activated', function (): void {
    $user = UserFactory::new()->create();
    $category = CategoryFactory::new()->inactive()->create();

    (new ActivateCategory)($category->public_id, $user);

    $category->refresh();
    expect($category->is_active)->toBeTrue()
        ->and($category->updated_by)->toBe($user->id)
        ->and(Activity::count())->toBe(1);

    $log = Activity::latest('id')->firstOrFail();
    expect($log->event)->toBe('category.activated')
        ->and($log->log_name)->toBe('catalog')
        ->and($log->causer_id)->toBe($user->id)
        ->and($log->properties->toArray())->toBe([
            'public_id' => $category->public_id,
            'old_is_active' => false,
            'new_is_active' => true,
        ]);
});

it('treats activation of active category and deactivation of inactive category as no-ops', function (): void {
    $activeCategory = CategoryFactory::new()->root()->create(['is_active' => true]);
    $inactiveCategory = CategoryFactory::new()->inactive()->create();

    Carbon::setTestNow(now()->addHour());
    $activeUpdatedAt = $activeCategory->fresh()->updated_at;
    $inactiveUpdatedAt = $inactiveCategory->fresh()->updated_at;

    DB::enableQueryLog();
    DB::flushQueryLog();

    (new ActivateCategory)($activeCategory->public_id);
    (new DeactivateCategory)($inactiveCategory->public_id);

    $queries = DB::getQueryLog();
    $updateQueries = array_filter(
        $queries,
        fn (array $q): bool => str_starts_with(strtoupper((string) $q['query']), 'UPDATE')
    );

    expect($updateQueries)->toBeEmpty()
        ->and($activeCategory->fresh()->updated_at?->toISOString())->toBe($activeUpdatedAt?->toISOString())
        ->and($inactiveCategory->fresh()->updated_at?->toISOString())->toBe($inactiveUpdatedAt?->toISOString())
        ->and(Activity::count())->toBe(0);

    DB::disableQueryLog();
});

it('deactivating a root category executes a single UPDATE and leaves children untouched while toggling effective active state', function (): void {
    $root = CategoryFactory::new()->root()->create(['is_active' => true]);
    $child = CategoryFactory::new()->child($root)->create(['is_active' => true]);

    Carbon::setTestNow(now()->addHour());
    $childUpdatedAt = $child->fresh()->updated_at;

    DB::enableQueryLog();
    DB::flushQueryLog();

    (new DeactivateCategory)($root->public_id);

    $queries = DB::getQueryLog();
    $updateQueries = array_filter(
        $queries,
        fn (array $q): bool => str_starts_with(strtoupper((string) $q['query']), 'UPDATE')
    );

    expect(count($updateQueries))->toBe(1);

    $freshChild = $child->fresh();
    expect($freshChild->updated_at?->toISOString())->toBe($childUpdatedAt?->toISOString())
        ->and($freshChild->is_active)->toBeTrue();

    $childWithEffective = Category::withEffectiveActive()->find($child->id);
    expect((bool) $childWithEffective?->is_effectively_active)->toBeFalse();

    DB::disableQueryLog();

    (new ActivateCategory)($root->public_id);
    $childReactivated = Category::withEffectiveActive()->find($child->id);
    expect((bool) $childReactivated?->is_effectively_active)->toBeTrue();
});

it('throws ModelNotFoundException when target public_id is unknown without modifying database or writing logs', function (): void {
    $randomUlid = (string) Str::ulid();
    $countBefore = Category::count();

    expect(fn () => (new ActivateCategory)($randomUlid))->toThrow(ModelNotFoundException::class);
    expect(fn () => (new DeactivateCategory)($randomUlid))->toThrow(ModelNotFoundException::class);

    expect(Category::count())->toBe($countBefore)
        ->and(Activity::count())->toBe(0);
});

it('verifies through reflection that category data classes do not declare is_active property', function (): void {
    $classes = [
        CreateCategoryData::class,
        UpdateCategoryData::class,
    ];

    if (class_exists('App\Domain\Catalog\Data\Category\MoveCategoryData')) {
        $classes[] = 'App\Domain\Catalog\Data\Category\MoveCategoryData';
    }

    if (class_exists('App\Domain\Catalog\Data\Category\ReorderCategoriesData')) {
        $classes[] = 'App\Domain\Catalog\Data\Category\ReorderCategoriesData';
    }

    foreach ($classes as $class) {
        $reflection = new ReflectionClass($class);
        expect($reflection->hasProperty('is_active'))->toBeFalse();
    }
});
