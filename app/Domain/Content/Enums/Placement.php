<?php

declare(strict_types=1);

namespace App\Domain\Content\Enums;

use Illuminate\Support\Str;

enum Placement: string
{
    case ANNOUNCEMENT_BAR = 'announcement_bar';
    case HOME_HERO_CAROUSEL = 'home_hero_carousel';
    case HOME_HERO_SIDE = 'home_hero_side';
    case HOME_SERVICE_STRIP = 'home_service_strip';
    case HOME_CATEGORIES_HEADER = 'home_categories_header';
    case HOME_FEATURED_HEADER = 'home_featured_header';
    case HOME_COLLECTION_BANNER = 'home_collection_banner';
    case HOME_NEW_ARRIVALS_HEADER = 'home_new_arrivals_header';
    case HOME_BRAND_STRIP = 'home_brand_strip';

    /**
     * @return array<int, string>
     */
    public function allowedBlockTypes(): array
    {
        return match ($this) {
            self::ANNOUNCEMENT_BAR => ['announcement_bar'],
            self::HOME_HERO_CAROUSEL => ['promo_slide'],
            self::HOME_HERO_SIDE => ['feature_banner'],
            self::HOME_SERVICE_STRIP => ['service_item'],
            self::HOME_CATEGORIES_HEADER => ['section_header'],
            self::HOME_FEATURED_HEADER => ['section_header'],
            self::HOME_COLLECTION_BANNER => ['feature_banner'],
            self::HOME_NEW_ARRIVALS_HEADER => ['section_header'],
            self::HOME_BRAND_STRIP => ['brand_strip'],
        };
    }

    public function maxCount(): int
    {
        return match ($this) {
            self::HOME_HERO_CAROUSEL => 5,
            self::HOME_SERVICE_STRIP => 4,
            self::ANNOUNCEMENT_BAR,
            self::HOME_HERO_SIDE,
            self::HOME_CATEGORIES_HEADER,
            self::HOME_FEATURED_HEADER,
            self::HOME_COLLECTION_BANNER,
            self::HOME_NEW_ARRIVALS_HEADER,
            self::HOME_BRAND_STRIP => 1,
        };
    }

    public function label(): string
    {
        return Str::headline($this->value);
    }
}
