<?php

declare(strict_types=1);

use App\Domain\Content\Actions\GetMenuTree;
use App\Domain\Content\Data\MenuTreeData;
use App\Domain\Content\Enums\MenuKey;
use App\Domain\Content\Models\Menu;
use App\Domain\Content\Models\MenuItem;
use Illuminate\Support\Facades\DB;

it('returns two-level menu tree ordered by sort_order', function () {
    $menu = Menu::factory()->forKey(MenuKey::MAIN_NAV)->create();

    // Create root item A (sort_order = 1) and root item B (sort_order = 0)
    $itemA = MenuItem::factory()->forMenu($menu)->create([
        'label' => 'Item A',
        'sort_order' => 1,
        'parent_id' => null,
    ]);
    $itemB = MenuItem::factory()->forMenu($menu)->create([
        'label' => 'Item B',
        'sort_order' => 0,
        'parent_id' => null,
    ]);

    // Create child items under A: child A1 (sort_order = 1) and child A2 (sort_order = 0)
    $childA1 = MenuItem::factory()->childOf($itemA)->create([
        'label' => 'Child A1',
        'sort_order' => 1,
    ]);
    $childA2 = MenuItem::factory()->childOf($itemA)->create([
        'label' => 'Child A2',
        'sort_order' => 0,
    ]);

    $tree = app(GetMenuTree::class)($menu);

    expect($tree)->toBeInstanceOf(MenuTreeData::class)
        ->and($tree->public_id)->toBe($menu->public_id)
        ->and($tree->key)->toBe(MenuKey::MAIN_NAV)
        ->and($tree->items)->toHaveCount(2)
        ->and($tree->items[0]->label)->toBe('Item B')
        ->and($tree->items[0]->sort_order)->toBe(0)
        ->and($tree->items[0]->children)->toBeEmpty()
        ->and($tree->items[1]->label)->toBe('Item A')
        ->and($tree->items[1]->sort_order)->toBe(1)
        ->and($tree->items[1]->children)->toHaveCount(2)
        ->and($tree->items[1]->children[0]->label)->toBe('Child A2')
        ->and($tree->items[1]->children[0]->sort_order)->toBe(0)
        ->and($tree->items[1]->children[1]->label)->toBe('Child A1')
        ->and($tree->items[1]->children[1]->sort_order)->toBe(1);
});

it('executes menu tree retrieval within constant query bounds', function () {
    $menu = Menu::factory()->forKey(MenuKey::MAIN_NAV)->create();

    // 10 root items, each with 10 child items (100 total items)
    for ($i = 0; $i < 10; $i++) {
        $parent = MenuItem::factory()->forMenu($menu)->create([
            'sort_order' => $i,
            'parent_id' => null,
        ]);
        for ($j = 0; $j < 10; $j++) {
            MenuItem::factory()->childOf($parent)->create([
                'sort_order' => $j,
            ]);
        }
    }

    DB::enableQueryLog();

    // Invoking with Menu model instance executes 1 query for items
    $tree = app(GetMenuTree::class)($menu);

    $queries = DB::getQueryLog();
    expect($queries)->toHaveCount(1)
        ->and($tree->items)->toHaveCount(10);

    // Invoking with key executes exactly 2 queries (1 for Menu, 1 for items)
    DB::flushQueryLog();
    $treeByKey = app(GetMenuTree::class)(MenuKey::MAIN_NAV);
    $queriesByKey = DB::getQueryLog();
    expect($queriesByKey)->toHaveCount(2)
        ->and($treeByKey->items)->toHaveCount(10);
});
