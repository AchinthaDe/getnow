<?php

declare(strict_types=1);

namespace Tests\Feature\Database;

use App\Domain\Content\Actions\GetLayoutContent;
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
        ->and($announcementBlock->payload['text'])->toBe('Good tech. Better everyday.')
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

it('is strictly idempotent on repeat execution preserving row counts and menu item ULIDs', function (): void {
    (new ContentSeeder)->run();

    $blockCount1 = ContentBlock::count();
    $menuCount1 = Menu::count();
    $itemCount1 = MenuItem::count();
    /** @var array<int, string> $itemUlids1 */
    $itemUlids1 = MenuItem::pluck('public_id', 'id')->all();

    expect($blockCount1)->toBe(1)
        ->and($menuCount1)->toBe(5)
        ->and($itemCount1)->toBe(36);

    (new ContentSeeder)->run();

    expect(ContentBlock::count())->toBe($blockCount1)
        ->and(Menu::count())->toBe($menuCount1)
        ->and(MenuItem::count())->toBe($itemCount1)
        ->and(MenuItem::pluck('public_id', 'id')->all())->toBe($itemUlids1);
});

it('enables GetLayoutContent to return the seeded storefront layout', function (): void {
    (new ContentSeeder)->run();

    $response = app(GetLayoutContent::class)();

    expect($response->data->settings->site_name)->toBe('GetNow')
        ->and($response->data->placements['announcement_bar'])->toHaveCount(1)
        ->and($response->data->placements['announcement_bar'][0]->data['text'])->toBe('Good tech. Better everyday.')
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
