<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\ContentBlockResource\Tables;

use App\Domain\Content\Actions\GetBlockServingStatus;
use App\Domain\Content\Contracts\BlockRegistry;
use App\Domain\Content\Enums\ServingStatus;
use App\Domain\Content\Models\ContentBlock;
use App\Filament\Admin\Services\AdminTimezoneResolver;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

final class ContentBlockTable
{
    public static function table(Table $table): Table
    {
        $timezone = AdminTimezoneResolver::resolve(config('admin.timezone', 'UTC'));

        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->orderBy('placement')->orderBy('sort_order'))
            ->paginated([10, 25, 50])
            ->columns([
                TextColumn::make('placement')
                    ->label('Placement')
                    ->sortable()
                    ->searchable(),

                TextColumn::make('type')
                    ->label('Type')
                    ->formatStateUsing(function ($state) {
                        $registry = app(BlockRegistry::class);
                        $definition = $registry->get($state);

                        if ($definition !== null && method_exists($definition, 'label')) {
                            return $definition->label();
                        }

                        return $state;
                    })
                    ->sortable()
                    ->searchable(),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn ($state) => match ($state?->value) {
                        'published' => 'success',
                        'draft' => 'gray',
                        'archived' => 'warning',
                        default => 'gray',
                    }),

                IconColumn::make('is_enabled')
                    ->label('Enabled')
                    ->boolean()
                    ->sortable(),

                TextColumn::make('starts_at')
                    ->label("Starts at ({$timezone})")
                    ->dateTime()
                    ->timezone($timezone)
                    ->sortable(),

                TextColumn::make('ends_at')
                    ->label("Ends at ({$timezone})")
                    ->dateTime()
                    ->timezone($timezone)
                    ->sortable(),

                TextColumn::make('serving_status')
                    ->label('Serving Status')
                    ->getStateUsing(fn (ContentBlock $record) => app(GetBlockServingStatus::class)($record)->status)
                    ->badge()
                    ->formatStateUsing(fn (?ServingStatus $state) => $state?->label())
                    ->color(fn (?ServingStatus $state) => match ($state) {
                        ServingStatus::Live => 'success',
                        ServingStatus::NotLive => 'gray',
                        ServingStatus::PayloadError => 'danger',
                        default => 'gray',
                    })
                    ->sortable(false)
                    ->searchable(false),
            ])
            ->filters([
                Filter::make('hide_archived')
                    ->label('Hide Archived')
                    ->query(fn (Builder $query) => $query->where('status', '!=', 'archived'))
                    ->default(true),

                SelectFilter::make('placement')
                    ->options([
                        'announcement_bar' => 'Announcement Bar',
                    ]),

                SelectFilter::make('status')
                    ->options([
                        'draft' => 'Draft',
                        'published' => 'Published',
                        'archived' => 'Archived',
                    ]),
            ])
            ->actions([])
            ->bulkActions([]);
    }
}
