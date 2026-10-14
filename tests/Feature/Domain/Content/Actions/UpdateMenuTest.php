<?php

declare(strict_types=1);

use App\Domain\Content\Actions\GetMenuTree;
use App\Domain\Content\Actions\UpdateMenu;
use App\Domain\Content\Data\UpdateMenuData;
use App\Domain\Content\Data\UpdateMenuItemInputData;
use App\Domain\Content\Enums\MenuKey;
use App\Domain\Content\Exceptions\InvalidMenuTreeException;
use App\Domain\Content\Models\Menu;
use App\Domain\Content\Models\MenuItem;
use App\Models\User;
use Spatie\Activitylog\Models\Activity;

it('replaces menu title and items atomically under a row lock', function () {
    $admin = User::factory()->platformAdmin()->create();
    $updateData = new UpdateMenuData(
        title: 'Main Storefront Nav',
        items: [
            new UpdateMenuItemInputData(
                public_id: null,
                label: 'Shop Phones',
                url: '/shop/phones',
            ),
            new UpdateMenuItemInputData(
                public_id: null,
                label: 'Deals',
                url: '/deals',
            ),
        ],
    );

    $tree = app(UpdateMenu::class)(MenuKey::MAIN_NAV, $updateData, $admin);

    expect($tree->title)->toBe('Main Storefront Nav')
        ->and($tree->items)->toHaveCount(2)
        ->and($tree->items[0]->label)->toBe('Shop Phones')
        ->and($tree->items[0]->sort_order)->toBe(0)
        ->and($tree->items[1]->label)->toBe('Deals')
        ->and($tree->items[1]->sort_order)->toBe(1);

    $menu = Menu::where('key', MenuKey::MAIN_NAV->value)->firstOrFail();
    expect($menu->title)->toBe('Main Storefront Nav')
        ->and($menu->items)->toHaveCount(2);
});

it('promotes child item safely when parent is deleted in same update', function () {
    $admin = User::factory()->platformAdmin()->create();
    $menu = Menu::where('key', MenuKey::MAIN_NAV->value)->firstOrFail();

    $parentItem = MenuItem::factory()->forMenu($menu)->create([
        'label' => 'Parent to delete',
        'parent_id' => null,
    ]);
    $childItem = MenuItem::factory()->childOf($parentItem)->create([
        'label' => 'Child to promote',
    ]);

    $childOriginalPublicId = $childItem->public_id;

    // Submit update where parent is omitted and child is promoted to top-level
    $updateData = new UpdateMenuData(
        title: 'Promoted Test',
        items: [
            new UpdateMenuItemInputData(
                public_id: $childOriginalPublicId,
                label: 'Promoted Item',
                url: '/promoted',
            ),
        ],
    );

    $tree = app(UpdateMenu::class)($menu, $updateData, $admin);

    expect($tree->items)->toHaveCount(1)
        ->and($tree->items[0]->public_id)->toBe($childOriginalPublicId)
        ->and($tree->items[0]->label)->toBe('Promoted Item');

    // Assert parent was deleted from database
    expect(MenuItem::where('id', $parentItem->id)->exists())->toBeFalse();

    // Assert child survived with same public_id and is now root
    $survivingChild = MenuItem::where('public_id', $childOriginalPublicId)->firstOrFail();
    expect($survivingChild->parent_id)->toBeNull()
        ->and($survivingChild->label)->toBe('Promoted Item');
});

it('preserves public_id values on item reordering and reparenting', function () {
    $admin = User::factory()->platformAdmin()->create();
    $menu = Menu::where('key', MenuKey::MAIN_NAV->value)->firstOrFail();

    $itemX = MenuItem::factory()->forMenu($menu)->create(['label' => 'Item X', 'sort_order' => 0]);
    $itemY = MenuItem::factory()->forMenu($menu)->create(['label' => 'Item Y', 'sort_order' => 1]);
    $childX1 = MenuItem::factory()->childOf($itemX)->create(['label' => 'Child X1', 'sort_order' => 0]);

    // Reorder: Y first, then X, with childX1 moved under Y
    $updateData = new UpdateMenuData(
        title: 'Reordered',
        items: [
            new UpdateMenuItemInputData(
                public_id: $itemY->public_id,
                label: 'Item Y',
                url: $itemY->url,
                children: [
                    new UpdateMenuItemInputData(
                        public_id: $childX1->public_id,
                        label: 'Child X1 under Y',
                        url: $childX1->url,
                    ),
                ],
            ),
            new UpdateMenuItemInputData(
                public_id: $itemX->public_id,
                label: 'Item X',
                url: $itemX->url,
                children: [],
            ),
        ],
    );

    $tree = app(UpdateMenu::class)($menu, $updateData, $admin);

    expect($tree->items[0]->public_id)->toBe($itemY->public_id)
        ->and($tree->items[0]->sort_order)->toBe(0)
        ->and($tree->items[0]->children[0]->public_id)->toBe($childX1->public_id)
        ->and($tree->items[0]->children[0]->label)->toBe('Child X1 under Y')
        ->and($tree->items[1]->public_id)->toBe($itemX->public_id)
        ->and($tree->items[1]->sort_order)->toBe(1);
});

it('generates new public_ids for newly added items', function () {
    $admin = User::factory()->platformAdmin()->create();
    $menu = Menu::where('key', MenuKey::MAIN_NAV->value)->firstOrFail();

    $existingItem = MenuItem::factory()->forMenu($menu)->create(['label' => 'Existing']);

    $updateData = new UpdateMenuData(
        title: null,
        items: [
            new UpdateMenuItemInputData(
                public_id: $existingItem->public_id,
                label: 'Existing Item',
                url: '/existing',
            ),
            new UpdateMenuItemInputData(
                public_id: null,
                label: 'Brand New Item',
                url: '/brand-new',
            ),
        ],
    );

    $tree = app(UpdateMenu::class)($menu, $updateData, $admin);

    expect($tree->items)->toHaveCount(2)
        ->and($tree->items[0]->public_id)->toBe($existingItem->public_id)
        ->and($tree->items[1]->public_id)->not->toBeEmpty()
        ->and($tree->items[1]->public_id)->not->toBe($existingItem->public_id);
});

it('hard deletes items omitted from submission', function () {
    $admin = User::factory()->platformAdmin()->create();
    $menu = Menu::where('key', MenuKey::MAIN_NAV->value)->firstOrFail();

    $item1 = MenuItem::factory()->forMenu($menu)->create(['label' => 'Item 1']);
    $item2 = MenuItem::factory()->forMenu($menu)->create(['label' => 'Item 2']);
    $item3 = MenuItem::factory()->forMenu($menu)->create(['label' => 'Item 3']);

    $updateData = new UpdateMenuData(
        title: null,
        items: [
            new UpdateMenuItemInputData(
                public_id: $item2->public_id,
                label: 'Item 2 Kept',
                url: '/item-2',
            ),
        ],
    );

    app(UpdateMenu::class)($menu, $updateData, $admin);

    expect(MenuItem::where('id', $item1->id)->exists())->toBeFalse()
        ->and(MenuItem::where('id', $item3->id)->exists())->toBeFalse()
        ->and(MenuItem::where('id', $item2->id)->exists())->toBeTrue();
});

it('empties menu when items array is empty', function () {
    $admin = User::factory()->platformAdmin()->create();
    $menu = Menu::where('key', MenuKey::MAIN_NAV->value)->firstOrFail();

    MenuItem::factory()->count(2)->forMenu($menu)->create();

    $updateData = new UpdateMenuData(
        title: 'Empty Tree',
        items: [],
    );

    $tree = app(UpdateMenu::class)($menu, $updateData, $admin);

    expect($tree->items)->toBeEmpty()
        ->and($menu->items()->count())->toBe(0);
});

it('rolls back database modifications when an exception is triggered mid-transaction', function () {
    $admin = User::factory()->platformAdmin()->create();
    $menu = Menu::where('key', MenuKey::MAIN_NAV->value)->firstOrFail();
    $menu->update(['title' => 'Initial Title']);

    $existingItem = MenuItem::factory()->forMenu($menu)->create(['label' => 'Original Item']);

    // Hook into MenuItem saving to throw mid-transaction during child persistence
    MenuItem::saving(function (MenuItem $item) {
        if ($item->label === 'Throw Mid-Transaction') {
            throw new RuntimeException('Simulated mid-transaction failure');
        }
    });

    $updateData = new UpdateMenuData(
        title: 'New Title That Should Rollback',
        items: [
            new UpdateMenuItemInputData(
                public_id: $existingItem->public_id,
                label: 'Updated Root Item',
                url: '/root',
                children: [
                    new UpdateMenuItemInputData(
                        public_id: null,
                        label: 'Throw Mid-Transaction',
                        url: '/child',
                    ),
                ],
            ),
        ],
    );

    try {
        app(UpdateMenu::class)($menu, $updateData, $admin);
    } catch (RuntimeException) {
        // Expected
    }

    $menu->refresh();
    expect($menu->title)->toBe('Initial Title')
        ->and($menu->items()->count())->toBe(1)
        ->and($menu->items()->first()->label)->toBe('Original Item');
});

it('records activity log entry with causer and plain array tree diff, zero on rejection, and two on two saves', function () {
    $admin = User::factory()->platformAdmin()->create();
    $menu = Menu::where('key', MenuKey::MAIN_NAV->value)->firstOrFail();

    $initialCount = Activity::where('subject_type', Menu::class)
        ->where('subject_id', $menu->id)
        ->count();

    $updateData1 = new UpdateMenuData(
        title: 'Audited Title 1',
        items: [
            new UpdateMenuItemInputData(
                public_id: null,
                label: 'Audited Item 1',
                url: '/audited-1',
            ),
        ],
    );

    // Save 1: Successful save writes exactly one activity entry
    app(UpdateMenu::class)($menu, $updateData1, $admin);

    $activitiesAfterSave1 = Activity::where('subject_type', Menu::class)
        ->where('subject_id', $menu->id)
        ->get();

    expect($activitiesAfterSave1)->toHaveCount($initialCount + 1);

    $activity1 = $activitiesAfterSave1->last();
    expect($activity1->causer_id)->toBe($admin->id)
        ->and($activity1->properties->has('old_items'))->toBeTrue()
        ->and($activity1->properties->has('new_items'))->toBeTrue()
        ->and(is_array($activity1->properties['new_items']))->toBeTrue()
        ->and($activity1->properties['new_items'][0]['label'])->toBe('Audited Item 1');

    // Rejection: Invalid URL must throw exception and write zero activity log entries
    $invalidData = new UpdateMenuData(
        title: 'Audited Title 2',
        items: [
            new UpdateMenuItemInputData(
                public_id: null,
                label: 'Invalid Item',
                url: 'javascript:alert(1)',
            ),
        ],
    );

    try {
        app(UpdateMenu::class)($menu, $invalidData, $admin);
    } catch (InvalidMenuTreeException) {
        // Expected domain exception
    }

    $activitiesAfterRejection = Activity::where('subject_type', Menu::class)
        ->where('subject_id', $menu->id)
        ->count();
    expect($activitiesAfterRejection)->toBe($initialCount + 1);

    // Save 2: Second consecutive successful save writes a second activity entry (2 total)
    $updateData2 = new UpdateMenuData(
        title: 'Audited Title 2',
        items: [
            new UpdateMenuItemInputData(
                public_id: null,
                label: 'Audited Item 2',
                url: '/audited-2',
            ),
        ],
    );

    app(UpdateMenu::class)($menu, $updateData2, $admin);

    $activitiesAfterSave2 = Activity::where('subject_type', Menu::class)
        ->where('subject_id', $menu->id)
        ->count();

    expect($activitiesAfterSave2)->toBe($initialCount + 2);
});

it('bumps menu updated_at when only items change', function () {
    $admin = User::factory()->platformAdmin()->create();
    $menu = Menu::where('key', MenuKey::MAIN_NAV->value)->firstOrFail();
    $menu->update(['title' => 'Constant Title', 'updated_at' => now()->subMinutes(10)]);
    $initialUpdatedAt = $menu->fresh()->updated_at;

    $updateData = new UpdateMenuData(
        title: 'Constant Title',
        items: [
            new UpdateMenuItemInputData(
                public_id: null,
                label: 'New Item Only',
                url: '/new-item',
            ),
        ],
    );

    app(UpdateMenu::class)($menu, $updateData, $admin);

    $menu->refresh();
    expect($menu->updated_at->isAfter($initialUpdatedAt))->toBeTrue();
});

it('accepts integer menu id as action identifier across actions', function () {
    $admin = User::factory()->platformAdmin()->create();
    $menu = Menu::where('key', MenuKey::MAIN_NAV->value)->firstOrFail();

    $tree = app(GetMenuTree::class)($menu->id);
    expect($tree->key)->toBe(MenuKey::MAIN_NAV);

    $updateData = new UpdateMenuData(
        title: 'Updated Via Int ID',
        items: [],
    );

    $savedTree = app(UpdateMenu::class)($menu->id, $updateData, $admin);
    expect($savedTree->title)->toBe('Updated Via Int ID');
});
