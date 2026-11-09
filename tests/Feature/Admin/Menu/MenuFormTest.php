<?php

declare(strict_types=1);

use App\Domain\Catalog\Actions\Category\DeactivateCategory;
use App\Domain\Catalog\Models\Category;
use App\Domain\Content\Actions\GetLayoutContent;
use App\Domain\Content\Actions\UpdateMenu;
use App\Domain\Content\Data\UpdateMenuData;
use App\Domain\Content\Data\UpdateMenuItemInputData;
use App\Domain\Content\Enums\MenuItemKind;
use App\Domain\Content\Enums\MenuKey;
use App\Domain\Content\Exceptions\InvalidMenuTreeException;
use App\Domain\Content\Models\Menu;
use App\Domain\Content\Models\MenuItem;
use App\Filament\Admin\Resources\MenuResource\Pages\EditMenu;
use App\Filament\Admin\Resources\MenuResource\Schemas\MenuForm;
use App\Models\User;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

it('hydrates form from GetMenuTree rather than Eloquent relationships', function () {
    $admin = User::factory()->platformAdmin()->create();
    actingAs($admin);

    $menu = Menu::firstOrFail();
    $parent = MenuItem::factory()->forMenu($menu)->create([
        'label' => 'Root Menu Item',
        'url' => '/root-item',
        'sort_order' => 0,
        'parent_id' => null,
    ]);
    MenuItem::factory()->childOf($parent)->create([
        'label' => 'Nested Child Item',
        'url' => '/nested-child',
        'sort_order' => 0,
    ]);

    Livewire::test(EditMenu::class, ['record' => $menu->public_id])
        ->assertFormSet([
            'title' => $menu->title,
        ]);
});

it('forbids third-level nesting in form schema', function () {
    $admin = User::factory()->platformAdmin()->create();
    actingAs($admin);

    $menu = Menu::firstOrFail();
    $component = Livewire::test(EditMenu::class, ['record' => $menu->public_id]);
    /** @var EditMenu $instance */
    $instance = $component->instance();
    $schema = Schema::make($instance);
    MenuForm::form($schema);
    $components = $schema->getComponents();

    $section2 = collect($components)->first(fn ($c) => $c instanceof Section && $c->getHeading() === 'Top-Level Menu Items (Level 1)');
    expect($section2)->not->toBeNull();
    /** @var Section $section2 */
    $itemsRepeater = collect($section2->getChildComponents())->first(fn ($c) => $c instanceof Repeater && $c->getName() === 'items');
    expect($itemsRepeater)->not->toBeNull();
    /** @var Section $subSection */
    $subSection = collect($itemsRepeater->getChildComponents())->first(fn ($c) => $c instanceof Section && $c->getHeading() === 'Dropdown Sub-Items (Level 2)');
    expect($subSection)->not->toBeNull();
    /** @var Repeater $childRepeater */
    $childRepeater = collect($subSection->getChildComponents())->first(fn ($c) => $c instanceof Repeater && $c->getName() === 'children');
    expect($childRepeater)->not->toBeNull();

    // Verify children repeater has NO nested repeater
    $nestedRepeaters = collect($childRepeater->getChildComponents())->filter(fn ($c) => $c instanceof Repeater);
    expect($nestedRepeaters)->toBeEmpty();
});

it('saves form data through UpdateMenu and re-hydrates state without duplicate creation on second save', function () {
    $admin = User::factory()->platformAdmin()->create();
    actingAs($admin);

    $menu = Menu::firstOrFail();

    $component = Livewire::test(EditMenu::class, ['record' => $menu->public_id])
        ->fillForm([
            'title' => 'Updated Navigation',
            'items' => [
                [
                    'public_id' => null,
                    'label' => 'Main Category',
                    'url' => '/category',
                    'opens_in_new_tab' => false,
                    'is_enabled' => true,
                    'children' => [
                        [
                            'public_id' => null,
                            'label' => 'Sub Category',
                            'url' => '/category/sub',
                            'opens_in_new_tab' => false,
                            'is_enabled' => true,
                        ],
                    ],
                ],
            ],
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    // Verify 2 items in database and record their attributes
    expect($menu->items()->count())->toBe(2);
    $initialParent = $menu->items()->whereNull('parent_id')->firstOrFail();
    $initialChild = $menu->items()->whereNotNull('parent_id')->firstOrFail();

    expect($initialParent->kind)->toBe(MenuItemKind::LINK)
        ->and($initialParent->label)->toBe('Main Category')
        ->and($initialParent->url)->toBe('/category')
        ->and($initialParent->icon)->toBeNull()
        ->and($initialParent->sort_order)->toBe(0)
        ->and($initialChild->kind)->toBe(MenuItemKind::LINK)
        ->and($initialChild->label)->toBe('Sub Category')
        ->and($initialChild->url)->toBe('/category/sub')
        ->and($initialChild->icon)->toBeNull()
        ->and($initialChild->sort_order)->toBe(0);

    // Immediately call save again without modifying inputs
    $component->call('save')
        ->assertHasNoFormErrors();

    // Assert total count remains exactly 2 and attributes are stable
    $freshMenu = $menu->fresh();
    $freshParent = $freshMenu->items()->whereNull('parent_id')->firstOrFail();
    $freshChild = $freshMenu->items()->whereNotNull('parent_id')->firstOrFail();

    expect($freshMenu->items()->count())->toBe(2)
        ->and($freshParent->public_id)->toBe($initialParent->public_id)
        ->and($freshChild->public_id)->toBe($initialChild->public_id)
        ->and($freshParent->kind)->toBe(MenuItemKind::LINK)
        ->and($freshParent->label)->toBe('Main Category')
        ->and($freshParent->url)->toBe('/category')
        ->and($freshParent->icon)->toBeNull()
        ->and($freshChild->kind)->toBe(MenuItemKind::LINK)
        ->and($freshChild->label)->toBe('Sub Category')
        ->and($freshChild->url)->toBe('/category/sub')
        ->and($freshChild->icon)->toBeNull();
});

it('displays field errors for invalid URL inputs', function () {
    $admin = User::factory()->platformAdmin()->create();
    actingAs($admin);

    $menu = Menu::firstOrFail();

    Livewire::test(EditMenu::class, ['record' => $menu->public_id])
        ->fillForm([
            'items' => [
                [
                    'public_id' => null,
                    'label' => 'Evil Link',
                    'url' => '//evil.com',
                ],
            ],
        ])
        ->call('save')
        ->assertHasErrors(['data.items.0.url']);

    expect($menu->items()->count())->toBe(0);
});

it('displays danger notification with fixed copy on domain rejection', function () {
    $admin = User::factory()->platformAdmin()->create();
    actingAs($admin);

    $menu = Menu::firstOrFail();

    // Bind throwing instance of UpdateMenu
    app()->bind(UpdateMenu::class, fn () => new class
    {
        public function __invoke(): never
        {
            throw InvalidMenuTreeException::depthExceeded();
        }
    });

    Livewire::test(EditMenu::class, ['record' => $menu->public_id])
        ->fillForm([
            'title' => 'Test',
        ])
        ->call('save')
        ->assertNotified('Update Failed');
});

it('allows saving root link, category, and category_tree items on main_nav and preserves kind on second save', function () {
    $admin = User::factory()->platformAdmin()->create();
    actingAs($admin);

    $category = Category::factory()->root()->create();
    $menu = Menu::where('key', MenuKey::MAIN_NAV->value)->firstOrFail();
    $menu->items()->whereNotNull('parent_id')->delete();
    $menu->items()->delete();

    $component = Livewire::test(EditMenu::class, ['record' => $menu->public_id])
        ->fillForm([
            'title' => 'Main Navigation',
            'items' => [
                [
                    'public_id' => null,
                    'kind' => MenuItemKind::LINK->value,
                    'label' => 'Shop Link',
                    'url' => '/shop',
                    'opens_in_new_tab' => false,
                    'is_enabled' => true,
                    'children' => [],
                ],
                [
                    'public_id' => null,
                    'kind' => MenuItemKind::CATEGORY->value,
                    'category_public_id' => $category->public_id,
                    'label' => 'Shop Category',
                    'url' => null,
                    'opens_in_new_tab' => false,
                    'is_enabled' => true,
                    'children' => [],
                ],
                [
                    'public_id' => null,
                    'kind' => MenuItemKind::CATEGORY_TREE->value,
                    'category_public_id' => null,
                    'label' => null,
                    'url' => null,
                    'opens_in_new_tab' => false,
                    'is_enabled' => true,
                    'children' => [],
                ],
            ],
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($menu->items()->count())->toBe(3);
    $items = $menu->items()->orderBy('sort_order')->get();
    expect($items[0]->kind)->toBe(MenuItemKind::LINK)
        ->and($items[1]->kind)->toBe(MenuItemKind::CATEGORY)
        ->and($items[1]->category_public_id)->toBe($category->public_id)
        ->and($items[2]->kind)->toBe(MenuItemKind::CATEGORY_TREE)
        ->and($items[2]->category_public_id)->toBeNull();

    $publicIds = $items->pluck('public_id')->all();

    $component->call('save')->assertHasNoFormErrors();

    $secondSaveItems = $menu->fresh()->items()->orderBy('sort_order')->get();
    expect($secondSaveItems->pluck('public_id')->all())->toBe($publicIds)
        ->and($secondSaveItems[0]->kind)->toBe(MenuItemKind::LINK)
        ->and($secondSaveItems[1]->kind)->toBe(MenuItemKind::CATEGORY)
        ->and($secondSaveItems[2]->kind)->toBe(MenuItemKind::CATEGORY_TREE);
});

it('saves category on footer_column_1 preserving kind and category_public_id', function () {
    $admin = User::factory()->platformAdmin()->create();
    actingAs($admin);

    $category = Category::factory()->root()->create();
    $footerMenu = Menu::where('key', MenuKey::FOOTER_COLUMN_1->value)->firstOrFail();

    $component = Livewire::test(EditMenu::class, ['record' => $footerMenu->public_id]);
    /** @var EditMenu $instance */
    $instance = $component->instance();
    $schema = Schema::make($instance);
    MenuForm::form($schema);
    $components = $schema->getComponents();

    /** @var Section $section2 */
    $section2 = collect($components)->first(fn ($c) => $c instanceof Section && $c->getHeading() === 'Top-Level Menu Items (Level 1)');
    /** @var Repeater $itemsRepeater */
    $itemsRepeater = collect($section2->getChildComponents())->first(fn ($c) => $c instanceof Repeater && $c->getName() === 'items');
    /** @var Grid $itemGrid */
    $itemGrid = collect($itemsRepeater->getChildComponents())->first(fn ($c) => $c instanceof Grid);
    /** @var Select $rootKindSelect */
    $rootKindSelect = collect($itemGrid->getChildComponents())->first(fn ($c) => $c instanceof Select && $c->getName() === 'kind');

    $options = $rootKindSelect->getOptions();
    expect($options)->toHaveKeys(['link', 'category'])
        ->and($options)->not->toHaveKey('category_tree');

    $component->fillForm([
        'items' => [
            [
                'public_id' => null,
                'kind' => MenuItemKind::CATEGORY->value,
                'category_public_id' => $category->public_id,
                'label' => 'Foot Category',
                'url' => null,
                'opens_in_new_tab' => false,
                'is_enabled' => true,
            ],
        ],
    ])
        ->call('save')
        ->assertHasNoFormErrors();

    $freshItem = $footerMenu->items()->firstOrFail();
    expect($freshItem->kind)->toBe(MenuItemKind::CATEGORY)
        ->and($freshItem->category_public_id)->toBe($category->public_id);
});

it('excludes category_tree from child kind select options on main_nav', function () {
    $admin = User::factory()->platformAdmin()->create();
    actingAs($admin);

    $menu = Menu::where('key', MenuKey::MAIN_NAV->value)->firstOrFail();
    $component = Livewire::test(EditMenu::class, ['record' => $menu->public_id]);
    /** @var EditMenu $instance */
    $instance = $component->instance();
    $schema = Schema::make($instance);
    MenuForm::form($schema);
    $components = $schema->getComponents();

    /** @var Section $section2 */
    $section2 = collect($components)->first(fn ($c) => $c instanceof Section && $c->getHeading() === 'Top-Level Menu Items (Level 1)');
    /** @var Repeater $itemsRepeater */
    $itemsRepeater = collect($section2->getChildComponents())->first(fn ($c) => $c instanceof Repeater && $c->getName() === 'items');
    /** @var Section $subSection */
    $subSection = collect($itemsRepeater->getChildComponents())->first(fn ($c) => $c instanceof Section && $c->getHeading() === 'Dropdown Sub-Items (Level 2)');
    /** @var Repeater $childRepeater */
    $childRepeater = collect($subSection->getChildComponents())->first(fn ($c) => $c instanceof Repeater && $c->getName() === 'children');
    /** @var Grid $childGrid */
    $childGrid = collect($childRepeater->getChildComponents())->first(fn ($c) => $c instanceof Grid);
    /** @var Select $childKindSelect */
    $childKindSelect = collect($childGrid->getChildComponents())->first(fn ($c) => $c instanceof Select && $c->getName() === 'kind');

    $options = $childKindSelect->getOptions();
    expect($options)->toHaveKeys(['link', 'category'])
        ->and($options)->not->toHaveKey('category_tree');
});

it('saves category item storing category_public_id, null url, and null label when blank, projecting to storefront layout', function () {
    $admin = User::factory()->platformAdmin()->create();
    actingAs($admin);

    $category = Category::factory()->root()->create([
        'name' => 'Smartphones',
        'slug' => 'smartphones',
    ]);
    $menu = Menu::where('key', MenuKey::MAIN_NAV->value)->firstOrFail();
    $menu->items()->whereNotNull('parent_id')->delete();
    $menu->items()->delete();

    Livewire::test(EditMenu::class, ['record' => $menu->public_id])
        ->fillForm([
            'items' => [
                [
                    'public_id' => null,
                    'kind' => MenuItemKind::CATEGORY->value,
                    'category_public_id' => $category->public_id,
                    'label' => '   ',
                    'url' => null,
                    'opens_in_new_tab' => false,
                    'is_enabled' => true,
                    'children' => [],
                ],
            ],
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $storedItem = $menu->items()->firstOrFail();
    expect($storedItem->url)->toBeNull()
        ->and($storedItem->label)->toBeNull()
        ->and($storedItem->category_public_id)->toBe($category->public_id)
        ->and($storedItem->kind)->toBe(MenuItemKind::CATEGORY);

    $layout = app(GetLayoutContent::class)();
    $navData = $layout->data->menus['main_nav'] ?? null;
    expect($navData)->not->toBeNull();
    $firstItem = $navData->items[0] ?? null;
    expect($firstItem)->not->toBeNull()
        ->and($firstItem->label)->toBe('Smartphones')
        ->and($firstItem->url)->toBe('/shop?category=smartphones')
        ->and($firstItem->id)->toBe($storedItem->public_id);
});

it('saves category item storing custom label override verbatim', function () {
    $admin = User::factory()->platformAdmin()->create();
    actingAs($admin);

    $category = Category::factory()->root()->create([
        'name' => 'Smartphones',
        'slug' => 'smartphones',
    ]);
    $menu = Menu::where('key', MenuKey::MAIN_NAV->value)->firstOrFail();
    $menu->items()->whereNotNull('parent_id')->delete();
    $menu->items()->delete();

    Livewire::test(EditMenu::class, ['record' => $menu->public_id])
        ->fillForm([
            'items' => [
                [
                    'public_id' => null,
                    'kind' => MenuItemKind::CATEGORY->value,
                    'category_public_id' => $category->public_id,
                    'label' => 'Featured Phones',
                    'url' => null,
                    'opens_in_new_tab' => false,
                    'is_enabled' => true,
                    'children' => [],
                ],
            ],
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $storedItem = $menu->items()->firstOrFail();
    expect($storedItem->label)->toBe('Featured Phones');

    $layout = app(GetLayoutContent::class)();
    $navData = $layout->data->menus['main_nav'] ?? null;
    expect($navData)->not->toBeNull();
    $firstItem = $navData->items[0] ?? null;
    expect($firstItem)->not->toBeNull()
        ->and($firstItem->label)->toBe('Featured Phones');
});

it('saves root category_tree with empty picker storing null category_public_id and expanding storefront roots in layout', function () {
    $admin = User::factory()->platformAdmin()->create();
    actingAs($admin);

    Category::query()->delete();
    $phones = Category::factory()->root()->create(['name' => 'Phones', 'sort_order' => 0]);
    $laptops = Category::factory()->root()->create(['name' => 'Laptops', 'sort_order' => 1]);

    $menu = Menu::where('key', MenuKey::MAIN_NAV->value)->firstOrFail();
    $menu->items()->whereNotNull('parent_id')->delete();
    $menu->items()->delete();

    Livewire::test(EditMenu::class, ['record' => $menu->public_id])
        ->fillForm([
            'items' => [
                [
                    'public_id' => null,
                    'kind' => MenuItemKind::CATEGORY_TREE->value,
                    'category_public_id' => null,
                    'label' => null,
                    'url' => null,
                    'opens_in_new_tab' => false,
                    'is_enabled' => true,
                    'children' => [],
                ],
            ],
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $storedItem = $menu->items()->firstOrFail();
    expect($storedItem->kind)->toBe(MenuItemKind::CATEGORY_TREE)
        ->and($storedItem->category_public_id)->toBeNull();

    $layout = app(GetLayoutContent::class)();
    $navData = $layout->data->menus['main_nav'] ?? null;
    expect($navData)->not->toBeNull();
    $expandedIds = array_map(fn ($item) => $item->id, $navData->items);
    expect($expandedIds)->toBe([$phones->public_id, $laptops->public_id]);
});

it('saves root category_tree referencing category storing category_public_id and expanding category children in layout', function () {
    $admin = User::factory()->platformAdmin()->create();
    actingAs($admin);

    Category::query()->delete();
    $parent = Category::factory()->root()->create(['name' => 'Electronics', 'sort_order' => 0]);
    $phones = Category::factory()->child($parent)->create(['name' => 'Phones', 'sort_order' => 0]);
    $audio = Category::factory()->child($parent)->create(['name' => 'Audio', 'sort_order' => 1]);

    $menu = Menu::where('key', MenuKey::MAIN_NAV->value)->firstOrFail();
    $menu->items()->whereNotNull('parent_id')->delete();
    $menu->items()->delete();

    Livewire::test(EditMenu::class, ['record' => $menu->public_id])
        ->fillForm([
            'items' => [
                [
                    'public_id' => null,
                    'kind' => MenuItemKind::CATEGORY_TREE->value,
                    'category_public_id' => $parent->public_id,
                    'label' => null,
                    'url' => null,
                    'opens_in_new_tab' => false,
                    'is_enabled' => true,
                    'children' => [],
                ],
            ],
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $storedItem = $menu->items()->firstOrFail();
    expect($storedItem->kind)->toBe(MenuItemKind::CATEGORY_TREE)
        ->and($storedItem->category_public_id)->toBe($parent->public_id);

    $layout = app(GetLayoutContent::class)();
    $navData = $layout->data->menus['main_nav'] ?? null;
    expect($navData)->not->toBeNull();
    $expandedIds = array_map(fn ($item) => $item->id, $navData->items);
    expect($expandedIds)->toBe([$phones->public_id, $audio->public_id])
        ->and($expandedIds)->not->toContain($parent->public_id);
});

it('rejects child category_tree when posted directly', function () {
    $admin = User::factory()->platformAdmin()->create();
    $menu = Menu::where('key', MenuKey::MAIN_NAV->value)->firstOrFail();
    $category = Category::factory()->root()->create();

    $data = new UpdateMenuData(
        title: 'Main Nav',
        items: [
            new UpdateMenuItemInputData(
                public_id: null,
                label: 'Root Link',
                url: '/root',
                children: [
                    new UpdateMenuItemInputData(
                        public_id: null,
                        label: 'Child Tree',
                        url: null,
                        children: [],
                        kind: MenuItemKind::CATEGORY_TREE,
                        category_public_id: $category->public_id,
                    ),
                ],
            ),
        ],
    );

    expect(fn () => app(UpdateMenu::class)($menu, $data, $admin))
        ->toThrow(InvalidMenuTreeException::class, 'Category tree items are allowed at level 1 only.');
});

it('displays inline warning and allows save for active category with show_in_nav false', function () {
    $admin = User::factory()->platformAdmin()->create();
    actingAs($admin);

    $category = Category::factory()->root()->hiddenFromNav()->create();
    $menu = Menu::where('key', MenuKey::MAIN_NAV->value)->firstOrFail();
    $menu->items()->whereNotNull('parent_id')->delete();
    $menu->items()->delete();

    $warning = 'Warning: This category is inactive or hidden from navigation and will be omitted from the storefront layout.';

    $component = Livewire::test(EditMenu::class, ['record' => $menu->public_id])
        ->fillForm([
            'items' => [
                [
                    'public_id' => null,
                    'kind' => MenuItemKind::CATEGORY->value,
                    'category_public_id' => $category->public_id,
                    'label' => 'Hidden Nav Category',
                    'url' => null,
                    'opens_in_new_tab' => false,
                    'is_enabled' => true,
                    'children' => [],
                ],
            ],
        ]);

    $component->assertSee($warning)
        ->call('save')
        ->assertHasNoFormErrors();

    expect($menu->items()->where('category_public_id', $category->public_id)->exists())->toBeTrue();

    $layout = app(GetLayoutContent::class)();
    $navData = $layout->data->menus['main_nav'] ?? null;
    expect($navData)->not->toBeNull();
    expect($navData->items)->toBeEmpty();
});

it('displays inline warning and allows save for active child of deactivated parent', function () {
    $admin = User::factory()->platformAdmin()->create();
    actingAs($admin);

    $parent = Category::factory()->root()->create();
    $child = Category::factory()->child($parent)->create();
    app(DeactivateCategory::class)($parent->public_id, $admin);

    $menu = Menu::where('key', MenuKey::MAIN_NAV->value)->firstOrFail();
    $menu->items()->whereNotNull('parent_id')->delete();
    $menu->items()->delete();

    $warning = 'Warning: This category is inactive or hidden from navigation and will be omitted from the storefront layout.';

    $component = Livewire::test(EditMenu::class, ['record' => $menu->public_id])
        ->fillForm([
            'items' => [
                [
                    'public_id' => null,
                    'kind' => MenuItemKind::CATEGORY->value,
                    'category_public_id' => $child->public_id,
                    'label' => 'Child Category',
                    'url' => null,
                    'opens_in_new_tab' => false,
                    'is_enabled' => true,
                    'children' => [],
                ],
            ],
        ]);

    $component->assertSee($warning)
        ->call('save')
        ->assertHasNoFormErrors();

    expect($menu->items()->where('category_public_id', $child->public_id)->exists())->toBeTrue();

    $layout = app(GetLayoutContent::class)();
    $navData = $layout->data->menus['main_nav'] ?? null;
    expect($navData)->not->toBeNull();
    expect($navData->items)->toBeEmpty();
});

it('displays no warning for active in-nav category target', function () {
    $admin = User::factory()->platformAdmin()->create();
    actingAs($admin);

    $category = Category::factory()->root()->create([
        'name' => 'Tablets',
        'show_in_nav' => true,
        'is_active' => true,
    ]);
    $menu = Menu::where('key', MenuKey::MAIN_NAV->value)->firstOrFail();
    $menu->items()->whereNotNull('parent_id')->delete();
    $menu->items()->delete();

    $warning = 'Warning: This category is inactive or hidden from navigation and will be omitted from the storefront layout.';
    Livewire::test(EditMenu::class, ['record' => $menu->public_id])
        ->fillForm([
            'items' => [
                [
                    'public_id' => null,
                    'kind' => MenuItemKind::CATEGORY->value,
                    'category_public_id' => $category->public_id,
                    'label' => 'Tablets',
                    'url' => null,
                    'opens_in_new_tab' => false,
                    'is_enabled' => true,
                    'children' => [],
                ],
            ],
        ])
        ->assertDontSee($warning)
        ->call('save')
        ->assertHasNoFormErrors();
});

it('preserves stored icon and opens_in_new_tab on category_tree item across saves', function () {
    $admin = User::factory()->platformAdmin()->create();
    actingAs($admin);

    $menu = Menu::where('key', MenuKey::MAIN_NAV->value)->firstOrFail();
    $menu->items()->whereNotNull('parent_id')->delete();
    $menu->items()->delete();

    $item = MenuItem::factory()
        ->forMenu($menu)
        ->categoryTree()
        ->create([
            'opens_in_new_tab' => true,
            'icon' => 'heroicon-o-folder',
            'sort_order' => 0,
        ]);

    $initialPublicId = $item->public_id;

    Livewire::test(EditMenu::class, ['record' => $menu->public_id])
        ->call('save')
        ->assertHasNoFormErrors();

    $freshItem = $menu->items()->where('public_id', $initialPublicId)->firstOrFail();
    expect($freshItem->opens_in_new_tab)->toBeTrue()
        ->and($freshItem->icon)->toBe('heroicon-o-folder')
        ->and($freshItem->kind)->toBe(MenuItemKind::CATEGORY_TREE)
        ->and($freshItem->public_id)->toBe($initialPublicId);
});

it('calls GetCategoryTree Admin audience once per form mount rather than per item', function () {
    $admin = User::factory()->platformAdmin()->create();
    actingAs($admin);

    $cat = Category::factory()->root()->create();

    $menu = Menu::where('key', MenuKey::MAIN_NAV->value)->firstOrFail();
    $menu->items()->whereNotNull('parent_id')->delete();
    $menu->items()->delete();

    for ($i = 0; $i < 5; $i++) {
        MenuItem::factory()->forMenu($menu)->category($cat->public_id)->create([
            'sort_order' => $i,
        ]);
    }

    DB::enableQueryLog();

    Livewire::test(EditMenu::class, ['record' => $menu->public_id]);

    $categoryQueries = collect(DB::getQueryLog())
        ->filter(fn (array $query) => str_contains((string) $query['query'], 'categories'));

    DB::disableQueryLog();

    expect($categoryQueries)->toHaveCount(1);
});
