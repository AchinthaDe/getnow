<?php

declare(strict_types=1);

namespace App\Filament\Blocks;

use App\Domain\Content\Blocks\AnnouncementBar\AnnouncementBarData;
use App\Domain\Content\Enums\Tone;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;

final class AnnouncementBarForm implements BlockForm
{
    /**
     * @return array<int, Component>
     */
    public static function schema(): array
    {
        return [
            TextInput::make('text')
                ->required()
                ->trim()
                ->maxLength(AnnouncementBarData::TEXT_MAX_LENGTH),

            TextInput::make('link_url')
                ->nullable()
                ->trim(),

            Select::make('tone')
                ->options(
                    collect(Tone::cases())
                        ->mapWithKeys(fn (Tone $tone): array => [$tone->value => ucfirst($tone->value)])
                        ->all()
                )
                ->required()
                ->default(Tone::INFO->value),
        ];
    }
}
