<?php

declare(strict_types=1);

use App\Domain\Catalog\Models\Category;
use App\Domain\Content\Actions\GetLayoutContent;
use App\Domain\Content\Actions\GetMenuTree;
use App\Domain\Content\Actions\UpdateMenu;
use App\Domain\Content\Data\UpdateMenuData;
use App\Domain\Content\Data\UpdateMenuItemInputData;
use App\Domain\Content\Enums\MenuItemKind;
use App\Domain\Content\Enums\MenuKey;
use App\Domain\Content\Exceptions\InvalidMenuTreeException;
use App\Domain\Content\Models\Menu;
use App\Domain\Content\Models\MenuItem;
use Database\Factories\CategoryFactory;
use Database\Factories\UserFactory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Activity;

it('validates allowed kinds per menu key and rejects disallowed kinds', function (): void {
    $mainMenu = Menu::factory()->create(['key' => MenuKey::MAIN_NAV->value]);
    $footerMenu = Menu::factory()->create(['key' => MenuKey::FOOTER_COLUMN_1->value]);
    $category = CategoryFactory::new()->root()->create();

    // 1. footer_column_1 rejects category_tree
    $footerData = new UpdateMenuData(
        title: 'Footer With Tree',
        items: [
            new UpdateMenuItemInputData(
                public_id: null,
                label: 'Tree in Footer',
                url: null,
                kind: MenuItemKind::CATEGORY_TREE,
                category_public_id: $category->public_id,
            ),
        ]
    );

    expect(fn () => (new UpdateMenu)($footerMenu, $footerData))
        ->toThrow(InvalidMenuTreeException::class, 'Menu [footer_column_1] does not allow items of kind [category_tree].');

    // 2. main_nav accepts all three kinds
    $mainData = new UpdateMenuData(
        title: 'Main Nav With All Kinds',
        items: [
            new UpdateMenuItemInputData(
                public_id: null,
                label: 'Link Item',
                url: '/shop',
                kind: MenuItemKind::LINK,
            ),
            new UpdateMenuItemInputData(
                public_id: null,
                label: 'Category Item',
                url: null,
                kind: MenuItemKind::CATEGORY,
                category_public_id: $category->public_id,
            ),
            new UpdateMenuItemInputData(
                public_id: null,
                label: 'Tree Item',
                url: null,
                kind: MenuItemKind::CATEGORY_TREE,
                category_public_id: $category->public_id,
            ),
        ]
    );

    $savedTree = (new UpdateMenu)($mainMenu, $mainData);
    expect($savedTree->items)->toHaveCount(3)
        ->and($savedTree->items[0]->kind)->toBe(MenuItemKind::LINK)
        ->and($savedTree->items[1]->kind)->toBe(MenuItemKind::CATEGORY)
        ->and($savedTree->items[2]->kind)->toBe(MenuItemKind::CATEGORY_TREE);
});

it('leaves stored rows completely untouched when save is rejected', function (): void {
    $footerMenu = Menu::factory()->create([
        'key' => MenuKey::FOOTER_COLUMN_1->value,
        'title' => 'Original Title',
    ]);
    $existingItem = MenuItem::factory()->forMenu($footerMenu)->create([
        'label' => 'Original Link',
        'url' => '/original',
    ]);

    $invalidData = new UpdateMenuData(
        title: 'Mutated Title',
        items: [
            new UpdateMenuItemInputData(
                public_id: null,
                label: 'Disallowed Tree',
                url: null,
                kind: MenuItemKind::CATEGORY_TREE,
                category_public_id: null,
            ),
        ]
    );

    expect(fn () => (new UpdateMenu)($footerMenu, $invalidData))
        ->toThrow(InvalidMenuTreeException::class);

    $footerMenu->refresh();
    expect($footerMenu->title)->toBe('Original Title');

    $items = MenuItem::where('menu_id', $footerMenu->id)->get();
    expect($items)->toHaveCount(1)
        ->and($items[0]->public_id)->toBe($existingItem->public_id)
        ->and($items[0]->label)->toBe('Original Link')
        ->and($items[0]->url)->toBe('/original');
});

it('rejects a null link label before url checks and leaves stored rows untouched', function (): void {
    $menu = Menu::factory()->create([
        'key' => MenuKey::MAIN_NAV->value,
        'title' => 'Original Title',
    ]);
    $existingItem = MenuItem::factory()->forMenu($menu)->create([
        'label' => 'Original Link',
        'url' => '/original',
        'kind' => MenuItemKind::LINK,
        'category_public_id' => null,
    ]);

    $invalidData = new UpdateMenuData(
        title: 'Mutated Title',
        items: [
            new UpdateMenuItemInputData(
                public_id: null,
                label: null,
                url: '/shop',
                kind: MenuItemKind::LINK,
                category_public_id: null,
            ),
        ]
    );

    expect(fn () => (new UpdateMenu)($menu, $invalidData))
        ->toThrow(InvalidMenuTreeException::class, 'Label must be between 1 and 80 characters.');

    $menu->refresh();
    expect($menu->title)->toBe('Original Title');

    $items = MenuItem::where('menu_id', $menu->id)->get();
    expect($items)->toHaveCount(1)
        ->and($items[0]->public_id)->toBe($existingItem->public_id)
        ->and($items[0]->label)->toBe('Original Link')
        ->and($items[0]->url)->toBe('/original')
        ->and($items[0]->kind)->toBe(MenuItemKind::LINK)
        ->and($items[0]->category_public_id)->toBeNull();
});

it('persists category items with reference and optional label while rejecting invalid shapes', function (Closure $dataBuilder, ?string $expectedExceptionMessage): void {
    $menu = Menu::factory()->create(['key' => MenuKey::MAIN_NAV->value]);
    $cat = CategoryFactory::new()->root()->create(['name' => 'Active Cat']);
    $inactiveCat = CategoryFactory::new()->root()->create(['name' => 'Inactive Cat', 'is_active' => false]);

    /** @var UpdateMenuItemInputData $inputItem */
    $inputItem = $dataBuilder($cat, $inactiveCat);
    $data = new UpdateMenuData(title: 'Category Nav', items: [$inputItem]);

    if ($expectedExceptionMessage !== null) {
        expect(fn () => (new UpdateMenu)($menu, $data))
            ->toThrow(InvalidMenuTreeException::class, $expectedExceptionMessage);
    } else {
        $savedTree = (new UpdateMenu)($menu, $data);
        expect($savedTree->items)->toHaveCount(1);
        $item = $savedTree->items[0];
        expect($item->kind)->toBe(MenuItemKind::CATEGORY)
            ->and($item->category_public_id)->toBe($inputItem->category_public_id)
            ->and($item->url)->toBeNull();

        $storedRecord = MenuItem::where('public_id', $item->public_id)->firstOrFail();
        expect($storedRecord->kind)->toBe(MenuItemKind::CATEGORY)
            ->and($storedRecord->category_public_id)->toBe($inputItem->category_public_id)
            ->and($storedRecord->url)->toBeNull();

        if ($inputItem->label !== null && trim($inputItem->label) !== '') {
            expect($storedRecord->label)->toBe(trim($inputItem->label));
        } else {
            expect($storedRecord->label)->toBeNull();
        }
    }
})->with([
    'valid category with reference and label' => [
        fn (Category $cat, Category $inactiveCat): UpdateMenuItemInputData => new UpdateMenuItemInputData(
            public_id: null,
            label: 'Phones',
            url: null,
            kind: MenuItemKind::CATEGORY,
            category_public_id: $cat->public_id,
        ),
        null,
    ],
    'valid category with reference and null label' => [
        fn (Category $cat, Category $inactiveCat): UpdateMenuItemInputData => new UpdateMenuItemInputData(
            public_id: null,
            label: null,
            url: null,
            kind: MenuItemKind::CATEGORY,
            category_public_id: $cat->public_id,
        ),
        null,
    ],
    'valid category with inactive category' => [
        fn (Category $cat, Category $inactiveCat): UpdateMenuItemInputData => new UpdateMenuItemInputData(
            public_id: null,
            label: 'Inactive Cat Item',
            url: null,
            kind: MenuItemKind::CATEGORY,
            category_public_id: $inactiveCat->public_id,
        ),
        null,
    ],
    'invalid category with non-existent public_id' => [
        fn (Category $cat, Category $inactiveCat): UpdateMenuItemInputData => new UpdateMenuItemInputData(
            public_id: null,
            label: 'Unknown Cat',
            url: null,
            kind: MenuItemKind::CATEGORY,
            category_public_id: (string) Str::ulid(),
        ),
        'does not exist',
    ],
    'invalid category with provided url' => [
        fn (Category $cat, Category $inactiveCat): UpdateMenuItemInputData => new UpdateMenuItemInputData(
            public_id: null,
            label: 'With URL',
            url: '/invalid',
            kind: MenuItemKind::CATEGORY,
            category_public_id: $cat->public_id,
        ),
        'URL is not allowed for menu items of kind [category].',
    ],
    'invalid category with missing category_public_id' => [
        fn (Category $cat, Category $inactiveCat): UpdateMenuItemInputData => new UpdateMenuItemInputData(
            public_id: null,
            label: 'No Ref',
            url: null,
            kind: MenuItemKind::CATEGORY,
            category_public_id: null,
        ),
        'Category reference is required for category menu items.',
    ],
    'invalid category with label over 80 chars' => [
        fn (Category $cat, Category $inactiveCat): UpdateMenuItemInputData => new UpdateMenuItemInputData(
            public_id: null,
            label: str_repeat('a', 81),
            url: null,
            kind: MenuItemKind::CATEGORY,
            category_public_id: $cat->public_id,
        ),
        'Label must be between 1 and 80 characters.',
    ],
    'invalid link with category reference' => [
        fn (Category $cat, Category $inactiveCat): UpdateMenuItemInputData => new UpdateMenuItemInputData(
            public_id: null,
            label: 'Link With Ref',
            url: '/valid',
            kind: MenuItemKind::LINK,
            category_public_id: $cat->public_id,
        ),
        'Category reference is not allowed for menu items of kind [link].',
    ],
]);

it('persists category tree items at level 1 and rejects level 2, children, url, or unknown references', function (Closure $dataBuilder, ?string $expectedExceptionMessage): void {
    $menu = Menu::factory()->create(['key' => MenuKey::MAIN_NAV->value]);
    $cat = CategoryFactory::new()->root()->create(['name' => 'Tree Reference Cat']);

    /** @var UpdateMenuItemInputData $inputItem */
    $inputItem = $dataBuilder($cat);
    $data = new UpdateMenuData(title: 'Tree Nav', items: [$inputItem]);

    if ($expectedExceptionMessage !== null) {
        expect(fn () => (new UpdateMenu)($menu, $data))
            ->toThrow(InvalidMenuTreeException::class, $expectedExceptionMessage);
    } else {
        $savedTree = (new UpdateMenu)($menu, $data);
        expect($savedTree->items)->toHaveCount(1);
        $item = $savedTree->items[0];
        expect($item->kind)->toBe(MenuItemKind::CATEGORY_TREE)
            ->and($item->category_public_id)->toBe($inputItem->category_public_id)
            ->and($item->url)->toBeNull();

        $storedRecord = MenuItem::where('public_id', $item->public_id)->firstOrFail();
        expect($storedRecord->kind)->toBe(MenuItemKind::CATEGORY_TREE)
            ->and($storedRecord->category_public_id)->toBe($inputItem->category_public_id)
            ->and($storedRecord->url)->toBeNull();
    }
})->with([
    'valid category_tree without category reference' => [
        fn (Category $cat): UpdateMenuItemInputData => new UpdateMenuItemInputData(
            public_id: null,
            label: 'Full Tree',
            url: null,
            kind: MenuItemKind::CATEGORY_TREE,
            category_public_id: null,
        ),
        null,
    ],
    'valid category_tree with category reference' => [
        fn (Category $cat): UpdateMenuItemInputData => new UpdateMenuItemInputData(
            public_id: null,
            label: 'Subtree',
            url: null,
            kind: MenuItemKind::CATEGORY_TREE,
            category_public_id: $cat->public_id,
        ),
        null,
    ],
    'invalid category_tree at level 2' => [
        fn (Category $cat): UpdateMenuItemInputData => new UpdateMenuItemInputData(
            public_id: null,
            label: 'Link Parent',
            url: '/parent',
            kind: MenuItemKind::LINK,
            children: [
                new UpdateMenuItemInputData(
                    public_id: null,
                    label: 'Tree Child',
                    url: null,
                    kind: MenuItemKind::CATEGORY_TREE,
                    category_public_id: null,
                ),
            ],
        ),
        'Category tree items are allowed at level 1 only.',
    ],
    'invalid category_tree with manual children' => [
        fn (Category $cat): UpdateMenuItemInputData => new UpdateMenuItemInputData(
            public_id: null,
            label: 'Tree Root',
            url: null,
            kind: MenuItemKind::CATEGORY_TREE,
            category_public_id: null,
            children: [
                new UpdateMenuItemInputData(
                    public_id: null,
                    label: 'Child Under Tree',
                    url: '/child',
                    kind: MenuItemKind::LINK,
                ),
            ],
        ),
        'Category tree items cannot define child items.',
    ],
    'invalid category_tree with non-null url' => [
        fn (Category $cat): UpdateMenuItemInputData => new UpdateMenuItemInputData(
            public_id: null,
            label: 'Tree With URL',
            url: '/tree',
            kind: MenuItemKind::CATEGORY_TREE,
            category_public_id: null,
        ),
        'URL is not allowed for menu items of kind [category_tree].',
    ],
    'invalid category_tree with unknown category reference' => [
        fn (Category $cat): UpdateMenuItemInputData => new UpdateMenuItemInputData(
            public_id: null,
            label: 'Tree Unknown',
            url: null,
            kind: MenuItemKind::CATEGORY_TREE,
            category_public_id: (string) Str::ulid(),
        ),
        'does not exist',
    ],
]);

it('batches catalog category existence queries', function (): void {
    $menu = Menu::factory()->create(['key' => MenuKey::MAIN_NAV->value]);
    $cat1 = CategoryFactory::new()->root()->create(['name' => 'Cat 1']);
    $cat2 = CategoryFactory::new()->root()->create(['name' => 'Cat 2']);
    $cat3 = CategoryFactory::new()->root()->create(['name' => 'Cat 3']);

    $data = new UpdateMenuData(
        title: 'Nav With Categories',
        items: [
            new UpdateMenuItemInputData(public_id: null, label: 'Cat 1 Item', url: null, kind: MenuItemKind::CATEGORY, category_public_id: $cat1->public_id),
            new UpdateMenuItemInputData(public_id: null, label: 'Cat 2 Item', url: null, kind: MenuItemKind::CATEGORY, category_public_id: $cat2->public_id),
            new UpdateMenuItemInputData(public_id: null, label: 'Cat 3 Item', url: null, kind: MenuItemKind::CATEGORY, category_public_id: $cat3->public_id),
            new UpdateMenuItemInputData(public_id: null, label: 'Tree 1', url: null, kind: MenuItemKind::CATEGORY_TREE, category_public_id: $cat1->public_id),
            new UpdateMenuItemInputData(public_id: null, label: 'Tree 2', url: null, kind: MenuItemKind::CATEGORY_TREE, category_public_id: $cat2->public_id),
        ]
    );

    DB::flushQueryLog();
    DB::enableQueryLog();

    (new UpdateMenu)($menu, $data);

    $queries = DB::getQueryLog();
    expect($queries)->not->toBeEmpty();

    $categoryQueries = array_filter(
        $queries,
        fn (array $q): bool => str_contains(strtolower((string) $q['query']), 'from "categories"')
            || str_contains(strtolower((string) $q['query']), 'from categories')
    );

    expect(count($categoryQueries))->toBe(1);

    // Zero category references executes 0 queries against categories:
    $linkOnlyData = new UpdateMenuData(
        title: 'Link Only Nav',
        items: [
            new UpdateMenuItemInputData(public_id: null, label: 'Link 1', url: '/link-1', kind: MenuItemKind::LINK),
            new UpdateMenuItemInputData(public_id: null, label: 'Link 2', url: '/link-2', kind: MenuItemKind::LINK),
        ]
    );

    DB::flushQueryLog();

    (new UpdateMenu)($menu, $linkOnlyData);

    $linkQueries = DB::getQueryLog();
    $linkCategoryQueries = array_filter(
        $linkQueries,
        fn (array $q): bool => str_contains(strtolower((string) $q['query']), 'from "categories"')
            || str_contains(strtolower((string) $q['query']), 'from categories')
    );

    expect(count($linkCategoryQueries))->toBe(0);

    DB::disableQueryLog();
});

it('translates concurrent category deletion during save into categoryDeletedDuringSave violation', function (): void {
    $menu = Menu::factory()->create(['key' => MenuKey::MAIN_NAV->value]);
    $category = CategoryFactory::new()->root()->create(['name' => 'Concurrent Deleted Cat']);

    $data = new UpdateMenuData(
        title: 'Concurrent Race Menu',
        items: [
            new UpdateMenuItemInputData(
                public_id: null,
                label: 'Race Cat Item',
                url: null,
                kind: MenuItemKind::CATEGORY,
                category_public_id: $category->public_id,
            ),
        ]
    );

    MenuItem::creating(function () use ($category): void {
        $deleted = DB::delete('DELETE FROM categories WHERE id = ?', [$category->id]);
        expect($deleted)->toBe(1);
    });

    try {
        expect(fn () => (new UpdateMenu)($menu, $data))
            ->toThrow(InvalidMenuTreeException::class, 'A referenced category was deleted during save.');
    } finally {
        MenuItem::flushEventListeners();
    }
});

it('reconciles item kinds, clears mismatched attributes, and records typed properties in activity log', function (): void {
    $admin = UserFactory::new()->platformAdmin()->create();
    $menu = Menu::factory()->create(['key' => MenuKey::MAIN_NAV->value]);
    $cat1 = CategoryFactory::new()->root()->create(['name' => 'Cat 1']);
    $cat2 = CategoryFactory::new()->root()->create(['name' => 'Cat 2']);

    // Initial save: 1 link item, 1 category item
    $initialData = new UpdateMenuData(
        title: 'Initial Title',
        items: [
            new UpdateMenuItemInputData(public_id: null, label: 'Initial Link', url: '/initial-link', kind: MenuItemKind::LINK),
            new UpdateMenuItemInputData(public_id: null, label: 'Initial Cat', url: null, kind: MenuItemKind::CATEGORY, category_public_id: $cat1->public_id),
        ]
    );

    $initialTree = (new UpdateMenu)($menu, $initialData, $admin);
    $item1PublicId = $initialTree->items[0]->public_id;
    $item2PublicId = $initialTree->items[1]->public_id;

    // Step 1: Re-save unchanged tree mapping explicitly with kind and category_public_id
    $resaveData = new UpdateMenuData(
        title: 'Initial Title',
        items: [
            new UpdateMenuItemInputData(
                public_id: $item1PublicId,
                label: 'Initial Link',
                url: '/initial-link',
                kind: MenuItemKind::LINK,
                category_public_id: null,
            ),
            new UpdateMenuItemInputData(
                public_id: $item2PublicId,
                label: 'Initial Cat',
                url: null,
                kind: MenuItemKind::CATEGORY,
                category_public_id: $cat1->public_id,
            ),
        ]
    );

    $resavedTree = (new UpdateMenu)($menu, $resaveData, $admin);
    expect($resavedTree->items[0]->public_id)->toBe($item1PublicId)
        ->and($resavedTree->items[1]->public_id)->toBe($item2PublicId);

    // Step 2 & 3: Change item 1 from link to category, change item 2 from category to link
    $switchData = new UpdateMenuData(
        title: 'Switched Title',
        items: [
            new UpdateMenuItemInputData(
                public_id: $item1PublicId,
                label: 'Switched to Cat',
                url: null,
                kind: MenuItemKind::CATEGORY,
                category_public_id: $cat2->public_id,
            ),
            new UpdateMenuItemInputData(
                public_id: $item2PublicId,
                label: 'Switched to Link',
                url: '/switched-link',
                kind: MenuItemKind::LINK,
                category_public_id: null,
            ),
        ]
    );

    (new UpdateMenu)($menu, $switchData, $admin);

    $record1 = MenuItem::where('public_id', $item1PublicId)->firstOrFail();
    expect($record1->kind)->toBe(MenuItemKind::CATEGORY)
        ->and($record1->url)->toBeNull()
        ->and($record1->category_public_id)->toBe($cat2->public_id);

    $record2 = MenuItem::where('public_id', $item2PublicId)->firstOrFail();
    expect($record2->kind)->toBe(MenuItemKind::LINK)
        ->and($record2->url)->toBe('/switched-link')
        ->and($record2->category_public_id)->toBeNull();

    // Step 4: Omit item 2
    $omitData = new UpdateMenuData(
        title: 'Omitted Title',
        items: [
            new UpdateMenuItemInputData(
                public_id: $item1PublicId,
                label: 'Switched to Cat',
                url: null,
                kind: MenuItemKind::CATEGORY,
                category_public_id: $cat2->public_id,
            ),
        ]
    );

    (new UpdateMenu)($menu, $omitData, $admin);

    expect(MenuItem::where('public_id', $item2PublicId)->exists())->toBeFalse();

    // Check activity log has old_items and new_items with kind and category_public_id
    $activity = Activity::where('log_name', 'content')->latest('id')->firstOrFail();
    expect($activity->properties->has('old_items'))->toBeTrue()
        ->and($activity->properties->has('new_items'))->toBeTrue();

    /** @var array<int, array<string, mixed>> $newItems */
    $newItems = $activity->properties['new_items'];
    expect($newItems[0])->toHaveKey('kind')
        ->and($newItems[0])->toHaveKey('category_public_id')
        ->and($newItems[0]['kind'])->toBe('category')
        ->and($newItems[0]['category_public_id'])->toBe($cat2->public_id);
});

it('returns typed kind and category reference in GetMenuTree output', function (): void {
    $menu = Menu::factory()->create(['key' => MenuKey::MAIN_NAV->value]);
    $cat = CategoryFactory::new()->root()->create(['name' => 'Tree Cat']);

    MenuItem::factory()->forMenu($menu)->create([
        'label' => 'Link Item',
        'url' => '/link',
        'sort_order' => 0,
    ]);
    MenuItem::factory()->forMenu($menu)->category($cat->public_id, 'Cat Item')->create([
        'sort_order' => 1,
    ]);
    MenuItem::factory()->forMenu($menu)->categoryTree($cat->public_id, 'Tree Item')->create([
        'sort_order' => 2,
    ]);

    $treeData = (new GetMenuTree)($menu);

    expect($treeData->items)->toHaveCount(3)
        ->and($treeData->items[0]->kind)->toBe(MenuItemKind::LINK)
        ->and($treeData->items[0]->category_public_id)->toBeNull()
        ->and($treeData->items[1]->kind)->toBe(MenuItemKind::CATEGORY)
        ->and($treeData->items[1]->category_public_id)->toBe($cat->public_id)
        ->and($treeData->items[2]->kind)->toBe(MenuItemKind::CATEGORY_TREE)
        ->and($treeData->items[2]->category_public_id)->toBe($cat->public_id);
});

it('omits typed category items and their children from layout content api until NAV1b', function (): void {
    $menu = Menu::factory()->create(['key' => MenuKey::MAIN_NAV->value]);
    $cat = CategoryFactory::new()->root()->create(['name' => 'API Cat']);

    // 1. Link root with link child
    $linkRoot = MenuItem::factory()->forMenu($menu)->create([
        'label' => 'Link Root',
        'url' => '/link-root',
        'sort_order' => 0,
    ]);
    MenuItem::factory()->forMenu($menu)->childOf($linkRoot)->create([
        'label' => 'Link Child',
        'url' => '/link-child',
        'sort_order' => 0,
    ]);

    // 2. Link root with category child
    $linkRoot2 = MenuItem::factory()->forMenu($menu)->create([
        'label' => 'Link Root 2',
        'url' => '/link-root-2',
        'sort_order' => 1,
    ]);
    MenuItem::factory()->forMenu($menu)->childOf($linkRoot2)->category($cat->public_id)->create([
        'sort_order' => 0,
    ]);

    // 3. Category root with link child (typed root with link child)
    $catRoot = MenuItem::factory()->forMenu($menu)->category($cat->public_id, 'Cat Root')->create([
        'sort_order' => 2,
    ]);
    MenuItem::factory()->forMenu($menu)->childOf($catRoot)->create([
        'label' => 'Link Under Cat',
        'url' => '/link-under-cat',
        'sort_order' => 0,
    ]);

    // 4. Category tree root
    MenuItem::factory()->forMenu($menu)->categoryTree($cat->public_id, 'Tree Root')->create([
        'sort_order' => 3,
    ]);

    $response = app(GetLayoutContent::class)();
    $mainNavItems = $response->data->menus[MenuKey::MAIN_NAV->value]->items;

    // Asserts:
    // Only linkRoot and linkRoot2 appear
    expect($mainNavItems)->toHaveCount(2)
        ->and($mainNavItems[0]->id)->toBe($linkRoot->public_id)
        ->and($mainNavItems[0]->label)->toBe('Link Root')
        ->and($mainNavItems[0]->url)->toBe('/link-root')
        ->and($mainNavItems[0]->children)->toHaveCount(1)
        ->and($mainNavItems[0]->children[0]->label)->toBe('Link Child')
        ->and($mainNavItems[0]->children[0]->url)->toBe('/link-child')
        // linkRoot2 has 0 children because its category child was filtered out
        ->and($mainNavItems[1]->id)->toBe($linkRoot2->public_id)
        ->and($mainNavItems[1]->label)->toBe('Link Root 2')
        ->and($mainNavItems[1]->url)->toBe('/link-root-2')
        ->and($mainNavItems[1]->children)->toBeEmpty();
});
