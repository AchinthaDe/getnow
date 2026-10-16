<?php

declare(strict_types=1);

namespace App\Filament\Blocks;

use App\Domain\Content\Blocks\PromoSlide\PromoSlideData;
use App\Domain\Content\Enums\PromoTheme;
use App\Filament\Blocks\Concerns\HasDemoProductImages;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;

final class PromoSlideForm implements BlockForm
{
    use HasDemoProductImages;

    /**
     * @return array<int, Component>
     */
    public static function schema(): array
    {
        return [
            Select::make('theme')
                ->options(
                    collect(PromoTheme::cases())
                        ->mapWithKeys(fn (PromoTheme $theme): array => [$theme->value => ucfirst($theme->value)])
                        ->all()
                )
                ->required()
                ->default(PromoTheme::SKY->value),

            TextInput::make('eyebrow')
                ->required()
                ->trim()
                ->maxLength(PromoSlideData::EYEBROW_MAX_LENGTH),

            TextInput::make('title_line_1')
                ->required()
                ->trim()
                ->maxLength(PromoSlideData::TITLE_LINE_1_MAX_LENGTH),

            TextInput::make('title_line_2')
                ->nullable()
                ->trim()
                ->maxLength(PromoSlideData::TITLE_LINE_2_MAX_LENGTH),

            Textarea::make('description')
                ->required()
                ->trim()
                ->maxLength(PromoSlideData::DESCRIPTION_MAX_LENGTH),

            TextInput::make('cta_label')
                ->required()
                ->trim()
                ->maxLength(PromoSlideData::CTA_LABEL_MAX_LENGTH),

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
                ->maxLength(PromoSlideData::IMAGE_ALT_MAX_LENGTH),

            TextInput::make('label')
                ->required()
                ->trim()
                ->maxLength(PromoSlideData::LABEL_MAX_LENGTH)
                ->helperText('Accessible slide and pagination dot label.'),
        ];
    }
}
