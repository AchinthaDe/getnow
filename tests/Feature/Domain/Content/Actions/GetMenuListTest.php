<?php

declare(strict_types=1);

use App\Domain\Content\Actions\GetMenuList;
use App\Domain\Content\Data\MenuListData;
use App\Domain\Content\Enums\MenuKey;
use App\Domain\Content\Models\Menu;
use App\Domain\Content\Models\MenuItem;

it('returns menu list summaries with accurate item counts', function () {
    $mainNav = Menu::where('key', MenuKey::MAIN_NAV->value)->firstOrFail();
    $mobileNav = Menu::where('key', MenuKey::MOBILE_NAV->value)->firstOrFail();

    MenuItem::factory()->count(3)->forMenu($mainNav)->create();
    MenuItem::factory()->count(2)->forMenu($mobileNav)->create();

    $list = app(GetMenuList::class)();

    expect($list)->toBeInstanceOf(MenuListData::class)
        ->and($list->menus)->toHaveCount(5);

    $mainSummary = collect($list->menus)->firstWhere('key', MenuKey::MAIN_NAV);
    $mobileSummary = collect($list->menus)->firstWhere('key', MenuKey::MOBILE_NAV);
    $footerSummary = collect($list->menus)->firstWhere('key', MenuKey::FOOTER_COLUMN_1);

    expect($mainSummary)->not->toBeNull()
        ->and($mainSummary->item_count)->toBe(3)
        ->and($mobileSummary)->not->toBeNull()
        ->and($mobileSummary->item_count)->toBe(2)
        ->and($footerSummary)->not->toBeNull()
        ->and($footerSummary->item_count)->toBe(0);
});
