<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\ContentBlockResource\Pages;

use App\Domain\Content\Actions\GetUpgradedBlockPayload;
use App\Domain\Content\Actions\UpdateContentBlock as UpdateContentBlockAction;
use App\Domain\Content\Exceptions\IllegalStateTransitionException;
use App\Domain\Content\Exceptions\InvalidPayloadException;
use App\Domain\Content\Exceptions\PlacementCapacityExceededException;
use App\Domain\Content\Exceptions\UnknownBlockTypeException;
use App\Domain\Content\Exceptions\UnsupportedSchemaVersionException;
use App\Domain\Content\Exceptions\UpgradeFailedException;
use App\Domain\Content\Models\ContentBlock;
use App\Filament\Admin\Resources\ContentBlockResource;
use App\Filament\Admin\Resources\ContentBlockResource\Pages\Concerns\InteractsWithContentBlockActions;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Exceptions\Halt;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

final class EditContentBlock extends EditRecord
{
    use InteractsWithContentBlockActions;

    protected static string $resource = ContentBlockResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        /** @var ContentBlock $record */
        $record = $this->getRecord();

        try {
            $upgraded = app(GetUpgradedBlockPayload::class)($record->type, $record->schema_version, $record->payload);
            $data['payload'] = $upgraded->toArray();

            return $data;
        } catch (UnknownBlockTypeException $e) {
            $msg = 'The block type is not recognized.';
        } catch (UnsupportedSchemaVersionException $e) {
            $msg = 'The block schema version is not supported.';
        } catch (UpgradeFailedException $e) {
            $msg = 'The block payload could not be upgraded.';
        } catch (InvalidPayloadException $e) {
            $msg = 'The block payload is invalid.';
        }

        Log::warning("Content block [{$record->public_id}] failed hydration: ".get_class($e));
        Notification::make()->danger()->title('Cannot edit content block')->body($msg)->send();
        $this->redirect(self::getResource()::getUrl('index'));
        $data['payload'] = [];

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        [$actionData, $rawPayload] = $this->prepareUpdateData($data);
        $causer = Filament::auth()->user();

        try {
            return app(UpdateContentBlockAction::class)((int) $record->getKey(), $actionData, $rawPayload, $causer);
        } catch (ValidationException $e) {
            $this->throwRemappedValidationException($e);
        } catch (PlacementCapacityExceededException $e) {
            Notification::make()->danger()->title('Capacity Exceeded')->body($e->getMessage())->send();
            throw new Halt;
        } catch (IllegalStateTransitionException $e) {
            Notification::make()->danger()->title('Cannot Update Block')->body('This block has been archived and cannot be edited.')->send();
            $this->redirect(self::getResource()::getUrl('index'));
            throw new Halt;
        } catch (UnknownBlockTypeException|UnsupportedSchemaVersionException $e) {
            Notification::make()->danger()->title('Update Failed')->body('The block cannot be updated due to a schema mismatch.')->send();
            $this->redirect(self::getResource()::getUrl('index'));
            throw new Halt;
        }
    }
}
