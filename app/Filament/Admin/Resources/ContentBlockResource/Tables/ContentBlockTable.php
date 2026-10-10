<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\ContentBlockResource\Tables;

use App\Domain\Content\Actions\GetBlockServingStatus;
use App\Domain\Content\Contracts\BlockRegistry;
use App\Domain\Content\Enums\PublishStatus;
use App\Domain\Content\Enums\ServingStatus;
use App\Domain\Content\Models\ContentBlock;
use App\Filament\Admin\Services\AdminTimezoneResolver;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Component;

final class ContentBlockTable
{
    public static function servingStatusColor(?ServingStatus $state): string
    {
        if ($state === null) {
            return 'gray';
        }

        return match ($state) {
            ServingStatus::Live => 'success',
            ServingStatus::NotLive => 'gray',
            ServingStatus::PayloadError => 'danger',
        };
    }

    public static function publishStatusColor(?PublishStatus $state): string
    {
        if ($state === null) {
            return 'gray';
        }

        return match ($state) {
            PublishStatus::PUBLISHED => 'success',
            PublishStatus::DRAFT => 'gray',
            PublishStatus::ARCHIVED => 'warning',
        };
    }

    public static function table(Table $table): Table
    {
        $timezone = app(AdminTimezoneResolver::class)->resolve(config('admin.timezone', 'UTC'));

        return $table
            ->defaultSort(fn (Builder $query) => $query->orderBy('placement')->orderBy('sort_order'))
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
                    ->color(fn (?PublishStatus $state) => self::publishStatusColor($state))
                    ->sortable(),

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
                    ->color(fn (?ServingStatus $state) => self::servingStatusColor($state))
                    ->sortable(false)
                    ->searchable(false),
            ])
            ->filters([
                Filter::make('hide_archived')
                    ->label('Hide Archived')
                    ->query(function (Builder $query, Component $livewire) {
                        $status = data_get($livewire, 'tableFilters.status.value');
                        if ($status !== 'archived') {
                            $query->where('status', '!=', 'archived');
                        }
                    })
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

                TernaryFilter::make('is_enabled')
                    ->label('Enabled'),
            ])
            ->actions([])
            ->bulkActions([]);
    }
}
