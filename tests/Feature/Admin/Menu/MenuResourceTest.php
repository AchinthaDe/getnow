<?php

declare(strict_types=1);

use App\Domain\Content\Enums\MenuKey;
use App\Domain\Content\Models\Menu;
use App\Domain\Content\Models\MenuItem;
use App\Filament\Admin\Resources\MenuResource;
use App\Filament\Admin\Resources\MenuResource\Pages\EditMenu;
use App\Filament\Admin\Resources\MenuResource\Pages\ListMenus;
use App\Filament\Admin\Resources\MenuResource\Schemas\MenuForm;
use App\Models\User;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

it('registers only index and edit routes on MenuResource', function () {
    $pages = MenuResource::getPages();

    expect(array_keys($pages))->toEqualCanonicalizing(['index', 'edit']);
});

it('returns 404 when navigating to edit route via raw integer id', function () {
    $admin = User::factory()->platformAdmin()->create();
    actingAs($admin);

    $menu = Menu::firstOrFail();

    get("/admin/menus/{$menu->id}/edit")->assertNotFound();
});

it('configures table pagination on ListMenus with history false', function () {
    $admin = User::factory()->platformAdmin()->create();
    actingAs($admin);

    $component = Livewire::test(ListMenus::class);
    /** @var ListMenus $instance */
    $instance = $component->instance();
    $queryString = $instance->queryStringHandlesPagination();

    expect($queryString)->not->toBeEmpty();

    foreach ($queryString as $config) {
        expect($config)->toBeArray()
            ->and($config)->toHaveKey('history')
            ->and($config['history'])->toBeFalse();
    }
});

it('renders list and edit pages for platform admin via smoke test', function () {
    $admin = User::factory()->platformAdmin()->create();
    actingAs($admin);

    $menu = Menu::firstOrFail();

    get(ListMenus::getUrl())->assertSuccessful();
    Livewire::test(ListMenus::class)->assertSuccessful();

    get(MenuResource::getUrl('edit', ['record' => $menu]))->assertSuccessful();
    Livewire::test(EditMenu::class, ['record' => $menu->public_id])->assertSuccessful();
});

it('returns 403 for non-admin on list and edit routes', function () {
    $user = User::factory()->create(['is_platform_admin' => false]);
    actingAs($user);

    $menu = Menu::firstOrFail();

    get('/admin/menus')->assertForbidden();
    get("/admin/menus/{$menu->public_id}/edit")->assertForbidden();
});

it('configures MenuForm with separate Menu Details and Top-Level Menu Items sections', function () {
    $admin = User::factory()->platformAdmin()->create();
    actingAs($admin);

    $menu = Menu::firstOrFail();
    $component = Livewire::test(EditMenu::class, ['record' => $menu->public_id]);
    /** @var EditMenu $instance */
    $instance = $component->instance();
    $schema = Schema::make($instance);
    MenuForm::form($schema);

    $components = $schema->getComponents();
    expect($components)->toHaveCount(2)
        ->and($components[0])->toBeInstanceOf(Section::class)
        ->and($components[1])->toBeInstanceOf(Section::class);

    /** @var Section $section1 */
    $section1 = $components[0];
    /** @var Section $section2 */
    $section2 = $components[1];

    expect($section1->getHeading())->toBe('Menu Details')
        ->and($section2->getHeading())->toBe('Top-Level Menu Items (Level 1)');

    $sec1Children = $section1->getChildComponents();
    expect($sec1Children)->toHaveCount(1)
        ->and($sec1Children[0])->toBeInstanceOf(TextInput::class)
        ->and($sec1Children[0]->getName())->toBe('title');

    $sec2Children = $section2->getChildComponents();
    expect($sec2Children)->toHaveCount(1)
        ->and($sec2Children[0])->toBeInstanceOf(Repeater::class)
        ->and($sec2Children[0]->getName())->toBe('items');
});

it('configures top-level items and nested children repeaters as collapsible and collapsed by default with collapse and expand actions', function () {
    $admin = User::factory()->platformAdmin()->create();
    actingAs($admin);

    $menu = Menu::firstOrFail();
    $component = Livewire::test(EditMenu::class, ['record' => $menu->public_id]);
    /** @var EditMenu $instance */
    $instance = $component->instance();
    $schema = Schema::make($instance);
    MenuForm::form($schema);

    $components = $schema->getComponents();
    /** @var Section $section2 */
    $section2 = $components[1];
    /** @var Repeater $itemsRepeater */
    $itemsRepeater = $section2->getChildComponents()[0];

    expect($itemsRepeater->isCollapsible())->toBeTrue()
        ->and($itemsRepeater->isCollapsed())->toBeTrue()
        ->and($itemsRepeater->getCollapseAllAction()->getLabel())->toBe('Collapse All')
        ->and($itemsRepeater->getExpandAllAction()->getLabel())->toBe('Expand All');

    $subSection = collect($itemsRepeater->getChildComponents())->first(fn ($c) => $c instanceof Section);
    expect($subSection)->not->toBeNull();
    /** @var Section $subSection */
    /** @var Repeater $childRepeater */
    $childRepeater = collect($subSection->getChildComponents())->first(fn ($c) => $c instanceof Repeater);
    expect($childRepeater)->not->toBeNull()
        ->and($childRepeater->isCollapsible())->toBeTrue()
        ->and($childRepeater->isCollapsed())->toBeTrue();
});

it('verifies Section and Grid layout components do not define statePath', function () {
    $admin = User::factory()->platformAdmin()->create();
    actingAs($admin);

    $menu = Menu::firstOrFail();
    $component = Livewire::test(EditMenu::class, ['record' => $menu->public_id]);
    /** @var EditMenu $instance */
    $instance = $component->instance();

    $schema = $instance->getSchema('form');
    expect($schema)->not->toBeNull();
    /** @var Schema $schema */
    $components = $schema->getComponents();
    expect($components)->toHaveCount(2);

    /** @var Section $section1 */
    $section1 = $components[0];
    /** @var Section $section2 */
    $section2 = $components[1];

    /** @var TextInput $titleInput */
    $titleInput = $section1->getChildComponents()[0];
    /** @var Repeater $itemsRepeater */
    $itemsRepeater = $section2->getChildComponents()[0];

    expect($titleInput->getStatePath())->toBe('data.title')
        ->and($itemsRepeater->getStatePath())->toBe('data.items');

    $itemComponents = $itemsRepeater->getChildComponents();
    $itemGrid = collect($itemComponents)->first(fn ($c) => $c instanceof Grid);
    $subSection = collect($itemComponents)->first(fn ($c) => $c instanceof Section);
    expect($itemGrid)->not->toBeNull()
        ->and($subSection)->not->toBeNull();
    /** @var Grid $itemGrid */
    /** @var Section $subSection */
    $childRepeater = collect($subSection->getChildComponents())->first(fn ($c) => $c instanceof Repeater);
    expect($childRepeater)->not->toBeNull();
    /** @var Repeater $childRepeater */
    $childGrid = collect($childRepeater->getChildComponents())->first(fn ($c) => $c instanceof Grid);
    expect($childGrid)->not->toBeNull();
    /** @var Grid $childGrid */
    expect($section1->hasStatePath())->toBeFalse()
        ->and($section2->hasStatePath())->toBeFalse()
        ->and($itemGrid->hasStatePath())->toBeFalse()
        ->and($subSection->hasStatePath())->toBeFalse()
        ->and($childGrid->hasStatePath())->toBeFalse()
        ->and($section1->getStatePath(isAbsolute: false))->toBeNull()
        ->and($section2->getStatePath(isAbsolute: false))->toBeNull()
        ->and($itemGrid->getStatePath(isAbsolute: false))->toBeNull()
        ->and($subSection->getStatePath(isAbsolute: false))->toBeNull()
        ->and($childGrid->getStatePath(isAbsolute: false))->toBeNull();
});

it('arranges top-level and level-2 item fields in 12-column grids preserving public_id hidden input', function () {
    $admin = User::factory()->platformAdmin()->create();
    actingAs($admin);

    $menu = Menu::firstOrFail();
    $component = Livewire::test(EditMenu::class, ['record' => $menu->public_id]);
    /** @var EditMenu $instance */
    $instance = $component->instance();
    $schema = Schema::make($instance);
    MenuForm::form($schema);

    $components = $schema->getComponents();
    /** @var Section $section2 */
    $section2 = $components[1];
    /** @var Repeater $itemsRepeater */
    $itemsRepeater = $section2->getChildComponents()[0];
    $itemComponents = $itemsRepeater->getChildComponents();

    $hidden1 = collect($itemComponents)->first(fn ($c) => $c instanceof Hidden && $c->getName() === 'public_id');
    expect($hidden1)->not->toBeNull();

    $grid1 = collect($itemComponents)->first(fn ($c) => $c instanceof Grid);
    expect($grid1)->not->toBeNull();
    /** @var Grid $grid1 */
    expect($grid1->getColumns())->toEqual(['default' => 12]);

    $grid1Components = $grid1->getChildComponents();
    /** @var TextInput $label1 */
    $label1 = collect($grid1Components)->first(fn ($c) => $c instanceof TextInput && $c->getName() === 'label');
    /** @var TextInput $url1 */
    $url1 = collect($grid1Components)->first(fn ($c) => $c instanceof TextInput && $c->getName() === 'url');
    /** @var Toggle $newTab1 */
    $newTab1 = collect($grid1Components)->first(fn ($c) => $c instanceof Toggle && $c->getName() === 'opens_in_new_tab');
    /** @var Toggle $enabled1 */
    $enabled1 = collect($grid1Components)->first(fn ($c) => $c instanceof Toggle && $c->getName() === 'is_enabled');

    expect($label1->getColumnSpan(null))->toEqual(['default' => 12, 'md' => 4])
        ->and($label1->isLive())->toBeTrue()
        ->and($label1->isLiveOnBlur())->toBeTrue()
        ->and($url1->getColumnSpan(null))->toEqual(['default' => 12, 'md' => 4])
        ->and($url1->isLive())->toBeTrue()
        ->and($url1->isLiveOnBlur())->toBeTrue()
        ->and($newTab1->getColumnSpan(null))->toEqual(['default' => 6, 'md' => 2])
        ->and($newTab1->isInline())->toBeFalse()
        ->and($enabled1->getColumnSpan(null))->toEqual(['default' => 6, 'md' => 2])
        ->and($enabled1->isInline())->toBeFalse();

    /** @var Section $subSection */
    $subSection = collect($itemComponents)->first(fn ($c) => $c instanceof Section);
    /** @var Repeater $childRepeater */
    $childRepeater = collect($subSection->getChildComponents())->first(fn ($c) => $c instanceof Repeater);
    $childComponents = $childRepeater->getChildComponents();

    $hidden2 = collect($childComponents)->first(fn ($c) => $c instanceof Hidden && $c->getName() === 'public_id');
    expect($hidden2)->not->toBeNull();

    $grid2 = collect($childComponents)->first(fn ($c) => $c instanceof Grid);
    expect($grid2)->not->toBeNull();
    /** @var Grid $grid2 */
    expect($grid2->getColumns())->toEqual(['default' => 12]);

    $grid2Components = $grid2->getChildComponents();
    /** @var TextInput $label2 */
    $label2 = collect($grid2Components)->first(fn ($c) => $c instanceof TextInput && $c->getName() === 'label');
    /** @var TextInput $url2 */
    $url2 = collect($grid2Components)->first(fn ($c) => $c instanceof TextInput && $c->getName() === 'url');
    /** @var Toggle $newTab2 */
    $newTab2 = collect($grid2Components)->first(fn ($c) => $c instanceof Toggle && $c->getName() === 'opens_in_new_tab');
    /** @var Toggle $enabled2 */
    $enabled2 = collect($grid2Components)->first(fn ($c) => $c instanceof Toggle && $c->getName() === 'is_enabled');

    expect($label2->getColumnSpan(null))->toEqual(['default' => 12, 'md' => 4])
        ->and($label2->isLive())->toBeTrue()
        ->and($label2->isLiveOnBlur())->toBeTrue()
        ->and($url2->getColumnSpan(null))->toEqual(['default' => 12, 'md' => 4])
        ->and($url2->isLive())->toBeTrue()
        ->and($url2->isLiveOnBlur())->toBeTrue()
        ->and($newTab2->getColumnSpan(null))->toEqual(['default' => 6, 'md' => 2])
        ->and($newTab2->isInline())->toBeFalse()
        ->and($enabled2->getColumnSpan(null))->toEqual(['default' => 6, 'md' => 2])
        ->and($enabled2->isInline())->toBeFalse();
});

it('renders top-level and level-2 itemLabels, fallbacks, and sub-item counts in EditMenu Livewire view', function () {
    $admin = User::factory()->platformAdmin()->create();
    actingAs($admin);

    $menu = Menu::where('key', MenuKey::MAIN_NAV->value)->firstOrFail();
    $menu->items()->whereNotNull('parent_id')->delete();
    $menu->items()->delete();

    // Parent 1 with 6 children
    $parent1 = MenuItem::factory()->forMenu($menu)->create([
        'label' => 'All Categories',
        'url' => '/shop',
        'opens_in_new_tab' => false,
        'is_enabled' => true,
        'sort_order' => 0,
    ]);
    for ($i = 0; $i < 6; $i++) {
        MenuItem::factory()->childOf($parent1)->create([
            'label' => 'Category '.($i + 1),
            'url' => '/shop/cat-'.($i + 1),
            'sort_order' => $i,
        ]);
    }

    // Parent 2 with 1 child
    $parent2 = MenuItem::factory()->forMenu($menu)->create([
        'label' => 'Phones',
        'url' => '/shop/phones',
        'opens_in_new_tab' => true,
        'is_enabled' => true,
        'sort_order' => 1,
    ]);
    MenuItem::factory()->childOf($parent2)->create([
        'label' => 'Smartphones',
        'url' => '/shop/phones/smartphones',
        'opens_in_new_tab' => true,
        'sort_order' => 0,
    ]);

    // Parent 3 with 0 children, opens_in_new_tab true, is_enabled false (pins badge order [New Tab]  [Disabled])
    MenuItem::factory()->forMenu($menu)->create([
        'label' => 'Deals',
        'url' => '/deals',
        'opens_in_new_tab' => true,
        'is_enabled' => false,
        'sort_order' => 2,
    ]);

    $component = Livewire::test(EditMenu::class, ['record' => $menu->public_id]);
    $component->assertSee('All Categories  —  /shop  •  (6 sub-items)')
        ->assertSee('6 / 10 sub-items')
        ->assertSee('Phones  —  /shop/phones  •  (1 sub-item)  [New Tab]')
        ->assertSee('Smartphones  —  /shop/phones/smartphones  [New Tab]')
        ->assertSee('1 / 10 sub-items')
        ->assertSee('Deals  —  /deals  [New Tab]  [Disabled]')
        ->assertSee('0 / 10 sub-items');

    // Test fallbacks for blank and null labels
    $component->set('data.items', [
        [
            'public_id' => null,
            'label' => '',
            'url' => '',
            'opens_in_new_tab' => false,
            'is_enabled' => true,
            'children' => [
                [
                    'public_id' => null,
                    'label' => null,
                    'url' => '',
                    'opens_in_new_tab' => false,
                    'is_enabled' => true,
                ],
            ],
        ],
    ]);
    $component->assertSee('New menu item')
        ->assertSee('New sub-item');
});

it('persists menu structure updates through create, reorder, edit, and delete preserving item and child public_ids', function () {
    $admin = User::factory()->platformAdmin()->create();
    actingAs($admin);

    $menu = Menu::where('key', MenuKey::MAIN_NAV->value)->firstOrFail();
    $menu->items()->whereNotNull('parent_id')->delete();
    $menu->items()->delete();

    $parent1 = MenuItem::factory()->forMenu($menu)->create([
        'label' => 'Category 1',
        'url' => '/cat-1',
        'sort_order' => 0,
    ]);
    $child1 = MenuItem::factory()->childOf($parent1)->create([
        'label' => 'Sub 1',
        'url' => '/sub-1',
        'sort_order' => 0,
    ]);

    $parent2 = MenuItem::factory()->forMenu($menu)->create([
        'label' => 'Category 2',
        'url' => '/cat-2',
        'sort_order' => 1,
    ]);
    $child2 = MenuItem::factory()->childOf($parent2)->create([
        'label' => 'Sub 2',
        'url' => '/sub-2',
        'sort_order' => 0,
    ]);

    $p1Id = $parent1->public_id;
    $p2Id = $parent2->public_id;
    $c1Id = $child1->public_id;
    $c2Id = $child2->public_id;

    $component = Livewire::test(EditMenu::class, ['record' => $menu->public_id]);
    $items = $component->get('data.items');
    expect($items)->toBeArray();

    $keys = array_keys($items);
    $k1 = $keys[0];
    $k2 = $keys[1];

    $items[$k1]['label'] = 'Updated Categories';
    $items[$k1]['is_enabled'] = false;
    $items[$k1]['children'] = []; // Delete Child 1

    $items[$k2]['children'][] = [
        'public_id' => null,
        'label' => 'New Sub',
        'url' => '/new-sub',
        'opens_in_new_tab' => false,
        'is_enabled' => true,
    ];

    $newItems = [
        $k2 => $items[$k2],
        $k1 => $items[$k1],
        'new_root_uuid' => [
            'public_id' => null,
            'label' => 'Brand New Root',
            'url' => '/brand-new',
            'opens_in_new_tab' => false,
            'is_enabled' => true,
            'children' => [],
        ],
    ];

    $component->set('data.items', $newItems)
        ->call('save')
        ->assertHasNoFormErrors();

    $freshMenu = $menu->fresh();
    expect($freshMenu->items()->where('public_id', $p1Id)->exists())->toBeTrue()
        ->and($freshMenu->items()->where('public_id', $p2Id)->exists())->toBeTrue()
        ->and($freshMenu->items()->where('public_id', $c2Id)->exists())->toBeTrue()
        ->and($freshMenu->items()->where('public_id', $c1Id)->exists())->toBeFalse();

    $retainedP1 = $freshMenu->items()->where('public_id', $p1Id)->firstOrFail();
    $retainedP2 = $freshMenu->items()->where('public_id', $p2Id)->firstOrFail();
    $retainedC2 = $freshMenu->items()->where('public_id', $c2Id)->firstOrFail();

    expect($retainedP1->label)->toBe('Updated Categories')
        ->and($retainedP1->is_enabled)->toBeFalse()
        ->and($retainedP2->sort_order)->toBe(0)
        ->and($retainedP1->sort_order)->toBe(1);

    $newRoot = $freshMenu->items()->where('label', 'Brand New Root')->firstOrFail();
    expect($newRoot->sort_order)->toBe(2)
        ->and($newRoot->public_id)->not->toBeNull();

    $newChild = $freshMenu->items()->where('label', 'New Sub')->firstOrFail();
    expect($newChild->parent_id)->toBe($retainedP2->id)
        ->and($newChild->public_id)->not->toBeNull();
});

it('fails validation and halts update when title or item label contains HTML tags', function () {
    $admin = User::factory()->platformAdmin()->create();
    actingAs($admin);

    $menu = Menu::where('key', MenuKey::MAIN_NAV->value)->firstOrFail();
    $menu->items()->whereNotNull('parent_id')->delete();
    $menu->items()->delete();

    $parent = MenuItem::factory()->forMenu($menu)->create([
        'label' => 'Parent Menu Item',
        'url' => '/parent-item',
        'sort_order' => 0,
    ]);
    $child = MenuItem::factory()->childOf($parent)->create([
        'label' => 'Child Menu Item',
        'url' => '/child-item',
        'sort_order' => 0,
    ]);

    $beforeUpdatedAt = $menu->fresh()->updated_at;
    $beforeItems = $menu->items()->orderBy('id')->get()->toArray();

    $component = Livewire::test(EditMenu::class, ['record' => $menu->public_id]);
    $items = $component->get('data.items');
    expect($items)->toBeArray();
    $parentKey = array_key_first($items);
    expect($parentKey)->not->toBeNull();
    expect($items[$parentKey]['children'])->toBeArray();
    $childKey = array_key_first($items[$parentKey]['children']);
    expect($childKey)->not->toBeNull();

    $component
        ->set('data.title', 'Title with <script>alert(1)</script>')
        ->set("data.items.{$parentKey}.label", '<script>parent()</script>')
        ->set("data.items.{$parentKey}.children.{$childKey}.label", '<script>child()</script>')
        ->call('save')
        ->assertHasErrors([
            'data.title',
            "data.items.{$parentKey}.label",
            "data.items.{$parentKey}.children.{$childKey}.label",
        ]);

    $errors = $component->errors();
    expect($errors->first('data.title'))->toContain('must not contain HTML tags.')
        ->and($errors->first("data.items.{$parentKey}.label"))->toContain('must not contain HTML tags.')
        ->and($errors->first("data.items.{$parentKey}.children.{$childKey}.label"))->toContain('must not contain HTML tags.');

    expect($menu->fresh()->updated_at->equalTo($beforeUpdatedAt))->toBeTrue();
    expect($menu->items()->orderBy('id')->get()->toArray())->toEqual($beforeItems);
});
