<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\BrandResource\Tables;

use App\Domain\Catalog\Models\Brand;
use App\Filament\Admin\Resources\BrandResource;
use App\Filament\Admin\Resources\BrandResource\Actions\BrandTableActions;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Support\Enums\FontFamily;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

final class BrandTable
{
    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('name', 'asc')
            ->columns([
                TextColumn::make('name')
                    ->label('Name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('slug')
                    ->label('Slug')
                    ->badge()
                    ->color('gray')
                    ->fontFamily(FontFamily::Mono)
                    ->searchable(),
                TextColumn::make('is_active')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (bool $state): string => $state ? 'Active' : 'Inactive')
                    ->color(fn (bool $state): string => $state ? 'success' : 'gray')
                    ->alignCenter(),
                IconColumn::make('show_in_nav')
                    ->label('In Nav')
                    ->boolean()
                    ->alignCenter(),
                TextColumn::make('updated_at')
                    ->label('Updated At')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TernaryFilter::make('is_active')
                    ->label('Status')
                    ->boolean()
                    ->trueLabel('Active brands only')
                    ->falseLabel('Inactive brands only')
                    ->placeholder('All brands'),
                TernaryFilter::make('show_in_nav')
                    ->label('Show in Navigation')
                    ->boolean()
                    ->trueLabel('In nav only')
                    ->falseLabel('Not in nav only')
                    ->placeholder('All brands'),
            ])
            ->recordActions([
                EditAction::make(),
                ActionGroup::make([
                    BrandTableActions::activate(),
                    BrandTableActions::deactivate(),
                ]),
            ])
            ->recordUrl(fn (Brand $record): string => BrandResource::getUrl('edit', ['record' => $record]))
            ->bulkActions([]);
    }
}
