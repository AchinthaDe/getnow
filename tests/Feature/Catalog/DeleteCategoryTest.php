<?php

declare(strict_types=1);

use App\Domain\Catalog\Actions\Category\DeactivateCategory;
use App\Domain\Catalog\Actions\Category\DeleteCategory;
use App\Domain\Catalog\Exceptions\CategoryRuleViolation;
use App\Domain\Catalog\Models\Category;
use App\Domain\Content\Models\Menu;
use App\Domain\Content\Models\MenuItem;
use Database\Factories\CategoryFactory;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Activity;

it('deletes a childless category and records category.deleted activity log', function (): void {
    $user = UserFactory::new()->create();
    $root = CategoryFactory::new()->root()->create([
        'name' => 'Standalone Category',
    ]);

    (new DeleteCategory)($root->public_id, $user);

    expect(Category::whereKey($root->id)->exists())->toBeFalse();

    expect(Activity::count())->toBe(1);
    $log = Activity::latest('id')->firstOrFail();
    expect($log->event)->toBe('category.deleted')
        ->and($log->log_name)->toBe('catalog')
        ->and($log->causer_id)->toBe($user->id)
        ->and($log->properties->toArray())->toBe([
            'public_id' => $root->public_id,
            'name' => $root->name,
            'slug' => $root->slug,
            'parent_public_id' => null,
        ]);
});

it('deletes a child category, captures parent public_id via lookup under strict mode, and records category.deleted activity log', function (): void {
    $user = UserFactory::new()->create();
    $root = CategoryFactory::new()->root()->create(['name' => 'Parent Category']);
    $child = CategoryFactory::new()->child($root)->create(['name' => 'Child Category']);

    (new DeleteCategory)($child->public_id, $user);

    expect(Category::whereKey($child->id)->exists())->toBeFalse()
        ->and(Category::whereKey($root->id)->exists())->toBeTrue();

    expect(Activity::count())->toBe(1);
    $log = Activity::latest('id')->firstOrFail();
    expect($log->event)->toBe('category.deleted')
        ->and($log->log_name)->toBe('catalog')
        ->and($log->causer_id)->toBe($user->id)
        ->and($log->properties->toArray())->toBe([
            'public_id' => $child->public_id,
            'name' => $child->name,
            'slug' => $child->slug,
            'parent_public_id' => $root->public_id,
        ]);
});

it('refuses deletion of category having child rows', function (): void {
    $root = CategoryFactory::new()->root()->create(['name' => 'Root Category']);
    CategoryFactory::new()->child($root)->create([
        'name' => 'Inactive Child',
        'is_active' => false,
    ]);

    DB::enableQueryLog();
    DB::flushQueryLog();

    expect(fn () => (new DeleteCategory)($root->public_id))
        ->toThrow(CategoryRuleViolation::class, "Cannot delete category [{$root->public_id}] because it has child categories.");

    expect(Category::whereKey($root->id)->exists())->toBeTrue()
        ->and(Activity::count())->toBe(0);

    $queries = DB::getQueryLog();
    $childCheckQueries = array_filter(
        $queries,
        fn (array $q): bool => str_contains(strtolower((string) $q['query']), '"parent_id" =')
            || str_contains(strtolower((string) $q['query']), 'parent_id =')
    );
    expect($childCheckQueries)->not->toBeEmpty();

    DB::disableQueryLog();
});

it('throws ModelNotFoundException when target category public_id is unknown without modifying database or writing logs', function (): void {
    $randomUlid = (string) Str::ulid();
    $countBefore = Category::count();

    expect(fn () => (new DeleteCategory)($randomUlid))->toThrow(ModelNotFoundException::class);

    expect(Category::count())->toBe($countBefore)
        ->and(Activity::count())->toBe(0);
});

it('refuses deletion of category referenced by menu item and surfaces inuse violation', function (): void {
    $category = CategoryFactory::new()->root()->create(['name' => 'Referenced Category']);
    $menu = Menu::factory()->create();
    $menuItem = MenuItem::factory()->forMenu($menu)->category($category->public_id)->create();

    expect(fn () => (new DeleteCategory)($category->public_id))
        ->toThrow(CategoryRuleViolation::class, "Cannot delete category [{$category->public_id}] because it is in use. Deactivate it instead.");

    expect(Category::whereKey($category->id)->exists())->toBeTrue()
        ->and(MenuItem::whereKey($menuItem->id)->exists())->toBeTrue()
        ->and(Activity::where('event', 'category.deleted')->count())->toBe(0);
});

it('allows deactivating referenced category and allows deletion once menu item reference is removed', function (): void {
    $category = CategoryFactory::new()->root()->create(['name' => 'Referenced Category']);
    $menu = Menu::factory()->create();
    $menuItem = MenuItem::factory()->forMenu($menu)->category($category->public_id)->create();

    (new DeactivateCategory)($category->public_id);
    expect($category->fresh()?->is_active)->toBeFalse();

    $menuItem->delete();

    (new DeleteCategory)($category->public_id);
    expect(Category::whereKey($category->id)->exists())->toBeFalse();
});
