<?php

declare(strict_types=1);

use App\Domain\Content\Contracts\BlockRegistry;
use App\Domain\Content\Enums\Placement;
use App\Domain\Content\Enums\PlacementPage;

it('maps every placement to a placement page and provides shortLabel', function () {
    expect(PlacementPage::cases())->toHaveCount(2)
        ->and(PlacementPage::GLOBAL_LAYOUT->label())->toBe('Global Layout')
        ->and(PlacementPage::HOME->label())->toBe('Homepage');

    $expectedPages = [
        Placement::ANNOUNCEMENT_BAR->value => PlacementPage::GLOBAL_LAYOUT,
        Placement::HOME_HERO_CAROUSEL->value => PlacementPage::HOME,
        Placement::HOME_HERO_SIDE->value => PlacementPage::HOME,
        Placement::HOME_SERVICE_STRIP->value => PlacementPage::HOME,
        Placement::HOME_CATEGORIES_HEADER->value => PlacementPage::HOME,
        Placement::HOME_FEATURED_HEADER->value => PlacementPage::HOME,
        Placement::HOME_COLLECTION_BANNER->value => PlacementPage::HOME,
        Placement::HOME_NEW_ARRIVALS_HEADER->value => PlacementPage::HOME,
        Placement::HOME_BRAND_STRIP->value => PlacementPage::HOME,
    ];

    $expectedShortLabels = [
        Placement::ANNOUNCEMENT_BAR->value => 'Announcement Bar',
        Placement::HOME_HERO_CAROUSEL->value => 'Hero Carousel',
        Placement::HOME_HERO_SIDE->value => 'Hero Side Banner',
        Placement::HOME_SERVICE_STRIP->value => 'Service Strip',
        Placement::HOME_CATEGORIES_HEADER->value => 'Categories Header',
        Placement::HOME_FEATURED_HEADER->value => 'Featured Products Header',
        Placement::HOME_COLLECTION_BANNER->value => 'Collection Banner',
        Placement::HOME_NEW_ARRIVALS_HEADER->value => 'New Arrivals Header',
        Placement::HOME_BRAND_STRIP->value => 'Brand Strip',
    ];

    foreach (Placement::cases() as $placement) {
        expect($placement->page())->toBe($expectedPages[$placement->value])
            ->and($placement->shortLabel())->toBe($expectedShortLabels[$placement->value]);
    }

    $globalPlacements = Placement::forPage(PlacementPage::GLOBAL_LAYOUT);
    expect($globalPlacements)->toBe([Placement::ANNOUNCEMENT_BAR]);

    $homePlacements = Placement::forPage(PlacementPage::HOME);
    expect($homePlacements)->toHaveCount(8)
        ->and($homePlacements)->not->toContain(Placement::ANNOUNCEMENT_BAR);
});

it('declares defaultMaxLivePerPlacement on all block definitions', function () {
    /** @var BlockRegistry $registry */
    $registry = app(BlockRegistry::class);

    $announcementBar = $registry->get('announcement_bar');
    expect($announcementBar->defaultMaxLivePerPlacement())->toBe(1);

    $promoSlide = $registry->get('promo_slide');
    expect($promoSlide->defaultMaxLivePerPlacement())->toBe(5);

    $featureBanner = $registry->get('feature_banner');
    expect($featureBanner->defaultMaxLivePerPlacement())->toBe(1);

    $serviceItem = $registry->get('service_item');
    expect($serviceItem->defaultMaxLivePerPlacement())->toBe(4);

    $sectionHeader = $registry->get('section_header');
    expect($sectionHeader->defaultMaxLivePerPlacement())->toBe(1);

    $brandStrip = $registry->get('brand_strip');
    expect($brandStrip->defaultMaxLivePerPlacement())->toBe(1);
});
