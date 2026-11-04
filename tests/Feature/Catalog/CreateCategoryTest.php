<?php

declare(strict_types=1);

use App\Domain\Catalog\Actions\Category\CreateCategory;
use App\Domain\Catalog\Data\Category\CreateCategoryData;
use App\Domain\Catalog\Exceptions\CategoryRuleViolation;
use App\Domain\Catalog\Models\Category;
use Database\Factories\CategoryFactory;
use Database\Factories\UserFactory;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Spatie\Activitylog\Models\Activity;

use function Pest\Laravel\assertDatabaseCount;
use function Pest\Laravel\assertDatabaseHas;

it('creates a root category with defaults and sequential sort order', function (): void {
    $user = UserFactory::new()->create();
    $action = new CreateCategory;

    $cat1 = $action(new CreateCategoryData(name: 'Electronics'), $user);
    $cat2 = $action(new CreateCategoryData(name: 'Appliances'), $user);

    expect($cat1->public_id)->toMatch('/^[0-9a-hjkmnp-tv-z]{26}$/i')
        ->and($cat1->parent_id)->toBeNull()
        ->and($cat1->name)->toBe('Electronics')
        ->and($cat1->slug)->toBe('electronics')
        ->and($cat1->sort_order)->toBe(0)
        ->and($cat1->show_in_nav)->toBeTrue()
        ->and($cat1->is_active)->toBeTrue()
        ->and($cat1->created_by)->toBe($user->id)
        ->and($cat1->updated_by)->toBe($user->id)
        ->and($cat2->sort_order)->toBe(1)
        ->and($cat2->slug)->toBe('appliances');
});

it('creates a subcategory under a root parent', function (): void {
    $user = UserFactory::new()->create();
    $root = CategoryFactory::new()->root()->create(['name' => 'Tech', 'slug' => 'tech']);
    $action = new CreateCategory;

    $child = $action(new CreateCategoryData(name: 'Smartphones', parent_public_id: $root->public_id), $user);

    expect($child->parent_id)->toBe($root->id)
        ->and($child->sort_order)->toBe(0)
        ->and($child->slug)->toBe('smartphones');
});

it('rejects creating a child under a parent already at MAX_DEPTH', function (): void {
    $user = UserFactory::new()->create();
    $root = CategoryFactory::new()->root()->create(['name' => 'Root Cat', 'slug' => 'root-cat']);
    $child = CategoryFactory::new()->child($root)->create(['name' => 'Child Cat', 'slug' => 'child-cat']);
    expect(Category::MAX_DEPTH)->toBe(2);

    $action = new CreateCategory;

    expect(fn () => $action(new CreateCategoryData(name: 'Accessories', parent_public_id: $child->public_id), $user))
        ->toThrow(CategoryRuleViolation::class, 'Category nesting exceeds maximum depth of 2.');

    assertDatabaseCount('categories', 2);
});

it('resolves slug collisions with numeric suffix across different parents', function (): void {
    $user = UserFactory::new()->create();
    $rootA = CategoryFactory::new()->root()->create(['name' => 'Parent A', 'slug' => 'parent-a']);
    $rootB = CategoryFactory::new()->root()->create(['name' => 'Parent B', 'slug' => 'parent-b']);
    $action = new CreateCategory;

    $childA = $action(new CreateCategoryData(name: 'Smart Phones', parent_public_id: $rootA->public_id), $user);
    $childB = $action(new CreateCategoryData(name: 'Smart Phones', parent_public_id: $rootB->public_id), $user);

    expect($childA->slug)->toBe('smart-phones')
        ->and($childB->slug)->toBe('smart-phones-2');
});

it('rejects names that generate an empty slug', function (): void {
    $user = UserFactory::new()->create();
    $action = new CreateCategory;

    expect(fn () => $action(new CreateCategoryData(name: '!!!'), $user))
        ->toThrow(CategoryRuleViolation::class, 'generates an empty slug');

    assertDatabaseCount('categories', 0);
});

it('rejects duplicate sibling name ignoring case under the same parent', function (): void {
    CategoryFactory::new()->root()->create(['name' => 'Computers', 'slug' => 'computers']);
    $action = new CreateCategory;

    expect(fn () => $action(new CreateCategoryData(name: 'COMPUTERS')))
        ->toThrow(CategoryRuleViolation::class, 'already exists under parent');

    assertDatabaseCount('categories', 1);
});

it('allows same name under different parents', function (): void {
    $user = UserFactory::new()->create();
    $rootA = CategoryFactory::new()->root()->create(['name' => 'Apple', 'slug' => 'apple']);
    $rootB = CategoryFactory::new()->root()->create(['name' => 'Samsung', 'slug' => 'samsung']);
    $action = new CreateCategory;

    $childA = $action(new CreateCategoryData(name: 'Phones', parent_public_id: $rootA->public_id), $user);
    $childB = $action(new CreateCategoryData(name: 'Phones', parent_public_id: $rootB->public_id), $user);

    expect($childA->name)->toBe('Phones')
        ->and($childB->name)->toBe('Phones')
        ->and($childA->slug)->toBe('phones')
        ->and($childB->slug)->toBe('phones-2')
        ->and($childA->parent_id)->toBe($rootA->id)
        ->and($childB->parent_id)->toBe($rootB->id);
});

it('rejects invalid names and unknown parent public ids during creation', function (): void {
    $invalidInputs = [
        ['name' => ''],
        ['name' => 'a'],
        ['name' => str_repeat('a', 81)],
        ['name' => "control\x00char"],
        ['name' => 'Valid Name', 'parent_public_id' => '01NONEXISTENT0000000000000'],
    ];

    foreach ($invalidInputs as $input) {
        expect(fn () => CreateCategoryData::validateAndCreate($input))
            ->toThrow(ValidationException::class);
    }

    assertDatabaseCount('categories', 0);
});

it('enforces database check constraints and unique indexes on raw inserts', function (): void {
    $baseline = CategoryFactory::new()->create(['name' => 'Existing Root', 'slug' => 'existing-root']);

    // 1. Negative sort_order check
    expect(fn () => DB::table('categories')->insert([
        'public_id' => (string) Str::ulid(),
        'parent_id' => null,
        'name' => 'Negative Sort',
        'slug' => 'negative-sort',
        'sort_order' => -1,
        'show_in_nav' => true,
        'is_active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]))->toThrow(QueryException::class);

    // 2. Parent cannot reference itself check (tested via UPDATE)
    expect(fn () => DB::table('categories')->where('id', $baseline->id)->update([
        'parent_id' => $baseline->id,
    ]))->toThrow(QueryException::class);

    // 3. Slug regex format check
    expect(fn () => DB::table('categories')->insert([
        'public_id' => (string) Str::ulid(),
        'parent_id' => null,
        'name' => 'Invalid Slug',
        'slug' => 'Slug With Spaces',
        'sort_order' => 0,
        'show_in_nav' => true,
        'is_active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]))->toThrow(QueryException::class);

    // 4. Duplicate slug unique constraint
    expect(fn () => DB::table('categories')->insert([
        'public_id' => (string) Str::ulid(),
        'parent_id' => null,
        'name' => 'Duplicate Slug Root',
        'slug' => 'existing-root',
        'sort_order' => 0,
        'show_in_nav' => true,
        'is_active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]))->toThrow(QueryException::class);

    // 5. Sibling name unique expression index (COALESCE(parent_id, 0), lower(name))
    expect(fn () => DB::table('categories')->insert([
        'public_id' => (string) Str::ulid(),
        'parent_id' => null,
        'name' => 'EXISTING ROOT',
        'slug' => 'other-slug',
        'sort_order' => 0,
        'show_in_nav' => true,
        'is_active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]))->toThrow(QueryException::class);
});

it('records category.created activity log with causer and exact properties', function (): void {
    $user = UserFactory::new()->create();
    $action = new CreateCategory;

    $category = $action(new CreateCategoryData(name: 'Laptops'), $user);

    assertDatabaseHas('activity_log', [
        'log_name' => 'catalog',
        'event' => 'category.created',
        'causer_id' => $user->id,
        'subject_id' => $category->id,
    ]);

    /** @var Activity $log */
    $log = Activity::where('log_name', 'catalog')->where('event', 'category.created')->latest('id')->firstOrFail();
    expect($log->properties->toArray())->toBe([
        'public_id' => $category->public_id,
        'name' => 'Laptops',
        'slug' => 'laptops',
        'parent_public_id' => null,
    ]);
});

it('writes no activity log on failed operations', function (): void {
    $user = UserFactory::new()->create();
    $root = CategoryFactory::new()->root()->create();
    $child = CategoryFactory::new()->child($root)->create();

    $initialLogCount = Activity::count();
    $action = new CreateCategory;

    try {
        $action(new CreateCategoryData(name: 'Overflow', parent_public_id: $child->public_id), $user);
    } catch (CategoryRuleViolation) {
        // Expected violation
    }

    expect(Activity::count())->toBe($initialLogCount);
});
