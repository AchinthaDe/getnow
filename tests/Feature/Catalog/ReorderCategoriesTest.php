<?php

declare(strict_types=1);

use App\Domain\Catalog\Actions\Category\GetCategoryTree;
use App\Domain\Catalog\Actions\Category\ReorderCategories;
use App\Domain\Catalog\Data\Category\ReorderCategoriesData;
use App\Domain\Catalog\Enums\CategoryTreeAudience;
use App\Domain\Catalog\Exceptions\CategoryRuleViolation;
use Database\Factories\CategoryFactory;
use Database\Factories\UserFactory;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Spatie\Activitylog\Models\Activity;

afterEach(function (): void {
    Carbon::setTestNow();
});

it('reorders child siblings sequentially and records category.reordered activity log', function (): void {
    $user = UserFactory::new()->create();
    $root = CategoryFactory::new()->root()->create(['name' => 'Root']);
    $c1 = CategoryFactory::new()->child($root)->create(['name' => 'C1', 'sort_order' => 0]);
    $c2 = CategoryFactory::new()->child($root)->create(['name' => 'C2', 'sort_order' => 1]);
    $c3 = CategoryFactory::new()->child($root)->create(['name' => 'C3', 'sort_order' => 2]);

    Carbon::setTestNow(now()->addHour());
    $c2UpdatedAt = $c2->fresh()->updated_at?->toISOString();

    (new ReorderCategories)(
        new ReorderCategoriesData(
            parent_public_id: $root->public_id,
            ordered_public_ids: [$c3->public_id, $c2->public_id, $c1->public_id]
        ),
        $user
    );

    expect($c3->fresh()->sort_order)->toBe(0)
        ->and($c2->fresh()->sort_order)->toBe(1)
        ->and($c2->fresh()->updated_at?->toISOString())->toBe($c2UpdatedAt)
        ->and($c1->fresh()->sort_order)->toBe(2);

    expect(Activity::count())->toBe(1);
    $log = Activity::latest('id')->firstOrFail();
    expect($log->event)->toBe('category.reordered')
        ->and($log->log_name)->toBe('catalog')
        ->and($log->causer_id)->toBe($user->id)
        ->and($log->subject_id)->toBe($root->id)
        ->and($log->properties->toArray())->toBe([
            'parent_public_id' => $root->public_id,
            'old_ordered_public_ids' => [$c1->public_id, $c2->public_id, $c3->public_id],
            'new_ordered_public_ids' => [$c3->public_id, $c2->public_id, $c1->public_id],
        ]);

    $tree = (new GetCategoryTree)(CategoryTreeAudience::Admin);
    expect($tree[0]->children[0]->public_id)->toBe($c3->public_id)
        ->and($tree[0]->children[1]->public_id)->toBe($c2->public_id)
        ->and($tree[0]->children[2]->public_id)->toBe($c1->public_id);
});

it('reorders root categories sequentially and records category.reordered activity log', function (): void {
    $user = UserFactory::new()->create();
    $r1 = CategoryFactory::new()->root()->create(['name' => 'R1', 'sort_order' => 0]);
    $r2 = CategoryFactory::new()->root()->create(['name' => 'R2', 'sort_order' => 1]);
    $r3 = CategoryFactory::new()->root()->create(['name' => 'R3', 'sort_order' => 2]);

    Carbon::setTestNow(now()->addHour());

    (new ReorderCategories)(
        new ReorderCategoriesData(
            parent_public_id: null,
            ordered_public_ids: [$r3->public_id, $r1->public_id, $r2->public_id]
        ),
        $user
    );

    expect($r3->fresh()->sort_order)->toBe(0)
        ->and($r1->fresh()->sort_order)->toBe(1)
        ->and($r2->fresh()->sort_order)->toBe(2);

    expect(Activity::count())->toBe(1);
    $log = Activity::latest('id')->firstOrFail();
    expect($log->event)->toBe('category.reordered')
        ->and($log->log_name)->toBe('catalog')
        ->and($log->causer_id)->toBe($user->id)
        ->and($log->subject_id)->toBeNull()
        ->and($log->properties->toArray())->toBe([
            'parent_public_id' => null,
            'old_ordered_public_ids' => [$r1->public_id, $r2->public_id, $r3->public_id],
            'new_ordered_public_ids' => [$r3->public_id, $r1->public_id, $r2->public_id],
        ]);
});

it('treats identical sibling order submission as a no-op', function (): void {
    $r1 = CategoryFactory::new()->root()->create(['name' => 'R1', 'sort_order' => 0]);
    $r2 = CategoryFactory::new()->root()->create(['name' => 'R2', 'sort_order' => 1]);

    Carbon::setTestNow(now()->addHour());
    $r1UpdatedAt = $r1->fresh()->updated_at?->toISOString();
    $r2UpdatedAt = $r2->fresh()->updated_at?->toISOString();

    DB::enableQueryLog();
    DB::flushQueryLog();

    (new ReorderCategories)(
        new ReorderCategoriesData(
            parent_public_id: null,
            ordered_public_ids: [$r1->public_id, $r2->public_id]
        )
    );

    $queries = DB::getQueryLog();
    $updateQueries = array_filter(
        $queries,
        fn (array $q): bool => str_starts_with(strtoupper((string) $q['query']), 'UPDATE')
    );

    expect($updateQueries)->toBeEmpty()
        ->and($r1->fresh()->updated_at?->toISOString())->toBe($r1UpdatedAt)
        ->and($r2->fresh()->updated_at?->toISOString())->toBe($r2UpdatedAt)
        ->and(Activity::count())->toBe(0);

    DB::disableQueryLog();
});

it('rejects reorder submissions with missing extra duplicate or foreign sibling IDs', function (): void {
    $rootA = CategoryFactory::new()->root()->create(['name' => 'Root A']);
    $c1 = CategoryFactory::new()->child($rootA)->create(['name' => 'C1', 'sort_order' => 0]);
    $c2 = CategoryFactory::new()->child($rootA)->create(['name' => 'C2', 'sort_order' => 1]);

    $rootB = CategoryFactory::new()->root()->create(['name' => 'Root B']);
    $c3 = CategoryFactory::new()->child($rootB)->create(['name' => 'C3', 'sort_order' => 0]);

    $unknownUlid = (string) Str::ulid();

    // Missing C2
    expect(fn () => (new ReorderCategories)(
        new ReorderCategoriesData(parent_public_id: $rootA->public_id, ordered_public_ids: [$c1->public_id])
    ))->toThrow(CategoryRuleViolation::class, "The ordered list of category IDs does not match the current siblings of parent [{$rootA->public_id}].");

    // Extra C3 (foreign sibling)
    expect(fn () => (new ReorderCategories)(
        new ReorderCategoriesData(parent_public_id: $rootA->public_id, ordered_public_ids: [$c1->public_id, $c2->public_id, $c3->public_id])
    ))->toThrow(CategoryRuleViolation::class, "The ordered list of category IDs does not match the current siblings of parent [{$rootA->public_id}].");

    // Duplicate C1
    expect(fn () => (new ReorderCategories)(
        new ReorderCategoriesData(parent_public_id: $rootA->public_id, ordered_public_ids: [$c1->public_id, $c1->public_id])
    ))->toThrow(CategoryRuleViolation::class, "The ordered list of category IDs does not match the current siblings of parent [{$rootA->public_id}].");

    // Unknown ULID
    expect(fn () => (new ReorderCategories)(
        new ReorderCategoriesData(parent_public_id: $rootA->public_id, ordered_public_ids: [$c1->public_id, $unknownUlid])
    ))->toThrow(CategoryRuleViolation::class, "The ordered list of category IDs does not match the current siblings of parent [{$rootA->public_id}].");

    // Assert database sort_order unchanged and Activity::count() === 0
    expect($c1->fresh()->sort_order)->toBe(0)
        ->and($c2->fresh()->sort_order)->toBe(1)
        ->and(Activity::count())->toBe(0);
});

it('fails validation when parent_public_id does not exist', function (): void {
    expect(fn () => ReorderCategoriesData::validateAndCreate([
        'parent_public_id' => 'nonexistent_ulid',
        'ordered_public_ids' => ['some_id'],
    ]))->toThrow(ValidationException::class);
});
