<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\BrandResource\Pages;

use App\Domain\Catalog\Actions\Brand\UpdateBrand as UpdateBrandAction;
use App\Domain\Catalog\Data\Brand\UpdateBrandData;
use App\Domain\Catalog\Exceptions\BrandRuleViolation;
use App\Domain\Catalog\Models\Brand;
use App\Filament\Admin\Resources\BrandResource;
use App\Filament\Admin\Resources\BrandResource\Actions\BrandTableActions;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Exceptions\Halt;
use Illuminate\Database\Eloquent\Model;

final class EditBrand extends EditRecord
{
    protected static string $resource = BrandResource::class;

    protected function getHeaderActions(): array
    {
        return [
            BrandTableActions::activate(),
            BrandTableActions::deactivate(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        assert($record instanceof Brand);
        $user = Filament::auth()->user();
        $causer = $user instanceof User ? $user : null;
        try {
            $payload = [];
            if (array_key_exists('name', $data) && $data['name'] !== null) {
                $payload['name'] = (string) $data['name'];
            }
            if (array_key_exists('show_in_nav', $data) && $data['show_in_nav'] !== null) {
                $payload['show_in_nav'] = (bool) $data['show_in_nav'];
            }

            $actionData = UpdateBrandData::validateAndCreate($payload);

            return app(UpdateBrandAction::class)($record->public_id, $actionData, $causer);
        } catch (BrandRuleViolation $e) {
            Notification::make()->danger()->title('Cannot Update Brand')->body($e->getMessage())->send();
            throw new Halt;
        }
    }

    protected function getRedirectUrl(): string
    {
        return self::getResource()::getUrl('index');
    }
}
