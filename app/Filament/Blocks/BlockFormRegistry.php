<?php

declare(strict_types=1);

namespace App\Filament\Blocks;

use App\Domain\Content\Exceptions\UnknownBlockTypeException;
use Filament\Schemas\Components\Component;

final class BlockFormRegistry
{
    /**
     * @var array<string, class-string<BlockForm>>
     */
    private const array FORMS = [
        'announcement_bar' => AnnouncementBarForm::class,
        'promo_slide' => PromoSlideForm::class,
        'feature_banner' => FeatureBannerForm::class,
        'service_item' => ServiceItemForm::class,
        'section_header' => SectionHeaderForm::class,
        'brand_strip' => BrandStripForm::class,
    ];

    /**
     * @return array<int, string>
     */
    public static function types(): array
    {
        return array_keys(self::FORMS);
    }

    public static function has(string $type): bool
    {
        return isset(self::FORMS[$type]);
    }

    /**
     * @return array<int, Component>
     */
    public static function get(string $type): array
    {
        if (! self::has($type)) {
            throw new UnknownBlockTypeException("Block type [{$type}] has no form registered.");
        }

        return self::FORMS[$type]::schema();
    }
}
