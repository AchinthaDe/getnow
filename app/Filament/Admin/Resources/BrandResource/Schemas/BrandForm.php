<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\BrandResource\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

final class BrandForm
{
    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Brand Information')
                    ->schema([
                        TextInput::make('name')
                            ->label('Name')
                            ->required()
                            ->minLength(1)
                            ->maxLength(80)
                            ->autofocus(),
                        TextInput::make('slug')
                            ->label('Slug')
                            ->disabled()
                            ->dehydrated(false)
                            ->visible(fn (string $operation): bool => $operation === 'edit')
                            ->helperText('Brand slug is generated automatically and is immutable (Rule CT3).'),
                        Toggle::make('show_in_nav')
                            ->label('Show in Navigation')
                            ->default(false)
                            ->helperText('Controls whether this brand is surfaced in navigation menus and brand source queries.'),
                    ]),
            ]);
    }
}
