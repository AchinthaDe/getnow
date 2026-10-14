<?php

declare(strict_types=1);

use App\Domain\Content\Models\Menu;
use App\Filament\Admin\Resources\MenuResource;
use App\Filament\Admin\Resources\MenuResource\Pages\EditMenu;
use App\Filament\Admin\Resources\MenuResource\Pages\ListMenus;
use App\Models\User;
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
