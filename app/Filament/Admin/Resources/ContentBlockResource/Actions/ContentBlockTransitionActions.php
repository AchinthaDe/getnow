<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\ContentBlockResource\Actions;

use App\Domain\Content\Actions\ArchiveContentBlock;
use App\Domain\Content\Actions\DisableContentBlock;
use App\Domain\Content\Actions\EnableContentBlock;
use App\Domain\Content\Actions\PublishContentBlock;
use App\Domain\Content\Enums\PublishStatus;
use App\Domain\Content\Exceptions\IllegalStateTransitionException;
use App\Domain\Content\Exceptions\UnservablePayloadException;
use App\Domain\Content\Models\ContentBlock;
use App\Models\User;
use Closure;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;

final class ContentBlockTransitionActions
{
    private static function executeTransition(
        ContentBlock $record,
        Action $action,
        string $ability,
        Closure $domainCall,
        string $actionVerb,
        string $pastVerb,
    ): void {
        $authUser = Filament::auth()->user();
        if (! $authUser instanceof User) {
            abort(403);
        }
        abort_unless($authUser->can($ability, $record), 403);

        try {
            $domainCall($authUser);
        } catch (UnservablePayloadException) {
            Notification::make()
                ->danger()
                ->title("{$actionVerb} Failed")
                ->body('The content block payload cannot be safely served.')
                ->send();
            $action->halt();
        } catch (IllegalStateTransitionException) {
            Notification::make()
                ->danger()
                ->title('Action Failed')
                ->body("This block cannot be {$pastVerb} in its current state.")
                ->send();
            $record->refresh();
            $action->halt();
        }

        $record->refresh();
        Notification::make()
            ->success()
            ->title("Content Block {$pastVerb}")
            ->body("The content block has been {$pastVerb}.")
            ->send();
    }

    public static function publish(): Action
    {
        return Action::make('publish')
            ->label('Publish')
            ->color('success')
            ->icon('heroicon-o-arrow-up-circle')
            ->requiresConfirmation()
            ->modalHeading('Publish Content Block')
            ->modalDescription('This content block will become eligible to show on the storefront within its schedule.')
            ->modalSubmitActionLabel('Publish')
            ->authorize('publish')
            ->visible(fn (?ContentBlock $record): bool => $record !== null && $record->status === PublishStatus::DRAFT)
            ->action(function (ContentBlock $record, Action $action): void {
                self::executeTransition(
                    record: $record,
                    action: $action,
                    ability: 'publish',
                    domainCall: fn (User $causer) => app(PublishContentBlock::class)((int) $record->id, $causer),
                    actionVerb: 'Publish',
                    pastVerb: 'published',
                );
            });
    }

    public static function archive(): Action
    {
        return Action::make('archive')
            ->label('Archive')
            ->color('danger')
            ->icon('heroicon-o-archive-box')
            ->requiresConfirmation()
            ->modalHeading('Archive Content Block')
            ->modalDescription('Archiving cannot be undone from the panel. The block will be removed from display and cannot be edited.')
            ->modalSubmitActionLabel('Archive')
            ->authorize('archive')
            ->visible(fn (?ContentBlock $record): bool => $record !== null && $record->status !== PublishStatus::ARCHIVED)
            ->action(function (ContentBlock $record, Action $action): void {
                self::executeTransition(
                    record: $record,
                    action: $action,
                    ability: 'archive',
                    domainCall: fn (User $causer) => app(ArchiveContentBlock::class)((int) $record->id, $causer),
                    actionVerb: 'Archive',
                    pastVerb: 'archived',
                );
            });
    }

    public static function enable(): Action
    {
        return Action::make('enable')
            ->label('Enable')
            ->color('success')
            ->icon('heroicon-o-check-circle')
            ->requiresConfirmation()
            ->modalHeading('Enable Content Block')
            ->modalDescription('This content block will become eligible to show on the storefront within its schedule.')
            ->modalSubmitActionLabel('Enable')
            ->authorize('enable')
            ->visible(fn (?ContentBlock $record): bool => $record !== null && $record->status === PublishStatus::PUBLISHED && ! $record->is_enabled)
            ->action(function (ContentBlock $record, Action $action): void {
                self::executeTransition(
                    record: $record,
                    action: $action,
                    ability: 'enable',
                    domainCall: fn (User $causer) => app(EnableContentBlock::class)((int) $record->id, $causer),
                    actionVerb: 'Enable',
                    pastVerb: 'enabled',
                );
            });
    }

    public static function disable(): Action
    {
        return Action::make('disable')
            ->label('Disable')
            ->color('gray')
            ->icon('heroicon-o-x-circle')
            ->authorize('disable')
            ->visible(fn (?ContentBlock $record): bool => $record !== null && $record->status === PublishStatus::PUBLISHED && $record->is_enabled)
            ->action(function (ContentBlock $record, Action $action): void {
                self::executeTransition(
                    record: $record,
                    action: $action,
                    ability: 'disable',
                    domainCall: fn (User $causer) => app(DisableContentBlock::class)((int) $record->id, $causer),
                    actionVerb: 'Disable',
                    pastVerb: 'disabled',
                );
            });
    }
}
