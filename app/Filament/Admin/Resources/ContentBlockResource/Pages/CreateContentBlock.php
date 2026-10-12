<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\ContentBlockResource\Pages;

use App\Domain\Content\Actions\CreateContentBlock as CreateContentBlockAction;
use App\Domain\Content\Exceptions\UnknownBlockTypeException;
use App\Filament\Admin\Resources\ContentBlockResource;
use App\Filament\Admin\Resources\ContentBlockResource\Pages\Concerns\InteractsWithContentBlockActions;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Filament\Support\Exceptions\Halt;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

final class CreateContentBlock extends CreateRecord
{
    use InteractsWithContentBlockActions;

    protected static string $resource = ContentBlockResource::class;

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        [$actionData, $rawPayload] = $this->prepareCreateData($data);
        $causer = Filament::auth()->user();

        try {
            return app(CreateContentBlockAction::class)($actionData, $rawPayload, $causer);
        } catch (ValidationException $e) {
            $this->throwRemappedValidationException($e);
        } catch (UnknownBlockTypeException $e) {
            Notification::make()->danger()->title('Invalid Block Type')->body('The selected block type is not registered.')->send();
            throw new Halt;
        }
    }

    protected function getRedirectUrl(): string
    {
        return self::getResource()::getUrl('edit', ['record' => $this->getRecord()]);
    }
}
