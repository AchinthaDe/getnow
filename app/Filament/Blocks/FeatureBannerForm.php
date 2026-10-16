<?php

declare(strict_types=1);

namespace App\Filament\Blocks;

use App\Domain\Content\Blocks\FeatureBanner\FeatureBannerData;
use App\Filament\Blocks\Concerns\HasDemoProductImages;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;

final class FeatureBannerForm implements BlockForm
{
    use HasDemoProductImages;

    /**
     * @return array<int, Component>
     */
    public static function schema(): array
    {
        return [
            TextInput::make('eyebrow')
                ->required()
                ->trim()
                ->maxLength(FeatureBannerData::EYEBROW_MAX_LENGTH),

            TextInput::make('title_line_1')
                ->required()
                ->trim()
                ->maxLength(FeatureBannerData::TITLE_LINE_1_MAX_LENGTH),

            TextInput::make('title_line_2')
                ->nullable()
                ->trim()
                ->maxLength(FeatureBannerData::TITLE_LINE_2_MAX_LENGTH),

            Textarea::make('description')
                ->nullable()
                ->trim()
                ->maxLength(FeatureBannerData::DESCRIPTION_MAX_LENGTH),

            TextInput::make('cta_label')
                ->required()
                ->trim()
                ->maxLength(FeatureBannerData::CTA_LABEL_MAX_LENGTH),

            TextInput::make('cta_url')
                ->required()
                ->trim(),

            TextInput::make('image_url')
                ->required()
                ->trim()
                ->datalist(self::PRODUCT_IMAGES),

            TextInput::make('image_alt')
                ->required()
                ->trim()
                ->maxLength(FeatureBannerData::IMAGE_ALT_MAX_LENGTH),

            TextInput::make('badge_text')
                ->nullable()
                ->trim()
                ->maxLength(FeatureBannerData::BADGE_TEXT_MAX_LENGTH),
        ];
    }
}
