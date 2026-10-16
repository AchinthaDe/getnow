<?php

declare(strict_types=1);

namespace App\Filament\Blocks;

use App\Domain\Content\Blocks\SectionHeader\SectionHeaderData;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;

final class SectionHeaderForm implements BlockForm
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
                ->maxLength(SectionHeaderData::EYEBROW_MAX_LENGTH),

            TextInput::make('title')
                ->required()
                ->trim()
                ->maxLength(SectionHeaderData::TITLE_MAX_LENGTH),

            TextInput::make('cta_label')
                ->nullable()
                ->trim()
                ->maxLength(SectionHeaderData::CTA_LABEL_MAX_LENGTH),

            TextInput::make('cta_url')
                ->nullable()
                ->trim(),
        ];
    }
}
