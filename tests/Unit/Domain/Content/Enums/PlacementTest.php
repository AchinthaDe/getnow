<?php

declare(strict_types=1);

use App\Domain\Content\Enums\Placement;

it('verifies allowed block types and max counts for all 9 placements', function () {
    expect(Placement::cases())->toHaveCount(9);

    $expectedAllowed = [
        Placement::ANNOUNCEMENT_BAR->value => ['announcement_bar'],
        Placement::HOME_HERO_CAROUSEL->value => ['promo_slide'],
        Placement::HOME_HERO_SIDE->value => ['feature_banner'],
        Placement::HOME_SERVICE_STRIP->value => ['service_item'],
        Placement::HOME_CATEGORIES_HEADER->value => ['section_header'],
        Placement::HOME_FEATURED_HEADER->value => ['section_header'],
        Placement::HOME_COLLECTION_BANNER->value => ['feature_banner'],
        Placement::HOME_NEW_ARRIVALS_HEADER->value => ['section_header'],
        Placement::HOME_BRAND_STRIP->value => ['brand_strip'],
    ];

    $expectedMaxCounts = [
        Placement::ANNOUNCEMENT_BAR->value => 1,
        Placement::HOME_HERO_CAROUSEL->value => 5,
        Placement::HOME_HERO_SIDE->value => 1,
        Placement::HOME_SERVICE_STRIP->value => 4,
        Placement::HOME_CATEGORIES_HEADER->value => 1,
        Placement::HOME_FEATURED_HEADER->value => 1,
        Placement::HOME_COLLECTION_BANNER->value => 1,
        Placement::HOME_NEW_ARRIVALS_HEADER->value => 1,
        Placement::HOME_BRAND_STRIP->value => 1,
    ];

    foreach (Placement::cases() as $placement) {
        expect($placement->allowedBlockTypes())->toBe($expectedAllowed[$placement->value])
            ->and($placement->maxCount())->toBe($expectedMaxCounts[$placement->value]);
    }
});
