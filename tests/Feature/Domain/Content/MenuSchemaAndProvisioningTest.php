<?php

declare(strict_types=1);

use App\Domain\Content\Enums\MenuKey;
use App\Domain\Content\Models\Menu;
use App\Domain\Content\Models\MenuItem;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

it('provisions exactly the five code-owned menu keys on migration', function () {
    $menus = DB::table('menus')->orderBy('id')->get();

    expect($menus)->toHaveCount(5);

    $keys = $menus->pluck('key')->all();
    $expectedKeys = array_map(fn (MenuKey $case) => $case->value, MenuKey::cases());
    expect($keys)->toEqualCanonicalizing($expectedKeys);

    foreach ($menus as $menu) {
        $case = MenuKey::from($menu->key);
        expect($menu->label)->toBe($case->defaultLabel())
            ->and($menu->title)->toBeNull()
            ->and($menu->public_id)->not->toBeEmpty();
    }
});

it('rejects duplicate menu key via database unique constraint', function () {
    expect(fn () => DB::table('menus')->insert([
        'public_id' => (string) Str::ulid(),
        'key' => MenuKey::MAIN_NAV->value,
        'label' => 'Duplicate Main Nav',
        'created_at' => now(),
        'updated_at' => now(),
    ]))->toThrow(QueryException::class);
});

it('rejects negative sort_order via database check constraint', function () {
    $menu = Menu::firstOrFail();

    expect(fn () => DB::table('menu_items')->insert([
        'public_id' => (string) Str::ulid(),
        'menu_id' => $menu->id,
        'label' => 'Invalid Order Item',
        'url' => '/test',
        'sort_order' => -1,
        'created_at' => now(),
        'updated_at' => now(),
    ]))->toThrow(QueryException::class);
});

it('rejects orphan menu_id via foreign key constraint', function () {
    expect(fn () => DB::table('menu_items')->insert([
        'public_id' => (string) Str::ulid(),
        'menu_id' => 999999,
        'label' => 'Orphan Item',
        'url' => '/orphan',
        'sort_order' => 0,
        'created_at' => now(),
        'updated_at' => now(),
    ]))->toThrow(QueryException::class);
});

it('cascades deletion of menu items when menu is deleted', function () {
    $menu = Menu::firstOrFail();
    $item = MenuItem::factory()->forMenu($menu)->create();

    expect(MenuItem::where('id', $item->id)->exists())->toBeTrue();

    DB::table('menus')->where('id', $menu->id)->delete();

    expect(MenuItem::where('id', $item->id)->exists())->toBeFalse();
});

it('detects provisioning drift if an enum case has no corresponding database row', function () {
    // Delete one row to simulate drift
    DB::table('menus')->where('key', MenuKey::FOOTER_COLUMN_3->value)->delete();

    $persistedKeys = DB::table('menus')->pluck('key')->all();

    $hasMissing = false;
    foreach (MenuKey::cases() as $case) {
        if (! in_array($case->value, $persistedKeys, true)) {
            $hasMissing = true;
            break;
        }
    }

    expect($hasMissing)->toBeTrue();
});

it('resolves existing provisioned menu records via factory without unique collisions', function () {
    $menu = Menu::factory()->forKey(MenuKey::MAIN_NAV)->create(['title' => 'Updated Title']);

    expect($menu)->toBeInstanceOf(Menu::class)
        ->and($menu->key)->toBe(MenuKey::MAIN_NAV)
        ->and($menu->title)->toBe('Updated Title')
        ->and(Menu::count())->toBe(5);
});
