<?php

declare(strict_types=1);

namespace Tests\Feature\Database;

use App\Domain\Content\Actions\ArchiveContentBlock;
use App\Domain\Content\Actions\DisableContentBlock;
use App\Domain\Content\Actions\GetLayoutContent;
use App\Domain\Content\Actions\UpdateContentBlock;
use App\Domain\Content\Data\UpdateContentBlockData;
use App\Domain\Content\Enums\MenuKey;
use App\Domain\Content\Enums\Placement;
use App\Domain\Content\Enums\PublishStatus;
use App\Domain\Content\Models\ContentBlock;
use App\Domain\Content\Models\Menu;
use App\Domain\Content\Models\MenuItem;
use App\Domain\Content\Settings\StorefrontSettings;
use App\Support\Rules\SafeLink;
use Database\Seeders\ContentSeeder;
use Illuminate\Support\Facades\Validator;

use function Pest\Laravel\getJson;

it('seeds storefront settings, announcement bar, and navigation menus matching storefront values', function (): void {
    (new ContentSeeder)->run();

    $settings = app(StorefrontSettings::class);
    expect($settings->site_name)->toBe('GetNow')
        ->and($settings->default_meta_description)->toContain('Discover headphones, phones')
        ->and($settings->search_placeholder)->toBe('What are you looking for?')
        ->and($settings->footer_brand_text)->toContain('Thoughtfully chosen tech.');

    $announcementBlock = ContentBlock::where('placement', Placement::ANNOUNCEMENT_BAR->value)
        ->where('type', 'announcement_bar')
        ->first();

    expect($announcementBlock)->not->toBeNull()
        ->and(ContentBlock::where('placement', Placement::ANNOUNCEMENT_BAR->value)->count())->toBe(1)
        ->and($announcementBlock->status)->toBe(PublishStatus::PUBLISHED)
        ->and($announcementBlock->is_enabled)->toBeTrue()
        ->and($announcementBlock->schema_version)->toBe(2)
        ->and($announcementBlock->sort_order)->toBe(0)
        ->and($announcementBlock->payload['text'])->toBe('Good tech. Better everyday.')
        ->and($announcementBlock->payload['secondary_text'])->toBe('Free delivery on orders over Rs. 25,000')
        ->and($announcementBlock->payload['locale_text'])->toBe('Sri Lanka / LKR')
        ->and($announcementBlock->payload['link_url'])->toBeNull()
        ->and($announcementBlock->payload['tone'])->toBe('info');

    $menus = Menu::all()->keyBy('key');
    expect($menus)->toHaveCount(5)
        ->and($menus->has(MenuKey::MAIN_NAV->value))->toBeTrue()
        ->and($menus->has(MenuKey::MOBILE_NAV->value))->toBeTrue()
        ->and($menus->has(MenuKey::FOOTER_COLUMN_1->value))->toBeTrue()
        ->and($menus->has(MenuKey::FOOTER_COLUMN_2->value))->toBeTrue()
        ->and($menus->has(MenuKey::FOOTER_COLUMN_3->value))->toBeTrue()
        ->and($menus->get(MenuKey::MAIN_NAV->value)->title)->toBeNull()
        ->and($menus->get(MenuKey::MOBILE_NAV->value)->title)->toBeNull()
        ->and($menus->get(MenuKey::FOOTER_COLUMN_1->value)->title)->toBe('Find your next favourite')
        ->and($menus->get(MenuKey::FOOTER_COLUMN_2->value)->title)->toBe('A little help')
        ->and($menus->get(MenuKey::FOOTER_COLUMN_3->value)->title)->toBe("Small upgrades.\nBig possibilities.");
});

it('seeds all 9 homepage placements matching exact storefront demo content', function (): void {
    (new ContentSeeder)->run();

    expect(ContentBlock::count())->toBe(14);

    $announcement = ContentBlock::where('placement', Placement::ANNOUNCEMENT_BAR->value)->first();
    expect($announcement)->not->toBeNull()
        ->and($announcement->schema_version)->toBe(2)
        ->and($announcement->payload['secondary_text'])->toBe('Free delivery on orders over Rs. 25,000')
        ->and($announcement->payload['locale_text'])->toBe('Sri Lanka / LKR');

    $carouselSlides = ContentBlock::where('placement', Placement::HOME_HERO_CAROUSEL->value)
        ->orderBy('sort_order')
        ->get();
    expect($carouselSlides)->toHaveCount(3)
        ->and($carouselSlides->pluck('sort_order')->all())->toBe([0, 1, 2])
        ->and($carouselSlides->pluck('type')->unique()->all())->toBe(['promo_slide']);

    $serviceItems = ContentBlock::where('placement', Placement::HOME_SERVICE_STRIP->value)
        ->orderBy('sort_order')
        ->get();
    expect($serviceItems)->toHaveCount(4)
        ->and($serviceItems->pluck('sort_order')->all())->toBe([0, 1, 2, 3])
        ->and($serviceItems->pluck('type')->unique()->all())->toBe(['service_item']);

    $heroSide = ContentBlock::where('placement', Placement::HOME_HERO_SIDE->value)->first();
    expect($heroSide)->not->toBeNull()
        ->and($heroSide->type)->toBe('feature_banner')
        ->and($heroSide->sort_order)->toBe(0);

    $collectionBanner = ContentBlock::where('placement', Placement::HOME_COLLECTION_BANNER->value)->first();
    expect($collectionBanner)->not->toBeNull()
        ->and($collectionBanner->type)->toBe('feature_banner')
        ->and($collectionBanner->sort_order)->toBe(0);

    $catHeader = ContentBlock::where('placement', Placement::HOME_CATEGORIES_HEADER->value)->first();
    expect($catHeader)->not->toBeNull()
        ->and($catHeader->type)->toBe('section_header');

    $featHeader = ContentBlock::where('placement', Placement::HOME_FEATURED_HEADER->value)->first();
    expect($featHeader)->not->toBeNull()
        ->and($featHeader->type)->toBe('section_header');

    $newHeader = ContentBlock::where('placement', Placement::HOME_NEW_ARRIVALS_HEADER->value)->first();
    expect($newHeader)->not->toBeNull()
        ->and($newHeader->type)->toBe('section_header');

    $brandStrip = ContentBlock::where('placement', Placement::HOME_BRAND_STRIP->value)->first();
    expect($brandStrip)->not->toBeNull()
        ->and($brandStrip->type)->toBe('brand_strip')
        ->and($brandStrip->payload['brands'])->toHaveCount(6);
});

it('is strictly idempotent on repeat execution preserving row counts across multi-block placements', function (): void {
    (new ContentSeeder)->run();

    $blockCount1 = ContentBlock::count();
    $menuCount1 = Menu::count();
    $itemCount1 = MenuItem::count();
    /** @var array<int, string> $blockUlids1 */
    $blockUlids1 = ContentBlock::orderBy('id')->pluck('public_id')->all();
    /** @var array<int, string> $itemUlids1 */
    $itemUlids1 = MenuItem::pluck('public_id', 'id')->all();

    expect($blockCount1)->toBe(14)
        ->and($menuCount1)->toBe(5)
        ->and($itemCount1)->toBe(36);

    (new ContentSeeder)->run();

    expect(ContentBlock::count())->toBe(14)
        ->and(Menu::count())->toBe($menuCount1)
        ->and(MenuItem::count())->toBe($itemCount1)
        ->and(ContentBlock::orderBy('id')->pluck('public_id')->all())->toBe($blockUlids1)
        ->and(MenuItem::pluck('public_id', 'id')->all())->toBe($itemUlids1);
});

it('resets single-block placements to published and enabled seeded state while skipping populated multi-block placements on re-run', function (): void {
    (new ContentSeeder)->run();

    $carouselSlide1 = ContentBlock::where('placement', Placement::HOME_HERO_CAROUSEL->value)
        ->where('sort_order', 1)
        ->firstOrFail();

    $slidePayload = $carouselSlide1->payload;
    $slidePayload['label'] = 'Edited Label';
    app(UpdateContentBlock::class)(
        $carouselSlide1->id,
        new UpdateContentBlockData(starts_at: null, ends_at: null, sort_order: 99),
        $slidePayload,
    );

    $brandStripBlock = ContentBlock::where('placement', Placement::HOME_BRAND_STRIP->value)->firstOrFail();
    app(DisableContentBlock::class)($brandStripBlock->id);

    $announcementBlock = ContentBlock::where('placement', Placement::ANNOUNCEMENT_BAR->value)->firstOrFail();
    $announcementBlock->status = PublishStatus::DRAFT;
    $announcementBlock->sort_order = 12;
    $announcementBlock->save();

    (new ContentSeeder)->run();

    expect(ContentBlock::count())->toBe(14);

    $freshBrandStrip = $brandStripBlock->fresh();
    expect($freshBrandStrip->is_enabled)->toBeTrue()
        ->and($freshBrandStrip->status)->toBe(PublishStatus::PUBLISHED)
        ->and($freshBrandStrip->sort_order)->toBe(0);

    $freshAnnouncement = $announcementBlock->fresh();
    expect($freshAnnouncement->status)->toBe(PublishStatus::PUBLISHED)
        ->and($freshAnnouncement->sort_order)->toBe(0);

    $freshSlide1 = $carouselSlide1->fresh();
    expect($freshSlide1->payload['label'])->toBe('Edited Label')
        ->and($freshSlide1->sort_order)->toBe(99);
});

it('restores a disabled home_brand_strip to its intended seeded state upon seeder rerun', function (): void {
    (new ContentSeeder)->run();

    $brandStripBlock = ContentBlock::where('placement', Placement::HOME_BRAND_STRIP->value)->firstOrFail();
    app(DisableContentBlock::class)($brandStripBlock->id);

    expect($brandStripBlock->fresh()->is_enabled)->toBeFalse();

    (new ContentSeeder)->run();

    $fresh = $brandStripBlock->fresh();
    expect($fresh->is_enabled)->toBeTrue()
        ->and($fresh->status)->toBe(PublishStatus::PUBLISHED)
        ->and($fresh->sort_order)->toBe(0);
});

it('does not re-seed multi-block placement when an individual slide is archived', function (): void {
    (new ContentSeeder)->run();

    $carouselSlide1 = ContentBlock::where('placement', Placement::HOME_HERO_CAROUSEL->value)
        ->where('sort_order', 1)
        ->firstOrFail();

    app(ArchiveContentBlock::class)($carouselSlide1->id);

    (new ContentSeeder)->run();

    expect(ContentBlock::count())->toBe(14);
});

it('upgrades an existing v1 announcement bar block to v2 in-place during seed run', function (): void {
    ContentBlock::factory()->create([
        'placement' => Placement::ANNOUNCEMENT_BAR->value,
        'type' => 'announcement_bar',
        'schema_version' => 1,
        'sort_order' => 0,
        'status' => PublishStatus::PUBLISHED,
        'is_enabled' => true,
        'payload' => [
            'text' => 'Good tech. Better everyday.',
            'tone' => 'info',
            'link_url' => null,
        ],
    ]);

    (new ContentSeeder)->run();

    expect(ContentBlock::count())->toBe(14);

    $announcement = ContentBlock::where('placement', Placement::ANNOUNCEMENT_BAR->value)->firstOrFail();
    expect($announcement->schema_version)->toBe(2)
        ->and($announcement->payload['text'])->toBe('Good tech. Better everyday.')
        ->and($announcement->payload['secondary_text'])->toBe('Free delivery on orders over Rs. 25,000')
        ->and($announcement->payload['locale_text'])->toBe('Sri Lanka / LKR')
        ->and($announcement->payload['tone'])->toBe('info');
});

it('enables GetLayoutContent to return the seeded storefront layout', function (): void {
    (new ContentSeeder)->run();

    $response = app(GetLayoutContent::class)();

    expect($response->data->settings->site_name)->toBe('GetNow')
        ->and($response->data->placements['announcement_bar'])->toHaveCount(1)
        ->and($response->data->placements['announcement_bar'][0]->data['text'])->toBe('Good tech. Better everyday.')
        ->and($response->data->placements['home_hero_carousel'])->toHaveCount(3)
        ->and($response->data->placements['home_service_strip'])->toHaveCount(4)
        ->and($response->data->placements['home_hero_side'])->toHaveCount(1)
        ->and($response->data->placements['home_collection_banner'])->toHaveCount(1)
        ->and($response->data->placements['home_brand_strip'])->toHaveCount(1)
        ->and($response->data->menus['main_nav']->items)->toHaveCount(6)
        ->and($response->data->menus['main_nav']->items[0]->children)->toHaveCount(8)
        ->and($response->data->menus['footer_column_1']->title)->toBe('Find your next favourite');
});

it('enables GET /api/v1/content/layout to return the seeded storefront layout', function (): void {
    (new ContentSeeder)->run();

    $response = getJson('/api/v1/content/layout');

    $response->assertOk();
    $response->assertJsonPath('data.settings.site_name', 'GetNow');
    $response->assertJsonPath('data.placements.announcement_bar.0.data.text', 'Good tech. Better everyday.');
    $response->assertJsonPath('data.placements.home_hero_carousel.0.data.theme', 'sky');
    $response->assertJsonPath('data.menus.main_nav.items.0.label', 'Browse categories');
});

it('verifies all seeded menu item urls satisfy SafeLink validation', function (): void {
    (new ContentSeeder)->run();

    $rule = new SafeLink;
    $items = MenuItem::all();

    expect($items)->not->toBeEmpty();

    foreach ($items as $item) {
        $validator = Validator::make(['url' => $item->url], ['url' => $rule]);
        expect($validator->passes())->toBeTrue("Menu item '{$item->label}' has invalid URL: {$item->url}");
    }
});

it('aborts when executed outside local or testing environments', function (): void {
    app()['env'] = 'production';

    expect(fn () => (new ContentSeeder)->run())
        ->toThrow(\RuntimeException::class, 'ContentSeeder may only be executed in local or testing environments.');
});

it('seeds a new published block when existing block in placement is archived', function (): void {
    (new ContentSeeder)->run();

    $block = ContentBlock::where('placement', Placement::ANNOUNCEMENT_BAR->value)->firstOrFail();
    app(ArchiveContentBlock::class)($block->id);

    expect(ContentBlock::where('placement', Placement::ANNOUNCEMENT_BAR->value)->count())->toBe(1)
        ->and($block->fresh()->status)->toBe(PublishStatus::ARCHIVED);

    (new ContentSeeder)->run();

    expect(ContentBlock::where('placement', Placement::ANNOUNCEMENT_BAR->value)->count())->toBe(2)
        ->and(ContentBlock::where('placement', Placement::ANNOUNCEMENT_BAR->value)->where('status', PublishStatus::PUBLISHED->value)->count())->toBe(1)
        ->and(ContentBlock::where('placement', Placement::ANNOUNCEMENT_BAR->value)->where('status', PublishStatus::ARCHIVED->value)->count())->toBe(1);
});
