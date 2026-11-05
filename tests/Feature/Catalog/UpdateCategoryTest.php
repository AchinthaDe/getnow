<?php

declare(strict_types=1);

use App\Domain\Catalog\Actions\Category\UpdateCategory;
use App\Domain\Catalog\Data\Category\UpdateCategoryData;
use App\Domain\Catalog\Exceptions\CategoryRuleViolation;
use Database\Factories\CategoryFactory;
use Database\Factories\UserFactory;
use Illuminate\Validation\ValidationException;
use Spatie\Activitylog\Models\Activity;

use function Pest\Laravel\assertDatabaseHas;

it('updates category name, show_in_nav, and sort_order while preserving slug and parent_id', function (): void {
    $user = UserFactory::new()->create();
    $category = CategoryFactory::new()->create([
        'name' => 'Old Name',
        'slug' => 'old-name',
        'parent_id' => null,
        'sort_order' => 0,
        'show_in_nav' => true,
    ]);

    $action = new UpdateCategory;
    $updated = $action($category, new UpdateCategoryData(name: 'New Name', show_in_nav: false, sort_order: 5), $user);

    $fresh = $category->fresh();
    expect($fresh->name)->toBe('New Name')
        ->and($fresh->show_in_nav)->toBeFalse()
        ->and($fresh->sort_order)->toBe(5)
        ->and($fresh->slug)->toBe('old-name')
        ->and($fresh->parent_id)->toBeNull()
        ->and($fresh->updated_by)->toBe($user->id)
        ->and($updated->id)->toBe($category->id);
});

it('updates category using public_id string as first parameter', function (): void {
    $user = UserFactory::new()->create();
    $category = CategoryFactory::new()->create([
        'name' => 'Original Name',
        'slug' => 'original-name',
    ]);

    $action = new UpdateCategory;
    $updated = $action($category->public_id, new UpdateCategoryData(name: 'Renamed By String'), $user);

    expect($updated->fresh()->name)->toBe('Renamed By String');
});

it('rejects renaming that clashes with an existing sibling', function (): void {
    $user = UserFactory::new()->create();
    $root = CategoryFactory::new()->root()->create(['name' => 'Root Cat', 'slug' => 'root-cat']);
    $tablets = CategoryFactory::new()->child($root)->create(['name' => 'Tablets', 'slug' => 'tablets']);
    $laptops = CategoryFactory::new()->child($root)->create(['name' => 'Laptops', 'slug' => 'laptops']);

    $action = new UpdateCategory;

    expect(fn () => $action($tablets, new UpdateCategoryData(name: 'laptops'), $user))
        ->toThrow(CategoryRuleViolation::class, 'already exists under parent');

    expect($tablets->fresh()->name)->toBe('Tablets');
});

it('rejects negative sort order during update', function (): void {
    $category = CategoryFactory::new()->create(['sort_order' => 2]);

    expect(fn () => UpdateCategoryData::validateAndCreate(['sort_order' => -1]))
        ->toThrow(ValidationException::class);

    expect($category->fresh()->sort_order)->toBe(2);
});

it('records category.updated activity log with exact old and new properties', function (): void {
    $user = UserFactory::new()->create();
    $category = CategoryFactory::new()->create([
        'name' => 'Initial Name',
        'show_in_nav' => true,
    ]);

    $action = new UpdateCategory;
    $action($category, new UpdateCategoryData(name: 'Updated Name', show_in_nav: false), $user);

    assertDatabaseHas('activity_log', [
        'log_name' => 'catalog',
        'event' => 'category.updated',
        'causer_id' => $user->id,
        'subject_id' => $category->id,
    ]);

    /** @var Activity $log */
    $log = Activity::where('log_name', 'catalog')->where('event', 'category.updated')->latest('id')->firstOrFail();
    expect($log->properties->toArray())->toBe([
        'public_id' => $category->public_id,
        'old_name' => 'Initial Name',
        'new_name' => 'Updated Name',
        'old_show_in_nav' => true,
        'new_show_in_nav' => false,
    ]);
});

it('writes no activity log and does not save when no fields are changed', function (): void {
    $user = UserFactory::new()->create();
    $category = CategoryFactory::new()->create([
        'name' => 'Unchanged Name',
        'show_in_nav' => true,
        'sort_order' => 3,
    ]);

    $originalUpdatedAt = $category->fresh()->updated_at?->toISOString();
    $initialLogCount = Activity::count();

    $action = new UpdateCategory;
    $result = $action($category, new UpdateCategoryData, $user);

    expect(Activity::count())->toBe($initialLogCount)
        ->and($result->fresh()->updated_at?->toISOString())->toBe($originalUpdatedAt);
});

it('writes no activity log on failed operations', function (): void {
    $user = UserFactory::new()->create();
    $root = CategoryFactory::new()->root()->create(['name' => 'Root Cat', 'slug' => 'root-cat']);
    $catA = CategoryFactory::new()->child($root)->create(['name' => 'Cat A', 'slug' => 'cat-a']);
    $catB = CategoryFactory::new()->child($root)->create(['name' => 'Cat B', 'slug' => 'cat-b']);

    $initialLogCount = Activity::count();
    $action = new UpdateCategory;

    try {
        $action($catB, new UpdateCategoryData(name: 'cat a'), $user);
    } catch (CategoryRuleViolation) {
        // Expected
    }

    expect(Activity::count())->toBe($initialLogCount);
});
