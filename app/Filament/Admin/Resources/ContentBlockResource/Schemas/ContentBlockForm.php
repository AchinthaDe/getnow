<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\ContentBlockResource\Schemas;

use App\Domain\Content\Enums\Placement;
use App\Domain\Content\Enums\PublishStatus;
use App\Domain\Content\Models\ContentBlock;
use App\Filament\Admin\Services\AdminTimezoneResolver;
use App\Filament\Blocks\BlockFormRegistry;
use Filament\Forms\Components\DateTimePicker;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Callout;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

final class ContentBlockForm
{
    public static function form(Schema $schema): Schema
    {
        $timezone = app(AdminTimezoneResolver::class)->resolve(config('admin.timezone', 'UTC'));

        return $schema->components([
            TextEntry::make('placement_display')
                ->label('Placement')
                ->state(function (?ContentBlock $record): string {
                    if ($record === null) {
                        return '';
                    }

                    return $record->placement instanceof Placement
                        ? $record->placement->value
                        : (string) ($record->getRawOriginal('placement') ?? '');
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
                ->visible(fn (?ContentBlock $record, string $operation): bool => $operation === 'edit' && $record?->status === PublishStatus::PUBLISHED),

            Group::make()
                ->statePath('payload')
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
        ]);
    }
}
