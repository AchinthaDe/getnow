<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\ContentBlockResource\Schemas;

use App\Domain\Content\Actions\GetBlockServingStatus;
use App\Domain\Content\Enums\Placement;
use App\Domain\Content\Enums\ServingHealthOutcome;
use App\Domain\Content\Models\ContentBlock;
use App\Filament\Admin\Resources\ContentBlockResource\Tables\ContentBlockTable;
use App\Filament\Admin\Services\AdminTimezoneResolver;
use App\Models\User;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Component;
use Illuminate\Support\Str;

final class ContentBlockMetadataSchema
{
    public static function humanServingReason(?ServingHealthOutcome $outcome): string
    {
        if ($outcome === null) {
            return '';
        }

        return match ($outcome) {
            ServingHealthOutcome::Servable => 'Servable',
            ServingHealthOutcome::UnknownType => 'Unknown type',
            ServingHealthOutcome::UnsupportedVersion => 'Unsupported version',
            ServingHealthOutcome::InvalidAfterUpgrade => 'Invalid payload after upgrade',
            ServingHealthOutcome::UpgradeFailed => 'Upgrade failed',
        };
    }

    /**
     * @return array<int, Component>
     */
    public static function schema(): array
    {
        $timezone = app(AdminTimezoneResolver::class)->resolve(config('admin.timezone', 'UTC'));

        return [
            TextEntry::make('public_id')
                ->label('Public ID')
                ->state(fn (?ContentBlock $record): string => $record !== null ? $record->public_id : ''),

            TextEntry::make('placement')
                ->label('Placement')
                ->state(function (?ContentBlock $record): string {
                    if ($record === null) {
                        return '';
                    }

                    return $record->placement instanceof Placement
                        ? Str::headline($record->placement->value)
                        : (string) ($record->getRawOriginal('placement') ?? '');
                }),

            TextEntry::make('type')
                ->label('Type')
                ->state(fn (?ContentBlock $record): string => $record !== null ? Str::headline($record->type) : ''),

            TextEntry::make('status')
                ->label('Status')
                ->badge()
                ->color(fn (?ContentBlock $record): string => ContentBlockTable::publishStatusColor($record?->status))
                ->state(fn (?ContentBlock $record): string => $record?->status ? ucfirst($record->status->value) : ''),

            IconEntry::make('is_enabled')
                ->label('Enabled')
                ->boolean()
                ->state(fn (?ContentBlock $record): bool => (bool) $record?->is_enabled),

            TextEntry::make('serving_status')
                ->label('Serving Status')
                ->badge()
                ->color(function (?ContentBlock $record): string {
                    if ($record === null) {
                        return 'gray';
                    }

                    return ContentBlockTable::servingStatusColor(app(GetBlockServingStatus::class)($record)->status);
                })
                ->state(function (?ContentBlock $record): string {
                    if ($record === null) {
                        return '';
                    }

                    $result = app(GetBlockServingStatus::class)($record);
                    $label = $result->status->label();
                    if ($result->healthOutcome !== null && $result->healthOutcome !== ServingHealthOutcome::Servable) {
                        $label .= ' ('.self::humanServingReason($result->healthOutcome).')';
                    }

                    return $label;
                }),

            TextEntry::make('starts_at')
                ->label("Starts at ({$timezone})")
                ->state(fn (?ContentBlock $record): string => $record?->starts_at?->copy()->setTimezone($timezone)->format('Y-m-d H:i:s') ?? '—'),

            TextEntry::make('ends_at')
                ->label("Ends at ({$timezone})")
                ->state(fn (?ContentBlock $record): string => $record?->ends_at?->copy()->setTimezone($timezone)->format('Y-m-d H:i:s') ?? '—'),

            TextEntry::make('schema_version')
                ->label('Schema Version')
                ->state(fn (?ContentBlock $record): string => $record !== null ? (string) $record->schema_version : '1'),

            TextEntry::make('created_by')
                ->label('Created by')
                ->state(function (?ContentBlock $record): string {
                    if ($record === null || $record->created_by === null) {
                        return '—';
                    }

                    $user = User::find($record->created_by);
                    if ($user === null) {
                        return '—';
                    }

                    return $user->name ?? $user->email ?? '—';
                }),

            TextEntry::make('updated_by')
                ->label('Updated by')
                ->state(function (?ContentBlock $record): string {
                    if ($record === null || $record->updated_by === null) {
                        return '—';
                    }

                    $user = User::find($record->updated_by);
                    if ($user === null) {
                        return '—';
                    }

                    return $user->name ?? $user->email ?? '—';
                }),

            TextEntry::make('created_at')
                ->label("Created at ({$timezone})")
                ->state(fn (?ContentBlock $record): string => $record?->created_at?->copy()->setTimezone($timezone)->format('Y-m-d H:i:s') ?? '—'),

            TextEntry::make('updated_at')
                ->label("Updated at ({$timezone})")
                ->state(fn (?ContentBlock $record): string => $record?->updated_at?->copy()->setTimezone($timezone)->format('Y-m-d H:i:s') ?? '—'),
        ];
    }
}
