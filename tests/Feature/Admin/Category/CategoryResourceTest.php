<?php

declare(strict_types=1);

use App\Domain\Catalog\Models\Category;
use App\Domain\Content\Models\Menu;
use App\Domain\Content\Models\MenuItem;
use App\Filament\Admin\Resources\CategoryResource;
use App\Filament\Admin\Resources\CategoryResource\Pages\CreateCategory;
use App\Filament\Admin\Resources\CategoryResource\Pages\EditCategory;
use App\Filament\Admin\Resources\CategoryResource\Pages\ListCategories;
use Database\Factories\CategoryFactory;
use Database\Factories\UserFactory;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

it('renders list, create, and edit pages for platform admin via smoke test', function (): void {
    $admin = UserFactory::new()->platformAdmin()->create();
    $root = CategoryFactory::new()->root()->create();

    actingAs($admin);

    get(ListCategories::getUrl())->assertSuccessful();
    Livewire::test(ListCategories::class)->assertSuccessful();

    get(CategoryResource::getUrl('create'))->assertSuccessful();
    Livewire::test(CreateCategory::class)->assertSuccessful();

    get(CategoryResource::getUrl('edit', ['record' => $root]))->assertSuccessful();
    Livewire::test(EditCategory::class, ['record' => $root->public_id])->assertSuccessful();
});

it('authorizes platform admin to access category resource and forbids non-admin users', function (): void {
    $admin = UserFactory::new()->platformAdmin()->create();
    $nonAdmin = UserFactory::new()->create(['is_platform_admin' => false]);
    $root = CategoryFactory::new()->root()->create();

    // 1. Direct policy assertions
    expect($admin->can('viewAny', Category::class))->toBeTrue()
        ->and($admin->can('view', $root))->toBeTrue()
        ->and($admin->can('create', Category::class))->toBeTrue()
        ->and($admin->can('update', $root))->toBeTrue()
        ->and($admin->can('delete', $root))->toBeTrue()
        ->and($nonAdmin->can('viewAny', Category::class))->toBeFalse()
        ->and($nonAdmin->can('view', $root))->toBeFalse()
        ->and($nonAdmin->can('create', Category::class))->toBeFalse()
        ->and($nonAdmin->can('update', $root))->toBeFalse()
        ->and($nonAdmin->can('delete', $root))->toBeFalse();

    // 2. Route assertions as platform admin
    actingAs($admin);
    get('/admin/categories')->assertSuccessful();
    get('/admin/categories/create')->assertSuccessful();
    get('/admin/categories/'.$root->public_id.'/edit')->assertSuccessful();

    // 3. Route assertions as non-admin user
    actingAs($nonAdmin);
    get('/admin/categories')->assertForbidden();
    get('/admin/categories/create')->assertForbidden();
    get('/admin/categories/'.$root->public_id.'/edit')->assertForbidden();

    // 4. Guest access redirects
    auth()->logout();
    get('/admin/categories')->assertRedirect('/login');
});

it('renders categories in hierarchical tree order with effective active status and constant query count', function (): void {
    $admin = UserFactory::new()->platformAdmin()->create();
    actingAs($admin);

    $rootA = CategoryFactory::new()->root()->create(['name' => 'Root A', 'sort_order' => 0, 'is_active' => false]);
    $childA1 = CategoryFactory::new()->child($rootA)->create(['name' => 'Child A1', 'sort_order' => 0, 'is_active' => true]);
    $childA2 = CategoryFactory::new()->child($rootA)->create(['name' => 'Child A2', 'sort_order' => 1, 'is_active' => true]);
    $rootB = CategoryFactory::new()->root()->create(['name' => 'Root B', 'sort_order' => 1, 'is_active' => true]);
    $childB1 = CategoryFactory::new()->child($rootB)->create(['name' => 'Child B1', 'sort_order' => 0, 'is_active' => true]);

    DB::enableQueryLog();

    $component = Livewire::test(ListCategories::class);

    $queryCount = count(DB::getQueryLog());
    DB::disableQueryLog();

    expect($queryCount)->toBeLessThanOrEqual(5);

    $component->assertSuccessful()
        ->assertCanSeeTableRecords([$rootA, $childA1, $childA2, $rootB, $childB1], inOrder: true);
});

it('creates root and child categories via CreateCategory action and surfaces domain violations cleanly', function (): void {
    $admin = UserFactory::new()->platformAdmin()->create();
    actingAs($admin);

    // 1. Happy path: create root category
    Livewire::test(CreateCategory::class)
        ->fillForm([
            'name' => 'Root B',
            'parent_public_id' => null,
            'show_in_nav' => true,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    /** @var Category $rootB */
    $rootB = Category::where('name', 'Root B')->firstOrFail();
    expect($rootB->slug)->toBe('root-b')
        ->and($rootB->parent_id)->toBeNull()
        ->and($rootB->created_by)->toBe($admin->id)
        ->and($rootB->is_active)->toBeTrue();

    // 2. Happy path: create child category
    Livewire::test(CreateCategory::class)
        ->fillForm([
            'name' => 'Child B1',
            'parent_public_id' => $rootB->public_id,
            'show_in_nav' => true,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    /** @var Category $childB1 */
    $childB1 = Category::where('name', 'Child B1')->firstOrFail();
    expect($childB1->parent_id)->toBe($rootB->id);

    // 3. Failure path: duplicate sibling name clash
    Livewire::test(CreateCategory::class)
        ->fillForm([
            'name' => 'child b1',
            'parent_public_id' => $rootB->public_id,
            'show_in_nav' => true,
        ])
        ->call('create')
        ->assertNotified('Cannot Create Category');

    expect(Category::where('parent_id', $rootB->id)->count())->toBe(1);

    // 4. Failure path: max-depth exceeded (creating under child category from options list)
    Livewire::test(CreateCategory::class)
        ->fillForm([
            'name' => 'Sub Child B11',
            'parent_public_id' => $childB1->public_id,
            'show_in_nav' => true,
        ])
        ->call('create')
        ->assertNotified('Cannot Create Category');

    expect(Category::where('name', 'Sub Child B11')->exists())->toBeFalse();
});

it('edits category details via UpdateCategory while keeping slug immutable and surfaces domain violations cleanly', function (): void {
    $admin = UserFactory::new()->platformAdmin()->create();
    actingAs($admin);

    $root = CategoryFactory::new()->root()->create([
        'name' => 'Old Name',
        'slug' => 'old-name',
        'sort_order' => 0,
        'show_in_nav' => true,
    ]);
    $otherRoot = CategoryFactory::new()->root()->create(['name' => 'Other Root', 'slug' => 'other-root']);

    // 1. Happy path: edit attributes, slug unchanged
    Livewire::test(EditCategory::class, ['record' => $root->public_id])
        ->fillForm([
            'name' => 'New Name',
            'show_in_nav' => false,
            'sort_order' => 3,
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $root->refresh();
    expect($root->name)->toBe('New Name')
        ->and($root->show_in_nav)->toBeFalse()
        ->and($root->sort_order)->toBe(3)
        ->and($root->slug)->toBe('old-name');

    // 2. Failure path: sibling name clash with other root
    Livewire::test(EditCategory::class, ['record' => $root->public_id])
        ->fillForm([
            'name' => 'other root',
        ])
        ->call('save')
        ->assertNotified('Cannot Update Category');

    expect($root->fresh()->name)->toBe('New Name');
});

it('activates and deactivates category via dedicated actions without modifying child rows', function (): void {
    $admin = UserFactory::new()->platformAdmin()->create();
    actingAs($admin);

    $root = CategoryFactory::new()->root()->create(['name' => 'Root Cat', 'is_active' => true]);
    $child = CategoryFactory::new()->child($root)->create(['name' => 'Child Cat', 'is_active' => true]);

    // Deactivate Root
    Livewire::test(ListCategories::class)
        ->callTableAction('deactivate', record: $root)
        ->assertNotified('Category Deactivated');

    expect($root->fresh()->is_active)->toBeFalse()
        ->and($child->fresh()->is_active)->toBeTrue();

    // Reactivate Root
    Livewire::test(ListCategories::class)
        ->callTableAction('activate', record: $root)
        ->assertNotified('Category Activated');

    expect($root->fresh()->is_active)->toBeTrue()
        ->and($child->fresh()->is_active)->toBeTrue();
});

it('moves category under a new parent or to root and surfaces cycle and depth violations cleanly', function (): void {
    $admin = UserFactory::new()->platformAdmin()->create();
    actingAs($admin);

    $rootA = CategoryFactory::new()->root()->create(['name' => 'Root A']);
    $childA1 = CategoryFactory::new()->child($rootA)->create(['name' => 'Child A1']);
    $childA2 = CategoryFactory::new()->child($rootA)->create(['name' => 'matching name']);
    $rootB = CategoryFactory::new()->root()->create(['name' => 'Root B']);
    $childB1 = CategoryFactory::new()->child($rootB)->create(['name' => 'Matching Name']);

    // 1. Cycle test: move Root A under Child A1
    Livewire::test(ListCategories::class)
        ->callTableAction('move', record: $rootA, data: ['parent_public_id' => $childA1->public_id])
        ->assertNotified('Move Failed');
    expect($rootA->fresh()->parent_id)->toBeNull();

    // 2. Max depth test: move Root A under Root B (Root A has child Child A1, combined depth 1 + 2 = 3 > 2)
    Livewire::test(ListCategories::class)
        ->callTableAction('move', record: $rootA, data: ['parent_public_id' => $rootB->public_id])
        ->assertNotified('Move Failed');
    expect($rootA->fresh()->parent_id)->toBeNull();

    // 3. Sibling name clash test: move Child A2 under Root B (clashes with Child B1)
    Livewire::test(ListCategories::class)
        ->callTableAction('move', record: $childA2, data: ['parent_public_id' => $rootB->public_id])
        ->assertNotified('Move Failed');
    expect($childA2->fresh()->parent_id)->toBe($rootA->id);

    // 4. Happy path reparent: move Child A1 under Root B
    Livewire::test(ListCategories::class)
        ->callTableAction('move', record: $childA1, data: ['parent_public_id' => $rootB->public_id])
        ->assertNotified('Category Moved');
    expect($childA1->fresh()->parent_id)->toBe($rootB->id);

    // 5. Happy path root promotion: move Child A1 to root
    Livewire::test(ListCategories::class)
        ->callTableAction('move', record: $childA1, data: ['parent_public_id' => null])
        ->assertNotified('Category Moved');
    expect($childA1->fresh()->parent_id)->toBeNull();
});

it('reorders sibling categories within parent group via ReorderCategories action', function (): void {
    $admin = UserFactory::new()->platformAdmin()->create();
    actingAs($admin);

    $root = CategoryFactory::new()->root()->create(['name' => 'Parent Root']);
    $child1 = CategoryFactory::new()->child($root)->create(['name' => 'Child 1', 'sort_order' => 0]);
    $child2 = CategoryFactory::new()->child($root)->create(['name' => 'Child 2', 'sort_order' => 1]);
    $child3 = CategoryFactory::new()->child($root)->create(['name' => 'Child 3', 'sort_order' => 2]);

    Livewire::test(ListCategories::class)
        ->callTableAction('reorder_children', record: $root, data: [
            'siblings' => [
                ['public_id' => $child3->public_id, 'name' => $child3->name],
                ['public_id' => $child2->public_id, 'name' => $child2->name],
                ['public_id' => $child1->public_id, 'name' => $child1->name],
            ],
        ])
        ->assertNotified('Children Reordered');

    expect($child3->fresh()->sort_order)->toBe(0)
        ->and($child2->fresh()->sort_order)->toBe(1)
        ->and($child1->fresh()->sort_order)->toBe(2);
});

it('deletes childless leaf category via DeleteCategory and rejects deleting category with children', function (): void {
    $admin = UserFactory::new()->platformAdmin()->create();
    actingAs($admin);

    $rootWithChild = CategoryFactory::new()->root()->create(['name' => 'Parent With Child']);
    $childOfRoot = CategoryFactory::new()->child($rootWithChild)->create(['name' => 'Only Child']);
    $leafRoot = CategoryFactory::new()->root()->create(['name' => 'Childless Root']);

    // 1. Delete childless leaf category
    Livewire::test(ListCategories::class)
        ->callTableAction('delete', record: $leafRoot)
        ->assertNotified('Category Deleted');

    expect(Category::where('id', $leafRoot->id)->exists())->toBeFalse();

    // 2. Reject deleting category with children
    Livewire::test(ListCategories::class)
        ->callTableAction('delete', record: $rootWithChild)
        ->assertNotified('Delete Failed');

    expect(Category::where('id', $rootWithChild->id)->exists())->toBeTrue();
});

it('surfaces inuse violation as danger notification in Filament CategoryResource', function (): void {
    $admin = UserFactory::new()->platformAdmin()->create();
    actingAs($admin);

    $category = CategoryFactory::new()->root()->create(['name' => 'Referenced Category']);
    $menu = Menu::factory()->create();
    MenuItem::factory()->forMenu($menu)->category($category->public_id)->create();

    Livewire::test(ListCategories::class)
        ->callTableAction('delete', record: $category)
        ->assertNotified(
            Notification::make()
                ->danger()
                ->title('Delete Failed')
                ->body("Cannot delete category [{$category->public_id}] because it is in use. Deactivate it instead.")
        );

    expect(Category::whereKey($category->id)->exists())->toBeTrue();
});

it('ensures zero direct model writes exist in app/Filament', function (): void {
    $path = app_path('Filament');
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path));
    $files = [];
    foreach ($iterator as $file) {
        if ($file->isFile() && $file->getExtension() === 'php') {
            $files[] = $file;
        }
    }

    $offendingFiles = [];
    $patterns = ['::create(', '->update(', '->delete(', '->save(', '->forceDelete('];

    foreach ($files as $file) {
        $content = file_get_contents($file->getPathname());
        foreach ($patterns as $pattern) {
            if (str_contains($content, $pattern)) {
                $offendingFiles[] = $file->getPathname();
                break;
            }
        }
    }

    expect($offendingFiles)->toBeEmpty();
});
