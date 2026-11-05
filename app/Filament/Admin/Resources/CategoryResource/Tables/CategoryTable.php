<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\CategoryResource\Tables;

use App\Domain\Catalog\Models\Category;
use App\Filament\Admin\Resources\CategoryResource;
use App\Filament\Admin\Resources\CategoryResource\Actions\CategoryTableActions;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Support\Enums\FontFamily;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

final class CategoryTable
{
    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query
                ->scopes('withEffectiveActive')
                ->with(['parent', 'children'])
                ->orderByRaw('
                    COALESCE((SELECT p.sort_order FROM categories p WHERE p.id = categories.parent_id), categories.sort_order) ASC,
                    COALESCE(categories.parent_id, categories.id) ASC,
                    categories.parent_id NULLS FIRST,
                    categories.sort_order ASC,
                    categories.id ASC
                ')
            )
            ->paginated(false)
            ->columns([
                TextColumn::make('name')
                    ->label('Name')
                    ->formatStateUsing(fn (Category $record, string $state): string => $record->parent_id !== null ? '— '.$state : $state)
                    ->description(fn (Category $record): ?string => $record->parent?->name ? 'Parent: '.$record->parent->name : null)
                    ->searchable(),
                TextColumn::make('slug')
                    ->label('Slug')
                    ->badge()
                    ->color('gray')
                    ->fontFamily(FontFamily::Mono)
                    ->searchable(),
                TextColumn::make('sort_order')
                    ->label('Sort')
                    ->alignCenter(),
                IconColumn::make('show_in_nav')
                    ->label('Nav')
                    ->boolean()
                    ->alignCenter(),
                TextColumn::make('is_active')
                    ->label('Stored State')
                    ->badge()
                    ->formatStateUsing(fn (bool $state): string => $state ? 'Active' : 'Inactive')
                    ->color(fn (bool $state): string => $state ? 'success' : 'gray')
                    ->alignCenter(),
                TextColumn::make('is_effectively_active')
                    ->label('Storefront Visibility')
                    ->badge()
                    ->formatStateUsing(function (Category $record): string {
                        if (! $record->is_active) {
                            return 'Inactive';
                        }
                        if (! (bool) $record->is_effectively_active) {
                            return 'Hidden (Parent Inactive)';
                        }

                        return 'Active';
                    })
                    ->color(function (Category $record): string {
                        if (! $record->is_active) {
                            return 'gray';
                        }
                        if (! (bool) $record->is_effectively_active) {
                            return 'warning';
                        }

                        return 'success';
                    })
                    ->alignCenter(),
            ])
            ->recordActions([
                EditAction::make(),
                ActionGroup::make([
                    CategoryTableActions::activate(),
                    CategoryTableActions::deactivate(),
                    CategoryTableActions::move(),
                    CategoryTableActions::reorderChildren(),
                    CategoryTableActions::delete(),
                ]),
            ])
            ->recordUrl(fn (Category $record): string => CategoryResource::getUrl('edit', ['record' => $record]))
            ->bulkActions([]);
    }
}
