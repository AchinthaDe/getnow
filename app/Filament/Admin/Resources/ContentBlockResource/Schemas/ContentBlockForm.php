<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\ContentBlockResource\Schemas;

use App\Domain\Content\Actions\CountLiveBlocksForPlacement;
use App\Domain\Content\Contracts\BlockRegistry;
use App\Domain\Content\Enums\Placement;
use App\Domain\Content\Enums\PlacementPage;
use App\Domain\Content\Enums\PublishStatus;
use App\Domain\Content\Models\ContentBlock;
use App\Filament\Admin\Services\AdminTimezoneResolver;
use App\Filament\Blocks\BlockFormRegistry;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Callout;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

final class ContentBlockForm
{
    public static function form(Schema $schema): Schema
    {
        $timezone = app(AdminTimezoneResolver::class)->resolve(config('admin.timezone', 'UTC'));

        return $schema->components([
            Select::make('page')
                ->label('Page')
                ->options(collect(PlacementPage::cases())->mapWithKeys(fn (PlacementPage $p) => [$p->value => $p->label()])->all())
                ->live()
                ->dehydrated(false)
                ->afterStateUpdated(function (Set $set): void {
                    $set('placement', null);
                    $set('type', null);
                    $set('payload', []);
                })
                ->visibleOn('create'),

            Select::make('placement')
                ->label('Placement')
                ->disabled(fn (Get $get): bool => blank($get('page')))
                ->options(function (Get $get): array {
                    $pageVal = $get('page');
                    if (blank($pageVal)) {
                        return [];
                    }

                    $page = PlacementPage::tryFrom((string) $pageVal);
                    if ($page === null) {
                        return [];
                    }

                    return collect(Placement::forPage($page))->mapWithKeys(fn (Placement $p) => [$p->value => $p->shortLabel()])->all();
                })
                ->helperText(function (Get $get): ?string {
                    $placementValue = $get('placement');
                    if (blank($placementValue)) {
                        return null;
                    }
                    $placement = Placement::tryFrom((string) $placementValue);
                    if ($placement === null) {
                        return null;
                    }
                    $liveCount = app(CountLiveBlocksForPlacement::class)($placement);
                    $max = $placement->maxCount();

                    return "Live capacity: {$liveCount} / {$max} active block(s).";
                })
                ->required()
                ->live()
                ->afterStateUpdated(function (Get $get, Set $set, ?string $state): void {
                    if (blank($state)) {
                        $set('type', null);
                        $set('payload', []);

                        return;
                    }

                    $placement = Placement::tryFrom($state);
                    if ($placement !== null) {
                        $allowedTypes = $placement->allowedBlockTypes();
                        if (count($allowedTypes) === 1) {
                            $set('type', $allowedTypes[0]);
                        } else {
                            $set('type', null);
                        }
                    }
                    $set('payload', []);
                })
                ->visibleOn('create'),

            Select::make('type')
                ->label('Type')
                ->options(function (Get $get): array {
                    $placementVal = $get('placement');
                    if (blank($placementVal)) {
                        return [];
                    }

                    $placement = Placement::tryFrom((string) $placementVal);
                    if ($placement === null) {
                        return [];
                    }

                    $blockRegistry = app(BlockRegistry::class);
                    $allowed = array_intersect(
                        $placement->allowedBlockTypes(),
                        array_keys($blockRegistry->all()),
                        BlockFormRegistry::types(),
                    );

                    return collect($allowed)->mapWithKeys(fn (string $type) => [$type => Str::headline($type)])->all();
                })
                ->required()
                ->live()
                ->afterStateUpdated(function (Set $set): void {
                    $set('payload', []);
                })
                ->visibleOn('create'),

            TextEntry::make('page_display')
                ->label('Page')
                ->state(fn (?ContentBlock $record): string => $record?->placement?->page()->label() ?? '—')
                ->visibleOn('edit'),

            TextEntry::make('placement_display')
                ->label('Placement')
                ->state(function (?ContentBlock $record): string {
                    if ($record === null) {
                        return '';
                    }

                    return $record->placement instanceof Placement
                        ? $record->placement->label()
                        : (string) ($record->getRawOriginal('placement') ?? '');
                })
                ->helperText(function (?ContentBlock $record): string {
                    if ($record === null || $record->placement === null) {
                        return '';
                    }
                    $liveCount = ContentBlock::visible()
                        ->where('placement', $record->placement->value)
                        ->count();
                    $maxCount = $record->placement->maxCount();

                    return "{$liveCount} / {$maxCount} live in this placement";
                })
                ->visibleOn('edit'),

            TextEntry::make('type_display')
                ->label('Type')
                ->state(function (?ContentBlock $record): string {
                    if ($record === null) {
                        return '';
                    }

                    return Str::headline($record->type);
                })
                ->visibleOn('edit'),

            Callout::make('Published block')
                ->description('Changes made to this block will go live immediately on the storefront.')
                ->warning()
                ->columnSpanFull()
                ->visible(fn (?ContentBlock $record, string $operation): bool => $operation === 'edit' && $record?->status === PublishStatus::PUBLISHED),

            Group::make()
                ->statePath('payload')
                ->columnSpanFull()
                ->schema(function (Get $get, ?ContentBlock $record, string $operation): array {
                    $type = $operation === 'create' ? $get('type') : $record?->type;

                    if (blank($type) || ! BlockFormRegistry::has((string) $type)) {
                        return [];
                    }

                    return BlockFormRegistry::get((string) $type);
                }),

            DateTimePicker::make('starts_at')
                ->label("Starts at ({$timezone})")
                ->timezone($timezone)
                ->nullable(),

            DateTimePicker::make('ends_at')
                ->label("Ends at ({$timezone})")
                ->timezone($timezone)
                ->nullable(),

            TextInput::make('sort_order')
                ->numeric()
                ->default(0)
                ->minValue(ContentBlock::MIN_SORT_ORDER)
                ->maxValue(ContentBlock::MAX_SORT_ORDER)
                ->label('Sort Order')
                ->helperText('Determines display order within the placement (lower numbers appear first).'),
        ]);
    }
}
