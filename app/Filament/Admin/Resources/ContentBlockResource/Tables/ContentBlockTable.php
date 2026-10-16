<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\ContentBlockResource\Tables;

use App\Domain\Content\Actions\GetBlockServingStatus;
use App\Domain\Content\Contracts\BlockRegistry;
use App\Domain\Content\Enums\Placement;
use App\Domain\Content\Enums\PlacementPage;
use App\Domain\Content\Enums\PublishStatus;
use App\Domain\Content\Enums\ServingStatus;
use App\Domain\Content\Models\ContentBlock;
use App\Filament\Admin\Resources\ContentBlockResource;
use App\Filament\Admin\Resources\ContentBlockResource\Actions\ContentBlockTransitionActions;
use App\Filament\Admin\Services\AdminTimezoneResolver;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
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
                TextColumn::make('page')
                    ->label('Page')
                    ->badge()
                    ->state(fn (ContentBlock $record): string => $record->placement?->page()->label() ?? '—')
                    ->sortable(false),

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

                SelectFilter::make('page')
                    ->label('Page')
                    ->options(collect(PlacementPage::cases())->mapWithKeys(fn (PlacementPage $p) => [$p->value => $p->label()])->all())
                    ->query(function (Builder $query, array $data): Builder {
                        $state = $data['value'] ?? null;
                        if (blank($state)) {
                            return $query;
                        }
                        $page = PlacementPage::tryFrom((string) $state);
                        if ($page === null) {
                            return $query;
                        }

                        return $query->whereIn('placement', collect(Placement::forPage($page))->pluck('value'));
                    }),

                SelectFilter::make('placement')
                    ->options(collect(Placement::cases())->mapWithKeys(fn (Placement $p) => [$p->value => $p->label()])->all()),

                SelectFilter::make('type')
                    ->options(collect(app(BlockRegistry::class)->all())->mapWithKeys(fn ($def, string $key) => [$key => Str::headline($key)])->all()),

                SelectFilter::make('status')
                    ->options([
                        'draft' => 'Draft',
                        'published' => 'Published',
                        'archived' => 'Archived',
                    ]),

                TernaryFilter::make('is_enabled')
                    ->label('Enabled'),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                ActionGroup::make([
                    ContentBlockTransitionActions::publish(),
                    ContentBlockTransitionActions::enable(),
                    ContentBlockTransitionActions::disable(),
                    ContentBlockTransitionActions::archive(),
                ]),
            ])
            ->recordUrl(fn (ContentBlock $record): string => ContentBlockResource::getUrl('view', ['record' => $record]))
            ->bulkActions([]);
    }
}
