<?php

declare(strict_types=1);

namespace Tests\Feature\Storefront;

use App\Domain\Content\Actions\DisableContentBlock;
use App\Domain\Content\Actions\UpdateMenu;
use App\Domain\Content\Data\UpdateMenuData;
use App\Domain\Content\Enums\MenuKey;
use App\Domain\Content\Enums\Placement;
use App\Domain\Content\Models\ContentBlock;
use App\Domain\Content\Models\Menu;
use App\Domain\Content\Models\MenuItem;
use Database\Seeders\ContentSeeder;

use function Pest\Laravel\get;

it('renders the demo storefront homepage on GET / with HTTP 200', function (): void {
    (new ContentSeeder)->run();

    $response = get('/');

    $response->assertOk();
    $response->assertSee('Good tech. Better everyday.');
    $response->assertSee('Soundcore Space 2');
    $response->assertSee('AirPods Pro 3');
    $response->assertSee('Browse categories');
    $response->assertSee('Find your next favourite');

    expect(route('shop', absolute: false))->toBe('/shop')
        ->and(route('product', ['slug' => 'test'], absolute: false))->toBe('/products/test')
        ->and(route('cart', absolute: false))->toBe('/bag')
        ->and(route('cart.store', absolute: false))->toBe('/bag')
        ->and(route('wishlist', absolute: false))->toBe('/saved')
        ->and(route('wishlist.toggle', absolute: false))->toBe('/saved')
        ->and(route('account', absolute: false))->toBe('/account')
        ->and(route('help', absolute: false))->toBe('/help')
        ->and(route('terms', absolute: false))->toBe('/terms');
});

it('dynamically renders seeded CMS settings, announcement bar, and navigation menus on the homepage', function (): void {
    (new ContentSeeder)->run();

    $response = get('/');

    $response->assertOk();
    $response->assertSee('GetNow');
    $response->assertSee('What are you looking for?');
    $response->assertSee('Thoughtfully chosen tech.');
    $response->assertSee('announcement--info');
});

it('reflects admin mutations and omits disabled announcement bar without crashing', function (): void {
    (new ContentSeeder)->run();

    $block = ContentBlock::where('placement', Placement::ANNOUNCEMENT_BAR->value)->firstOrFail();
    app(DisableContentBlock::class)($block->id);

    $response = get('/');

    $response->assertOk();
    $response->assertDontSee('<div class="announcement', false);
    $response->assertDontSee('Good tech. Better everyday.');
});

it('reflects admin changes when a menu item is updated', function (): void {
    (new ContentSeeder)->run();

    $menu = Menu::where('key', MenuKey::FOOTER_COLUMN_1->value)->firstOrFail();
    app(UpdateMenu::class)($menu, new UpdateMenuData(
        title: 'Updated Footer Column',
        items: [],
    ));

    $response = get('/');

    $response->assertOk();
    $response->assertSee('Updated Footer Column');
});

it('renders gracefully when placements and menus are empty', function (): void {
    ContentBlock::query()->delete();
    MenuItem::query()->delete();
    Menu::query()->delete();

    $response = get('/');

    $response->assertOk();
});

it('has valid teardown guide documenting all temporary demo files', function (): void {
    $path = base_path('docs/blueprints/P1-S1/DEMO-TEARDOWN.md');

    expect(file_exists($path))->toBeTrue();

    $content = (string) file_get_contents($path);

    expect($content)->toContain('Files to Remove')
        ->and($content)->toContain('Remove-Item');
});
