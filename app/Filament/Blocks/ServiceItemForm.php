<?php

declare(strict_types=1);

namespace App\Filament\Blocks;

use App\Domain\Content\Blocks\ServiceItem\ServiceItemData;
use App\Domain\Content\Enums\ServiceIcon;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;

final class ServiceItemForm implements BlockForm
{
    /**
     * @return array<int, Component>
     */
    public static function schema(): array
    {
        return [
            Select::make('icon')
                ->options(
                    collect(ServiceIcon::cases())
                        ->mapWithKeys(fn (ServiceIcon $icon): array => [$icon->value => ucfirst($icon->value)])
                        ->all()
                )
                ->required(),

            TextInput::make('title')
                ->required()
                ->trim()
                ->maxLength(ServiceItemData::TITLE_MAX_LENGTH),

            TextInput::make('subtitle')
                ->required()
                ->trim()
                ->maxLength(ServiceItemData::SUBTITLE_MAX_LENGTH),

            TextInput::make('link_url')
                ->nullable()
                ->trim(),
        ];
    }
}
