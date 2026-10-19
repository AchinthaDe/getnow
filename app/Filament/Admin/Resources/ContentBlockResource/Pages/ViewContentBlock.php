<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\ContentBlockResource\Pages;

use App\Domain\Content\Actions\GetUpgradedBlockPayload;
use App\Domain\Content\Exceptions\InvalidPayloadException;
use App\Domain\Content\Exceptions\UnknownBlockTypeException;
use App\Domain\Content\Exceptions\UnsupportedSchemaVersionException;
use App\Domain\Content\Exceptions\UpgradeFailedException;
use App\Domain\Content\Models\ContentBlock;
use App\Filament\Admin\Resources\ContentBlockResource;
use App\Filament\Admin\Resources\ContentBlockResource\Actions\ContentBlockTransitionActions;
use App\Filament\Admin\Resources\ContentBlockResource\Schemas\ContentBlockMetadataSchema;
use App\Filament\Blocks\BlockFormRegistry;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Log;

final class ViewContentBlock extends ViewRecord
{
    protected static string $resource = ContentBlockResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
            ContentBlockTransitionActions::publish(),
            ContentBlockTransitionActions::enable(),
            ContentBlockTransitionActions::disable(),
            ContentBlockTransitionActions::archive(),
        ];
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
        Notification::make()->danger()->title('Cannot view content block')->body($msg)->send();
        $this->redirect(self::getResource()::getUrl('index'));
        $data['payload'] = [];

        return $data;
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Storefront Preview (Approximation)')
                ->schema([
                    View::make('filament.admin.resources.content-block-resource.components.view-isolated-preview'),
                ]),

            Section::make('Metadata')
                ->schema(ContentBlockMetadataSchema::schema())
                ->columns(2),

            Section::make('Payload')
                ->statePath('payload')
                ->schema(function (?ContentBlock $record): array {
                    if ($record === null || ! BlockFormRegistry::has($record->type)) {
                        return [];
                    }

                    return BlockFormRegistry::get($record->type);
                }),
        ]);
    }
}
