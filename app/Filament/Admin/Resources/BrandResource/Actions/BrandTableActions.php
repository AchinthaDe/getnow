<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\BrandResource\Actions;

use App\Domain\Catalog\Actions\Brand\ActivateBrand;
use App\Domain\Catalog\Actions\Brand\DeactivateBrand;
use App\Domain\Catalog\Exceptions\BrandRuleViolation;
use App\Domain\Catalog\Models\Brand;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;

final class BrandTableActions
{
    public static function activate(): Action
    {
        return Action::make('activate')
            ->label('Activate')
            ->icon('heroicon-o-check-circle')
            ->color('success')
            ->visible(fn (Brand $record): bool => ! $record->is_active)
            ->authorize(fn (Brand $record): bool => (bool) Filament::auth()->user()?->can('update', $record))
            ->action(function (Brand $record, Action $action): void {
                $user = Filament::auth()->user();
                $causer = $user instanceof User ? $user : null;
                if ($causer === null) {
                    abort(403);
                }
                abort_unless($causer->can('update', $record), 403);
                try {
                    app(ActivateBrand::class)($record->public_id, $causer);
                    Notification::make()->success()->title('Brand Activated')->body("Brand [{$record->name}] has been activated.")->send();
                } catch (BrandRuleViolation $e) {
                    Notification::make()->danger()->title('Activation Failed')->body($e->getMessage())->send();
                    $action->halt();
                }
            });
    }

    public static function deactivate(): Action
    {
        return Action::make('deactivate')
            ->label('Deactivate')
            ->icon('heroicon-o-x-circle')
            ->color('warning')
            ->visible(fn (Brand $record): bool => $record->is_active)
            ->requiresConfirmation()
            ->modalHeading('Deactivate Brand')
            ->modalDescription('Are you sure you want to deactivate this brand? Inactive brands are hidden from selection options.')
            ->authorize(fn (Brand $record): bool => (bool) Filament::auth()->user()?->can('update', $record))
            ->action(function (Brand $record, Action $action): void {
                $user = Filament::auth()->user();
                $causer = $user instanceof User ? $user : null;
                if ($causer === null) {
                    abort(403);
                }
                abort_unless($causer->can('update', $record), 403);
                try {
                    app(DeactivateBrand::class)($record->public_id, $causer);
                    Notification::make()->success()->title('Brand Deactivated')->body("Brand [{$record->name}] has been deactivated.")->send();
                } catch (BrandRuleViolation $e) {
                    Notification::make()->danger()->title('Deactivation Failed')->body($e->getMessage())->send();
                    $action->halt();
                }
            });
    }
}
