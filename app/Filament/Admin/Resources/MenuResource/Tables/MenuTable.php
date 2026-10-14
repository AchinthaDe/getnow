<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\MenuResource\Tables;

use App\Domain\Content\Models\Menu;
use App\Filament\Admin\Resources\MenuResource;
use App\Filament\Admin\Services\AdminTimezoneResolver;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

final class MenuTable
{
    public static function table(Table $table): Table
    {
        $timezone = app(AdminTimezoneResolver::class)->resolve(config('admin.timezone', 'UTC'));

        return $table
            ->columns([
                TextColumn::make('label')
                    ->label('Menu')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('key')
                    ->label('Key')
                    ->badge()
                    ->color('gray')
                    ->sortable(),
                TextColumn::make('title')
                    ->label('Storefront Title')
                    ->placeholder('—')
                    ->sortable(),
                TextColumn::make('items_count')
                    ->counts('items')
                    ->label('Items')
                    ->sortable(),
                TextColumn::make('updated_at')
                    ->label("Updated at ({$timezone})")
                    ->dateTime()
                    ->timezone($timezone)
                    ->sortable(),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->recordUrl(fn (Menu $record): string => MenuResource::getUrl('edit', ['record' => $record]))
            ->bulkActions([]);
    }
}
