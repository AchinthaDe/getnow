<?php

declare(strict_types=1);

namespace App\Filament\Blocks;

use App\Domain\Content\Blocks\BrandStrip\BrandStripData;
use App\Domain\Content\Blocks\BrandStrip\BrandStripItemData;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;

final class BrandStripForm implements BlockForm
{
    /**
     * @return array<int, Component>
     */
    public static function schema(): array
    {
        return [
            TextInput::make('eyebrow')
                ->required()
                ->trim()
                ->maxLength(BrandStripData::EYEBROW_MAX_LENGTH),

            TextInput::make('title')
                ->required()
                ->trim()
                ->maxLength(BrandStripData::TITLE_MAX_LENGTH),

            Repeater::make('brands')
                ->maxItems(BrandStripData::MAX_BRANDS)
                ->defaultItems(0)
                ->schema([
                    TextInput::make('label')
                        ->required()
                        ->trim()
                        ->maxLength(BrandStripItemData::LABEL_MAX_LENGTH),

                    TextInput::make('url')
                        ->required()
                        ->trim(),
                ]),
        ];
    }
}
