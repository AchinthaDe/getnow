<?php

declare(strict_types=1);

namespace Tests\Feature\Storefront;

use App\Domain\Content\Actions\DisableContentBlock;
use App\Domain\Content\Actions\UpdateContentBlock;
use App\Domain\Content\Actions\UpdateMenu;
use App\Domain\Content\Data\UpdateContentBlockData;
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

it('renders full-bar clickable link target when announcement link_url is present', function (): void {
    (new ContentSeeder)->run();

    $block = ContentBlock::where('placement', Placement::ANNOUNCEMENT_BAR->value)->firstOrFail();
    $payload = $block->payload;
    $payload['link_url'] = '/shop?deals=1';

    app(UpdateContentBlock::class)($block->id, new UpdateContentBlockData(starts_at: null, ends_at: null), $payload);

    $response = get('/');

    $response->assertOk();
    $response->assertSee('<a href="/shop?deals=1" class="announcement announcement--info announcement--link">', false);
});

it('renders secondary and locale text dynamically on announcement bar', function (): void {
    (new ContentSeeder)->run();

    $response = get('/');

    $response->assertOk();
    $response->assertSee('Free delivery on orders over Rs. 25,000');
    $response->assertSee('Sri Lanka / LKR');
});

it('dynamically renders all 8 homepage CMS placements from GetLayoutContent', function (): void {
    (new ContentSeeder)->run();

    $response = get('/');

    $response->assertOk();
    $response->assertSee('FIND YOUR FREQUENCY');
    $response->assertSee('THE NEXT CHAPTER');
    $response->assertSee('READY FOR EVERY DAY');
    $response->assertSee('MAKE ROOM FOR GREAT SOUND');
    $response->assertSee('Islandwide delivery');
    $response->assertSee('Cash on delivery');
    $response->assertSee('Shop with confidence');
    $response->assertSee('Here to help');
    $response->assertSee('FIND YOUR THING');
    $response->assertSee('THE ONES TO KNOW');
    $response->assertSee('FRESH ON THE SHELF');
    $response->assertSee('POWER YOUR EVERYDAY');
    $response->assertSee('GOOD COMPANY');
    $response->assertSee('Apple');
    $response->assertSee('Samsung');
    $response->assertSee('Anker');
    $response->assertSee('JBL');
    $response->assertSee('Marshall');
    $response->assertSee('Sony');
});

it('renders full-card clickable links on banners and linked service items', function (): void {
    (new ContentSeeder)->run();

    $response = get('/');

    $response->assertOk();
    $response->assertSee('banner-stretched-link', false);
    $response->assertSee('<a href="/help">', false);
    $response->assertSee('<div>'."\n".'                        <svg', false);
});

it('omits placements cleanly without crashing when blocks are disabled or empty', function (): void {
    (new ContentSeeder)->run();

    foreach (ContentBlock::where('placement', Placement::HOME_HERO_CAROUSEL->value)->get() as $slide) {
        app(DisableContentBlock::class)($slide->id);
    }

    $response = get('/');
    $response->assertOk();
    $response->assertSee('hero-grid--single');

    foreach (ContentBlock::where('placement', Placement::HOME_SERVICE_STRIP->value)->get() as $serviceItem) {
        app(DisableContentBlock::class)($serviceItem->id);
    }

    $response = get('/');
    $response->assertOk();
    $response->assertDontSee('service-strip');

    $collectionBanner = ContentBlock::where('placement', Placement::HOME_COLLECTION_BANNER->value)->firstOrFail();
    app(DisableContentBlock::class)($collectionBanner->id);

    $response = get('/');
    $response->assertOk();
    $response->assertDontSee('collection-banner');

    $brandStrip = ContentBlock::where('placement', Placement::HOME_BRAND_STRIP->value)->firstOrFail();
    app(DisableContentBlock::class)($brandStrip->id);

    $response = get('/');
    $response->assertOk();
    $response->assertDontSee('brand-strip');
});

it('reflects admin mutations and omits disabled announcement bar without crashing', function (): void {
    (new ContentSeeder)->run();

    $block = ContentBlock::where('placement', Placement::ANNOUNCEMENT_BAR->value)->firstOrFail();
    app(DisableContentBlock::class)($block->id);

    $response = get('/');

    $response->assertOk();
    $response->assertDontSee('class="announcement', false);
    $response->assertDontSee('Good tech. Better everyday.');
});

it('reflects admin payload and sort_order mutations across homepage blocks immediately on GET /', function (): void {
    (new ContentSeeder)->run();

    // 1. Mutate hero promo slide headline
    $slide = ContentBlock::where('placement', Placement::HOME_HERO_CAROUSEL->value)->orderBy('sort_order')->firstOrFail();
    $slidePayload = $slide->payload;
    $slidePayload['title_line_1'] = 'Exclusive Flash Deals';
    app(UpdateContentBlock::class)($slide->id, new UpdateContentBlockData(null, null, $slide->sort_order), $slidePayload);

    $response = get('/');
    $response->assertOk();
    $response->assertSee('Exclusive Flash Deals');

    // 2. Mutate sort order of promo slide to reorder carousel slides
    $lastSlide = ContentBlock::where('placement', Placement::HOME_HERO_CAROUSEL->value)->orderByDesc('sort_order')->firstOrFail();
    $lastSlidePayload = $lastSlide->payload;
    // Move last slide to sort_order -1 so it becomes first
    app(UpdateContentBlock::class)($lastSlide->id, new UpdateContentBlockData(null, null, 0), $lastSlidePayload);
    // Move previous first slide to sort_order 10
    app(UpdateContentBlock::class)($slide->id, new UpdateContentBlockData(null, null, 10), $slidePayload);

    $response = get('/');
    $response->assertOk();
    // The previous last slide title is now first in HTML order
    $html = $response->getContent();
    expect(strpos($html, (string) $lastSlidePayload['title_line_1']))
        ->toBeLessThan(strpos($html, 'Exclusive Flash Deals'));

    // 3. Mutate collection feature banner
    $banner = ContentBlock::where('placement', Placement::HOME_COLLECTION_BANNER->value)->firstOrFail();
    $bannerPayload = $banner->payload;
    $bannerPayload['title_line_1'] = 'Supercharged Weekend';
    app(UpdateContentBlock::class)($banner->id, new UpdateContentBlockData(null, null, 0), $bannerPayload);

    $response = get('/');
    $response->assertOk();
    $response->assertSee('Supercharged Weekend');

    // 4. Mutate brand strip brands
    $brandStrip = ContentBlock::where('placement', Placement::HOME_BRAND_STRIP->value)->firstOrFail();
    $brandPayload = $brandStrip->payload;
    $brandPayload['brands'][] = ['label' => 'Nothing Tech', 'url' => '/shop?brand=Nothing'];
    app(UpdateContentBlock::class)($brandStrip->id, new UpdateContentBlockData(null, null, 0), $brandPayload);

    $response = get('/');
    $response->assertOk();
    $response->assertSee('Nothing Tech');
    $response->assertSee('/shop?brand=Nothing');
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
    $path = file_exists(base_path('docs/blueprints/phase-1/P1-S1/DEMO-TEARDOWN.md'))
        ? base_path('docs/blueprints/phase-1/P1-S1/DEMO-TEARDOWN.md')
        : base_path('docs/blueprints/P1-S1/DEMO-TEARDOWN.md');

    expect(file_exists($path))->toBeTrue();

    $content = (string) file_get_contents($path);

    expect($content)->toContain('Files to Remove')
        ->and($content)->toContain('Remove-Item');
});

it('matches the pre-refactor golden HTML snapshot on GET /', function (): void {
    (new ContentSeeder)->run();

    $response = get('/');
    $response->assertOk();

    $normalize = function (string $html): string {
        $html = (string) preg_replace('/<meta name="csrf-token" content="[^"]*">/', '<meta name="csrf-token" content="STATIC_CSRF_TOKEN">', $html);
        $html = (string) preg_replace('/<input type="hidden" name="_token" value="[^"]*"/i', '<input type="hidden" name="_token" value="STATIC_CSRF_TOKEN"', $html);
        $html = (string) preg_replace('/home-offers-01[0-9a-zA-Z]{24}/', 'home-offers-STATIC_SLIDE_ID', $html);
        $html = (string) preg_replace('/\s+>/', '>', $html);
        $html = (string) preg_replace('/>\s+</', '><', $html);

        return trim((string) preg_replace('/\s+/', ' ', $html));
    };

    $fixturePath = base_path('tests/Fixtures/storefront/home-seeded-golden.html');
    $fixture = $normalize((string) file_get_contents($fixturePath));

    expect($normalize($response->getContent()))->toBe($fixture);
});

it('renders zero preview markers on public GET /', function (): void {
    (new ContentSeeder)->run();

    $response = get('/');
    $response->assertOk();

    $response->assertDontSee('data-preview-target');
    $response->assertDontSee('preview-target-block');
    $response->assertDontSee('preview-fallback-banner');
    $response->assertDontSee('preview-unservable-placeholder');
});
