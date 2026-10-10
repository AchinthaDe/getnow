<?php

declare(strict_types=1);

namespace App\Filament\Admin\Schemas;

use App\Domain\Content\Data\StorefrontSettingsData;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;

final class StorefrontSettingsForm
{
    /**
     * @return array<int, Component>
     */
    public static function schema(): array
    {
        return [
            TextInput::make('site_name')
                ->required()
                ->trim()
                ->maxLength(StorefrontSettingsData::MAX_SITE_NAME)
                ->notRegex(StorefrontSettingsData::HTML_REGEX),

            Textarea::make('default_meta_description')
                ->required()
                ->trim()
                ->maxLength(StorefrontSettingsData::MAX_DESC)
                ->notRegex(StorefrontSettingsData::HTML_REGEX),

            TextInput::make('search_placeholder')
                ->required()
                ->trim()
                ->maxLength(StorefrontSettingsData::MAX_SEARCH)
                ->notRegex(StorefrontSettingsData::HTML_REGEX),

            TextInput::make('footer_brand_text')
                ->required()
                ->trim()
                ->maxLength(StorefrontSettingsData::MAX_FOOTER)
                ->notRegex(StorefrontSettingsData::HTML_REGEX),
        ];
    }
}
