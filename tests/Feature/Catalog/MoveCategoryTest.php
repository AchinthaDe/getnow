<?php

declare(strict_types=1);

use App\Domain\Catalog\Actions\Category\MoveCategory;
use App\Domain\Catalog\Data\Category\MoveCategoryData;
use App\Domain\Catalog\Exceptions\CategoryRuleViolation;
use App\Domain\Catalog\Models\Category;
use Database\Factories\CategoryFactory;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Spatie\Activitylog\Models\Activity;

afterEach(function (): void {
    Carbon::setTestNow();
});

it('moves a leaf category under a root setting sequential sort order and logging category.moved', function (): void {
    $user = UserFactory::new()->create();
    $rootA = CategoryFactory::new()->root()->create(['name' => 'Root A']);
    $childX = CategoryFactory::new()->child($rootA)->create(['name' => 'Child X', 'sort_order' => 0]);
    $siblingUnderA = CategoryFactory::new()->child($rootA)->create(['name' => 'Sibling Under A', 'sort_order' => 1]);

    $rootB = CategoryFactory::new()->root()->create(['name' => 'Root B']);
    $childY = CategoryFactory::new()->child($rootB)->create(['name' => 'Child Y', 'sort_order' => 0]);
    $childZ = CategoryFactory::new()->child($rootB)->create(['name' => 'Child Z', 'sort_order' => 1]);

    Carbon::setTestNow(now()->addHour());
    $originalSiblingUnderAUpdatedAt = $siblingUnderA->fresh()->updated_at?->toISOString();
    $originalChildYUpdatedAt = $childY->fresh()->updated_at?->toISOString();
    $originalChildZUpdatedAt = $childZ->fresh()->updated_at?->toISOString();

    (new MoveCategory)($childX->public_id, new MoveCategoryData(parent_public_id: $rootB->public_id), $user);

    $freshChildX = $childX->fresh();
    expect($freshChildX->parent_id)->toBe($rootB->id)
        ->and($freshChildX->sort_order)->toBe(2)
        ->and($freshChildX->slug)->toBe($childX->slug)
        ->and($freshChildX->is_active)->toBe($childX->is_active)
        ->and($freshChildX->updated_by)->toBe($user->id);

    // Old sibling under root A retains original parent_id, sort_order, updated_at
    $freshSiblingUnderA = $siblingUnderA->fresh();
    expect($freshSiblingUnderA->parent_id)->toBe($rootA->id)
        ->and($freshSiblingUnderA->sort_order)->toBe(1)
        ->and($freshSiblingUnderA->updated_at?->toISOString())->toBe($originalSiblingUnderAUpdatedAt);

    // Siblings Y and Z retain original parent_id, sort_order, updated_at
    $freshY = $childY->fresh();
    $freshZ = $childZ->fresh();
    expect($freshY->parent_id)->toBe($rootB->id)
        ->and($freshY->sort_order)->toBe(0)
        ->and($freshY->updated_at?->toISOString())->toBe($originalChildYUpdatedAt)
        ->and($freshZ->parent_id)->toBe($rootB->id)
        ->and($freshZ->sort_order)->toBe(1)
        ->and($freshZ->updated_at?->toISOString())->toBe($originalChildZUpdatedAt);

    expect(Activity::count())->toBe(1);
    $log = Activity::latest('id')->firstOrFail();
    expect($log->event)->toBe('category.moved')
        ->and($log->log_name)->toBe('catalog')
        ->and($log->causer_id)->toBe($user->id)
        ->and($log->properties->toArray())->toBe([
            'public_id' => $childX->public_id,
            'old_parent_public_id' => $rootA->public_id,
            'new_parent_public_id' => $rootB->public_id,
            'old_sort_order' => 0,
            'new_sort_order' => 2,
        ]);
});

it('moves a child category to the root level placing it last among root siblings', function (): void {
    CategoryFactory::new()->root()->create(['name' => 'Root A', 'sort_order' => 0]);
    $rootB = CategoryFactory::new()->root()->create(['name' => 'Root B', 'sort_order' => 1]);
    $childX = CategoryFactory::new()->child($rootB)->create(['name' => 'Child X', 'sort_order' => 0]);

    (new MoveCategory)($childX->public_id, new MoveCategoryData(parent_public_id: null));

    $freshChildX = $childX->fresh();
    expect($freshChildX->parent_id)->toBeNull()
        ->and($freshChildX->sort_order)->toBe(2);
});

it('treats moving a category to its current parent as a no-op', function (): void {
    $rootA = CategoryFactory::new()->root()->create(['name' => 'Root A']);
    $childX = CategoryFactory::new()->child($rootA)->create(['name' => 'Child X', 'sort_order' => 0]);

    Carbon::setTestNow(now()->addHour());
    $updatedAtBefore = $childX->fresh()->updated_at?->toISOString();

    DB::enableQueryLog();
    DB::flushQueryLog();

    (new MoveCategory)($childX->public_id, new MoveCategoryData(parent_public_id: $rootA->public_id));

    $queries = DB::getQueryLog();
    $updateQueries = array_filter(
        $queries,
        fn (array $q): bool => str_starts_with(strtoupper((string) $q['query']), 'UPDATE')
    );

    expect($updateQueries)->toBeEmpty()
        ->and($childX->fresh()->updated_at?->toISOString())->toBe($updatedAtBefore)
        ->and(Activity::count())->toBe(0);

    DB::disableQueryLog();
});

it('rejects moving a category under itself or one of its descendants with cycle violation', function (): void {
    $rootA = CategoryFactory::new()->root()->create(['name' => 'Root A']);
    $childB = CategoryFactory::new()->child($rootA)->create(['name' => 'Child B']);

    // Moving root under itself
    expect(fn () => (new MoveCategory)($rootA->public_id, new MoveCategoryData(parent_public_id: $rootA->public_id)))
        ->toThrow(CategoryRuleViolation::class, "Cannot move category [{$rootA->public_id}] under itself or its descendant [{$rootA->public_id}].");

    expect(Activity::count())->toBe(0)
        ->and($rootA->fresh()->parent_id)->toBeNull();

    // Moving root under its child
    expect(fn () => (new MoveCategory)($rootA->public_id, new MoveCategoryData(parent_public_id: $childB->public_id)))
        ->toThrow(CategoryRuleViolation::class, "Cannot move category [{$rootA->public_id}] under itself or its descendant [{$childB->public_id}].");

    expect(Activity::count())->toBe(0)
        ->and($rootA->fresh()->parent_id)->toBeNull();
});

it('enforces Category MAX_DEPTH constraint dynamically when reparenting subtrees', function (): void {
    $maxDepth = (int) (new ReflectionClass(Category::class))->getConstant('MAX_DEPTH');

    // Build first chain of Category::MAX_DEPTH levels
    $chain1Root = CategoryFactory::new()->root()->create(['name' => 'Chain1 Level 1']);
    $current1 = $chain1Root;
    for ($depth = 2; $depth <= $maxDepth; $depth++) {
        $current1 = CategoryFactory::new()->child($current1)->create(['name' => "Chain1 Level {$depth}"]);
    }

    // Build second root with chain of Category::MAX_DEPTH - 1 levels, keeping deepest node D
    $chain2Root = CategoryFactory::new()->root()->create(['name' => 'Chain2 Level 1']);
    $nodeD = $chain2Root;
    for ($depth = 2; $depth <= ($maxDepth - 1); $depth++) {
        $nodeD = CategoryFactory::new()->child($nodeD)->create(['name' => "Chain2 Level {$depth}"]);
    }

    // Standalone leaf L
    $leafL = CategoryFactory::new()->root()->create(['name' => 'Leaf L']);

    // Attempting to move top category of first chain under second root throws maxDepthExceeded
    expect(fn () => (new MoveCategory)($chain1Root->public_id, new MoveCategoryData(parent_public_id: $chain2Root->public_id)))
        ->toThrow(CategoryRuleViolation::class, 'Category nesting exceeds maximum depth of '.Category::MAX_DEPTH.'.');

    expect(Activity::count())->toBe(0)
        ->and($chain1Root->fresh()->parent_id)->toBeNull();

    // Moving leaf category L under node D succeeds
    (new MoveCategory)($leafL->public_id, new MoveCategoryData(parent_public_id: $nodeD->public_id));
    expect($leafL->fresh()->parent_id)->toBe($nodeD->id);
});

it('rejects moving into a parent that already contains a sibling with the same name ignoring case', function (): void {
    $rootA = CategoryFactory::new()->root()->create(['name' => 'Root A']);
    $childA = CategoryFactory::new()->child($rootA)->create(['name' => 'Phones']);

    $rootB = CategoryFactory::new()->root()->create(['name' => 'Root B']);
    CategoryFactory::new()->child($rootB)->create(['name' => 'PHONES']);

    expect(fn () => (new MoveCategory)($childA->public_id, new MoveCategoryData(parent_public_id: $rootB->public_id)))
        ->toThrow(CategoryRuleViolation::class, "A sibling category named [Phones] already exists under parent [{$rootB->public_id}].");

    expect(Activity::count())->toBe(0)
        ->and($childA->fresh()->parent_id)->toBe($rootA->id);
});

it('fails validation when destination parent_public_id does not exist', function (): void {
    $root = CategoryFactory::new()->root()->create(['name' => 'Root']);

    expect(fn () => MoveCategoryData::validateAndCreate(['parent_public_id' => 'nonexistent_ulid']))
        ->toThrow(ValidationException::class);

    expect(Activity::count())->toBe(0)
        ->and($root->fresh()->parent_id)->toBeNull();
});

it('throws ModelNotFoundException when target category public_id is unknown without modifying database or writing logs', function (): void {
    $randomUlid = (string) Str::ulid();
    $countBefore = Category::count();

    expect(fn () => (new MoveCategory)($randomUlid, new MoveCategoryData(parent_public_id: null)))
        ->toThrow(ModelNotFoundException::class);

    expect(Category::count())->toBe($countBefore)
        ->and(Activity::count())->toBe(0);
});
