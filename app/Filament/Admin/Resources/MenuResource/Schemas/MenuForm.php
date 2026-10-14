<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\MenuResource\Schemas;

use App\Support\Rules\SafeLink;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

final class MenuForm
{
    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('title')
                    ->label('Storefront Title')
                    ->maxLength(255)
                    ->nullable(),
                Repeater::make('items')
                    ->label('Menu Items')
                    ->maxItems(10)
                    ->defaultItems(0)
                    ->schema([
                        Hidden::make('public_id'),
                        TextInput::make('label')
                            ->required()
                            ->maxLength(80),
                        TextInput::make('url')
                            ->label('URL / Path')
                            ->required()
                            ->maxLength(2048)
                            ->rule(new SafeLink),
                        Toggle::make('opens_in_new_tab')
                            ->label('Open in new tab')
                            ->default(false),
                        Toggle::make('is_enabled')
                            ->label('Enabled')
                            ->default(true),
                        Repeater::make('children')
                            ->label('Sub-items')
                            ->maxItems(10)
                            ->defaultItems(0)
                            ->schema([
                                Hidden::make('public_id'),
                                TextInput::make('label')
                                    ->required()
                                    ->maxLength(80),
                                TextInput::make('url')
                                    ->label('URL / Path')
                                    ->required()
                                    ->maxLength(2048)
                                    ->rule(new SafeLink),
                                Toggle::make('opens_in_new_tab')
                                    ->label('Open in new tab')
                                    ->default(false),
                                Toggle::make('is_enabled')
                                    ->label('Enabled')
                                    ->default(true),
                            ]),
                    ]),
            ]);
    }
}
